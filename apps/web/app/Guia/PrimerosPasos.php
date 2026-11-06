<?php

namespace App\Guia;

use App\Enums\EstadoCorrida;
use App\Enums\TipoFranja;
use App\Models\Escuela;
use Illuminate\Support\Facades\DB;

/**
 * Qué pasos de la guía de primeros pasos ya tienen datos en una escuela. El avance siempre
 * se deduce de los datos: no se guarda en qué paso va nadie.
 */
final class PrimerosPasos
{
    /** Las claves en el orden en que la app necesita los datos. */
    public const array PASOS = ['ciclo', 'planteles', 'espacios', 'plantilla', 'materias', 'grupos', 'profesores', 'carga', 'generar'];

    /**
     * Una sola consulta: las subconsultas filtran a mano por escuela y por ciclo actual,
     * porque en SQL crudo no aplica el global scope.
     *
     * @return array<string, bool> las claves de PASOS, en orden
     */
    public function hechos(Escuela $escuela): array
    {
        $ciclo = $escuela->ciclo_actual_id ?? 0;
        $conHorario = [EstadoCorrida::Ok->value, EstadoCorrida::FactibleParcial->value];

        $fila = (array) DB::selectOne(
            'select
                exists(select 1 from planteles where escuela_id = ?) as planteles,
                exists(select 1 from espacios where escuela_id = ?) as espacios,
                exists(select 1 from franjas where escuela_id = ? and tipo = ?) as plantilla,
                exists(select 1 from materias where escuela_id = ?) as materias,
                exists(select 1 from grupos where ciclo_id = ?) as grupos,
                exists(select 1 from profesores where escuela_id = ?) as profesores,
                exists(select 1 from cursos where ciclo_id = ?) as carga,
                exists(select 1 from corridas where ciclo_id = ? and estado in (?, ?)) as generar',
            [$escuela->id, $escuela->id, $escuela->id, TipoFranja::Clase->value, $escuela->id, $ciclo, $escuela->id, $ciclo, $ciclo, ...$conHorario],
        );

        $hechos = [];

        foreach (self::PASOS as $paso) {
            $hechos[$paso] = $paso === 'ciclo' ? $escuela->ciclo_actual_id !== null : (bool) $fila[$paso];
        }

        return $hechos;
    }
}
