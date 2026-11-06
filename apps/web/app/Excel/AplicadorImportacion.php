<?php

namespace App\Excel;

use App\Enums\EstadoDisponibilidad;
use App\Models\Ciclo;
use App\Models\Espacio;
use App\Models\Materia;
use App\Models\PlanEstudios;
use App\Models\Plantel;
use App\Models\PlantillaHoraria;
use App\Models\Profesor;
use App\Support\EscuelaActual;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Aplica un plan de importación sin errores, todo o nada.
 */
final class AplicadorImportacion
{
    private Ciclo $ciclo;

    /** @var array<string, array<string, int>> tipo => clave => id */
    private array $ids = [];

    public function aplicar(PlanImportacion $plan): void
    {
        if ($plan->tieneErrores()) {
            throw new LogicException('Una importación con errores no se aplica.');
        }

        app(EscuelaActual::class)->usar($plan->escuelaId, function () use ($plan): void {
            DB::transaction(function () use ($plan): void {
                $this->ciclo = Ciclo::query()->findOrFail($plan->cicloId);
                $this->ids = [];

                foreach ($plan->operaciones() as $operacion) {
                    $this->operar($operacion['tipo'], $operacion['datos']);
                }
            });
        });
    }

    /**
     * @param  array<string, mixed>  $d
     */
    private function operar(string $tipo, array $d): void
    {
        switch ($tipo) {
            case 'plantel':
                Plantel::query()->updateOrCreate(['clave' => $d['clave']], ['nombre' => $d['nombre'], 'direccion' => $d['direccion']]);
                break;
            case 'espacio':
                Espacio::query()->updateOrCreate(['clave' => $d['clave']], [
                    'plantel_id' => $this->id('plantel', $d['plantel']), 'nombre' => $d['nombre'], 'tipo' => $d['tipo'], 'capacidad' => $d['capacidad'],
                ]);
                break;
            case 'plantilla':
                PlantillaHoraria::query()->firstOrCreate(['clave' => $d['clave']], ['nombre' => $d['clave']]);
                break;
            case 'franja':
                PlantillaHoraria::query()->findOrFail($this->id('plantilla', $d['plantilla']))->franjas()->updateOrCreate(
                    ['dia' => $d['dia'], 'orden' => $d['orden']],
                    ['inicio' => $d['inicio'], 'fin' => $d['fin'], 'tipo' => $d['tipo']],
                );
                break;
            case 'plan':
                PlanEstudios::query()->firstOrCreate(['clave' => $d['clave']], ['nombre' => $d['clave']]);
                break;
            case 'materia':
                Materia::query()->updateOrCreate(['clave' => $d['clave']], [
                    'plan_id' => $this->id('plan', $d['plan']), 'nombre' => $d['nombre'], 'semestre' => $d['semestre'],
                    'horas_semana' => $d['horas_semana'], 'patrones' => $d['patrones'], 'tipo_espacio' => $d['tipo_espacio'],
                ]);
                break;
            case 'grupo':
                $this->ciclo->grupos()->updateOrCreate(['clave' => $d['clave']], [
                    'plantel_id' => $this->id('plantel', $d['plantel']), 'plantilla_id' => $this->id('plantilla', $d['plantilla']),
                    'plan_id' => $this->id('plan', $d['plan']), 'semestre' => $d['semestre'], 'alumnos' => $d['alumnos'],
                ]);
                break;
            case 'profesor':
                $profesor = Profesor::query()->updateOrCreate(['clave' => $d['clave']], [
                    'nombre' => $d['nombre'], 'contrato' => $d['contrato'], 'horas_min' => $d['horas_min'],
                    'horas_max' => $d['horas_max'], 'max_dia' => $d['max_dia'], 'sin_huecos' => $d['sin_huecos'],
                ]);
                $profesor->planteles()->sync(array_map(fn (string $c) => $this->id('plantel', $c), $d['planteles']));
                $profesor->materias()->sync(array_map(fn (string $c) => $this->id('materia', $c), $d['materias']));
                break;
            case 'disponibilidad':
                $this->disponibilidad($d);
                break;
            case 'sincronia':
                $this->ciclo->sincronias()->firstOrCreate(['clave' => $d['clave']], ['nombre' => $d['clave']]);
                break;
            case 'curso':
                $curso = $this->ciclo->cursos()->where('clave', $d['clave'])->first() ?? $this->ciclo->cursos()->make();
                $curso->clave = $d['clave'];
                $curso->fill([
                    'materia_id' => $this->id('materia', $d['materia']),
                    'profesor_id' => $d['profesor'] === null ? null : $this->id('profesor', $d['profesor']),
                    'vacante' => $d['vacante'],
                    'sincronia_id' => $d['sincronia'] === null ? null : $this->id('sincronia', $d['sincronia']),
                    'alumnos' => $d['alumnos'],
                ])->save();
                $curso->grupos()->sync(array_map(fn (string $c) => $this->id('grupo', $c), $d['grupos']));
                break;
            default:
                throw new LogicException("Operación desconocida: {$tipo}");
        }
    }

    /**
     * @param  array<string, mixed>  $d
     */
    private function disponibilidad(array $d): void
    {
        $profesor = Profesor::query()->findOrFail($this->id('profesor', $d['profesor']));
        $franjas = PlantillaHoraria::query()->findOrFail($this->id('plantilla', $d['plantilla']))->franjas()->get()
            ->keyBy(fn ($franja) => "{$franja->dia}-{$franja->orden}");

        foreach ($d['celdas'] as $celda) {
            $franja = $franjas->get("{$celda['dia']}-{$celda['orden']}") ?? throw new LogicException('Falta una franja de la disponibilidad.');
            $estado = EstadoDisponibilidad::deLetra($celda['letra']);

            if ($estado === null) {
                $profesor->disponibilidades()->where('franja_id', $franja->id)->delete();
            } else {
                $profesor->disponibilidades()->updateOrCreate(['franja_id' => $franja->id], ['estado' => $estado]);
            }
        }
    }

    /**
     * El id de lo que tiene esa clave, ya sea de antes o creado en esta importación.
     */
    private function id(string $tipo, string $clave): int
    {
        return $this->ids[$tipo][$clave] ??= (int) (match ($tipo) {
            'plantel' => Plantel::query()->where('clave', $clave)->value('id'),
            'plantilla' => PlantillaHoraria::query()->where('clave', $clave)->value('id'),
            'plan' => PlanEstudios::query()->where('clave', $clave)->value('id'),
            'materia' => Materia::query()->where('clave', $clave)->value('id'),
            'profesor' => Profesor::query()->where('clave', $clave)->value('id'),
            'grupo' => $this->ciclo->grupos()->where('clave', $clave)->value('id'),
            'sincronia' => $this->ciclo->sincronias()->where('clave', $clave)->value('id'),
            default => null,
        } ?? throw new LogicException("No se encontró {$tipo} {$clave}"));
    }
}
