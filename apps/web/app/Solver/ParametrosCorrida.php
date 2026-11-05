<?php

namespace App\Solver;

use App\Enums\ModoRegla;
use App\Models\Escuela;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * El objeto `parametros` del contrato del motor, a partir de lo que manda el formulario de
 * generar (todo opcional) y de la configuración de la escuela.
 */
final class ParametrosCorrida
{
    /** Las reglas suaves y su configuración por omisión, la misma del motor. */
    public const array REGLAS = [
        'S1' => ['modo' => 'obligatoria', 'peso' => 5],
        'S2' => ['modo' => 'preferencia', 'peso' => 7],
        'S3' => ['modo' => 'preferencia', 'peso' => 5],
        'S4' => ['modo' => 'preferencia', 'peso' => 4],
        'S5' => ['modo' => 'preferencia', 'peso' => 3],
        'S6' => ['modo' => 'preferencia', 'peso' => 5],
        'S7' => ['modo' => 'obligatoria', 'peso' => 5],
    ];

    /** Los nombres que usa el motor (apps/solver/src/solver/messages.py: NOMBRES_REGLAS). */
    public const array NOMBRES = [
        'S1' => 'Sin huecos para los grupos',
        'S2' => 'Pocos huecos para profesores',
        'S3' => 'Menos días por profesor',
        'S4' => 'Usar franjas preferentes',
        'S5' => 'Repartir materias en la semana',
        'S6' => 'Reglas de franja',
        'S7' => 'Un docente, una materia por día en cada grupo',
    ];

    /**
     * Las reglas para el formulario de generar, con sus modos permitidos (S3 no puede ser
     * obligatoria).
     *
     * @param  array<string, mixed>  $reglas  las de normalizar()
     * @return list<array{clave: string, nombre: string, modo: string, peso: int, modos: list<string>}>
     */
    public static function paraFormulario(array $reglas): array
    {
        $formulario = [];

        foreach (self::NOMBRES as $clave => $nombre) {
            $modos = array_map(fn (ModoRegla $modo) => $modo->value, ModoRegla::cases());

            $formulario[] = [
                'clave' => $clave,
                'nombre' => $nombre,
                'modo' => (string) $reglas[$clave]['modo'],
                'peso' => (int) $reglas[$clave]['peso'],
                'modos' => $clave === 'S3' ? array_values(array_diff($modos, [ModoRegla::Obligatoria->value])) : $modos,
            ];
        }

        return $formulario;
    }

    /**
     * @param  array<string, mixed>  $entrada  `tiempo_limite_s`, `permitir_parcial` y `reglas.{S1..S7}.{modo,peso}`
     * @return array{tiempo_limite_s: int, semilla: int, num_workers: int, permitir_parcial: bool, minutos_traslado: int, reglas: array<string, mixed>}
     *
     * @throws ValidationException
     */
    public static function normalizar(array $entrada, Escuela $escuela): array
    {
        Validator::make($entrada, [
            'tiempo_limite_s' => ['nullable', 'integer', 'between:1,300'],
            'permitir_parcial' => ['nullable', 'boolean'],
            'reglas' => ['nullable', 'array:'.implode(',', array_keys(self::REGLAS))],
            'reglas.*.modo' => ['required', Rule::enum(ModoRegla::class)],
            'reglas.*.peso' => ['nullable', 'integer', 'between:1,10'],
            'reglas.S3.modo' => [Rule::notIn([ModoRegla::Obligatoria->value])],
        ], [
            'reglas.S3.modo.not_in' => 'La regla S3 no puede ser obligatoria.',
        ], [
            'tiempo_limite_s' => 'tiempo límite',
        ])->validate();

        $reglas = [];

        foreach (self::REGLAS as $clave => $porOmision) {
            $dada = (array) ($entrada['reglas'][$clave] ?? []);
            $reglas[$clave] = [
                'modo' => (string) ($dada['modo'] ?? $porOmision['modo']),
                'peso' => (int) ($dada['peso'] ?? $porOmision['peso']),
            ];

            if ($clave === 'S6') {
                // Las restricciones de franja de S6 se capturan en la fase 3.
                $reglas['S6_restricciones'] = [];
            }
        }

        return [
            'tiempo_limite_s' => (int) ($entrada['tiempo_limite_s'] ?? $escuela->tiempo_limite_s),
            'semilla' => 0,
            'num_workers' => max(1, min(16, (int) config('solver.workers'))),
            'permitir_parcial' => (bool) ($entrada['permitir_parcial'] ?? false),
            'minutos_traslado' => $escuela->minutos_traslado,
            'reglas' => $reglas,
        ];
    }
}
