<?php

namespace App\Domain;

/**
 * Un grupo que comparte cursos (sincronías, Inglés por nivel) no se puede mover solo a otro
 * plantel o a otra plantilla: el motor rechaza un curso con grupos en lugares distintos.
 */
final class CursosCompartidos
{
    /**
     * @param  list<string>  $cursos  las claves de los cursos que quedarían partidos
     */
    public static function mensaje(string $grupo, array $cursos): string
    {
        $ultimo = array_pop($cursos);
        $lista = $cursos === [] ? "el curso {$ultimo}" : 'los cursos '.implode(', ', $cursos)." y {$ultimo}";

        return "No se puede mover {$grupo}: comparte {$lista} con otros grupos.";
    }
}
