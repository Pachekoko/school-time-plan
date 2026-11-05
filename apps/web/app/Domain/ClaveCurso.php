<?php

namespace App\Domain;

use App\Models\Curso;
use App\Models\Grupo;
use App\Models\Materia;

/**
 * La llave natural de un curso: «{grupos unidos con +}-{materia}», con -2, -3… si ya
 * existe. La importación la usa para no duplicar cursos al reimportar.
 */
final class ClaveCurso
{
    /**
     * @param  iterable<Grupo>  $grupos
     * @param  list<string>  $tomadas  claves ya usadas que todavía no están en la base
     */
    public static function generar(iterable $grupos, Materia $materia, int $cicloId, array $tomadas = []): string
    {
        $claves = [];

        foreach ($grupos as $grupo) {
            $claves[] = $grupo->clave;
        }

        natsort($claves);
        $base = implode('+', $claves).'-'.$materia->clave;

        $usadas = array_flip([
            ...Curso::query()->where('ciclo_id', $cicloId)->pluck('clave')->all(),
            ...$tomadas,
        ]);

        $clave = $base;

        for ($n = 2; isset($usadas[$clave]); $n++) {
            $clave = "{$base}-{$n}";
        }

        return $clave;
    }
}
