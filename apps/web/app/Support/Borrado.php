<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\ValidationException;

final class Borrado
{
    /**
     * Impide borrar algo que otros registros usan. Cada uso con al menos un registro da un
     * mensaje «No se puede borrar «{nombre}»: lo usan {n} {cosa}.» bajo la clave `borrar`.
     *
     * La cosa puede venir como «singular|plural» (`'espacio|espacios'`) para que un solo uso
     * se lea «lo usa 1 espacio».
     *
     * @param  array<string, Relation<*, *, *>|Builder<*>>  $usos  cosa => relación o consulta que cuenta los usos
     *
     * @throws ValidationException
     */
    public static function impedirSiSeUsa(string $nombre, array $usos): void
    {
        $mensajes = [];

        foreach ($usos as $cosa => $consulta) {
            $n = $consulta->count();

            if ($n === 0) {
                continue;
            }

            [$una, $varias] = array_pad(explode('|', $cosa, 2), 2, $cosa);

            $mensajes[] = $n === 1
                ? "No se puede borrar «{$nombre}»: lo usa 1 {$una}."
                : "No se puede borrar «{$nombre}»: lo usan {$n} {$varias}.";
        }

        if ($mensajes !== []) {
            throw ValidationException::withMessages(['borrar' => $mensajes]);
        }
    }
}
