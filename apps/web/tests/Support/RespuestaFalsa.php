<?php

namespace Tests\Support;

use App\Models\Ciclo;
use App\Models\Curso;
use App\Models\Espacio;
use App\Support\EscuelaActual;

/**
 * Respuestas de `POST /solve` para el motor falso de las pruebas. No son horarios válidos:
 * la web no valida lo que devuelve el motor, solo lo guarda.
 */
final class RespuestaFalsa
{
    /**
     * Cada bloque del patrón 0 de cada curso en franjas consecutivas (4 por día), con su
     * profesor y el primer espacio del tipo de la materia.
     *
     * @return array<string, mixed>
     */
    public static function ok(Ciclo $ciclo): array
    {
        return self::respuesta('ok', self::sesiones($ciclo));
    }

    /**
     * @param  list<array<string, mixed>>  $diagnostico
     * @return array<string, mixed>
     */
    public static function sinSolucion(array $diagnostico): array
    {
        return self::respuesta('sin_solucion', [], $diagnostico);
    }

    /**
     * Como ok(), pero el primer curso queda sin acomodar.
     *
     * @return array<string, mixed>
     */
    public static function parcial(Ciclo $ciclo): array
    {
        $sesiones = self::sesiones($ciclo);
        $primero = $sesiones[0]['curso'];

        foreach ($sesiones as $i => $sesion) {
            if ($sesion['curso'] === $primero) {
                $sesiones[$i] = [...$sesion, 'dia' => null, 'orden_inicio' => null, 'espacio' => null, 'profesor' => null, 'sin_acomodar' => true];
            }
        }

        return self::respuesta('factible_parcial', $sesiones, [[
            'codigo' => 'SIN_ACOMODAR', 'regla' => 'H1', 'entidades' => [['tipo' => 'curso', 'id' => $primero]],
            'mensaje' => 'Un curso quedó sin acomodar.',
        ]]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function sesiones(Ciclo $ciclo): array
    {
        return app(EscuelaActual::class)->usar($ciclo->escuela_id, function () use ($ciclo): array {
            $sesiones = [];
            [$dia, $orden] = [0, 0];

            foreach ($ciclo->cursos()->with('materia')->orderBy('id')->get() as $curso) {
                /** @var Curso $curso */
                $espacio = Espacio::query()->where('tipo', $curso->materia->tipo_espacio)->orderBy('id')->first();

                foreach ($curso->materia->patronesEfectivos()[0] as $bloque => $horas) {
                    if ($orden + $horas > 4) {
                        [$dia, $orden] = [$dia + 1, 0];
                    }

                    $sesiones[] = [
                        'curso' => (string) $curso->id, 'patron' => 0, 'bloque' => $bloque,
                        'dia' => $dia, 'orden_inicio' => $orden,
                        'espacio' => $espacio === null ? null : (string) $espacio->id,
                        'profesor' => $curso->profesor_id === null ? null : (string) $curso->profesor_id,
                        'fijada' => false, 'sin_acomodar' => false,
                    ];
                    $orden += $horas;
                }
            }

            return $sesiones;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $sesiones
     * @param  list<array<string, mixed>>  $diagnostico
     * @return array<string, mixed>
     */
    private static function respuesta(string $estado, array $sesiones, array $diagnostico = []): array
    {
        return [
            'schema_version' => '1',
            'estado' => $estado,
            'sesiones' => $sesiones,
            'puntaje' => 12,
            'desglose' => ['S2' => ['crudo' => 2, 'peso' => 6, 'penalizacion' => 12]],
            'diagnostico' => $diagnostico,
            'estadisticas' => ['tiempo_s' => 0.4, 'estado_cp_sat' => 'OPTIMAL', 'variables' => 100, 'restricciones' => 200],
        ];
    }
}
