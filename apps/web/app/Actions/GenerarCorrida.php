<?php

namespace App\Actions;

use App\Enums\EstadoCorrida;
use App\Jobs\ResolverCorrida;
use App\Models\Ciclo;
use App\Models\Corrida;
use App\Models\Escuela;
use App\Solver\ParametrosCorrida;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pone en cola una corrida del ciclo, a lo más una activa a la vez.
 */
final class GenerarCorrida
{
    /**
     * @param  array<string, mixed>  $entrada  lo del formulario de generar
     *
     * @throws CorridaEnCurso
     * @throws ValidationException
     */
    public function __invoke(Ciclo $ciclo, array $entrada): Corrida
    {
        $parametros = ParametrosCorrida::normalizar($entrada, Escuela::query()->findOrFail($ciclo->escuela_id));

        return $this->encolar($ciclo, fn (): Corrida => $ciclo->corridas()->create([
            'estado' => EstadoCorrida::Pendiente,
            'parametros' => $parametros,
            'tiempo_limite_s' => $parametros['tiempo_limite_s'],
        ]));
    }

    /**
     * Vuelve a poner en cola una corrida que terminó en error.
     *
     * @throws CorridaEnCurso
     */
    public function reintentar(Corrida $corrida): void
    {
        $this->encolar($corrida->ciclo()->firstOrFail(), function () use ($corrida): Corrida {
            $corrida->update([
                'estado' => EstadoCorrida::Pendiente,
                'mensaje_error' => null,
                'iniciada_en' => null,
                'terminada_en' => null,
            ]);

            return $corrida;
        });
    }

    /**
     * @param  callable(): Corrida  $preparar
     */
    private function encolar(Ciclo $ciclo, callable $preparar): Corrida
    {
        try {
            return DB::transaction(function () use ($ciclo, $preparar): Corrida {
                if (Corrida::query()->where('ciclo_id', $ciclo->id)->activas()->lockForUpdate()->exists()) {
                    throw new CorridaEnCurso;
                }

                $corrida = $preparar();
                ResolverCorrida::dispatch($corrida->id)->afterCommit();

                return $corrida;
            });
        } catch (UniqueConstraintViolationException) {
            // Otra petición ganó la carrera entre la revisión y el insert.
            throw new CorridaEnCurso;
        }
    }
}
