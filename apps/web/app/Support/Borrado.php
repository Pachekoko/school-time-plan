<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

final class Borrado
{
    /**
     * Impide borrar algo que otros registros usan, con un solo mensaje bajo la clave `borrar`
     * y en un toast de error (que se ve en cualquier página): «No se puede borrar «{nombre}»:
     * lo usan {n} {cosa} y {m} {otra}.».
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
        $partes = [];
        $total = 0;

        foreach ($usos as $cosa => $consulta) {
            $n = $consulta->count();

            if ($n === 0) {
                continue;
            }

            [$una, $varias] = array_pad(explode('|', $cosa, 2), 2, $cosa);
            $partes[] = $n === 1 ? "1 {$una}" : "{$n} {$varias}";
            $total += $n;
        }

        if ($partes === []) {
            return;
        }

        $ultima = array_pop($partes);
        $lista = $partes === [] ? $ultima : implode(', ', $partes)." y {$ultima}";
        $mensaje = "No se puede borrar «{$nombre}»: lo ".($total === 1 ? 'usa' : 'usan')." {$lista}.";

        Inertia::flash('toast', ['type' => 'error', 'message' => $mensaje]);

        throw ValidationException::withMessages(['borrar' => $mensaje]);
    }
}
