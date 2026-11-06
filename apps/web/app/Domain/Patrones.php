<?php

namespace App\Domain;

use InvalidArgumentException;

/**
 * Los patrones de una materia dicen cómo se reparten sus horas en bloques de días
 * distintos: `[[2, 1]]` es un bloque de 2 h y otro de 1 h; `[[2], [1, 1]]` da dos
 * alternativas. En texto se escriben «2 | 1+1».
 */
final class Patrones
{
    /** El bloque más largo que acepta el contrato del motor. */
    private const int BLOQUE_MAXIMO = 24;

    /**
     * El patrón por omisión: bloques de 2 h y uno de 1 h si sobra; 2 h admite también 1+1.
     *
     * @return list<list<int>>
     */
    public static function derivar(int $horas): array
    {
        if ($horas === 2) {
            return [[2], [1, 1]];
        }

        $bloques = array_fill(0, intdiv($horas, 2), 2);

        if ($horas % 2 === 1) {
            $bloques[] = 1;
        }

        return [$bloques];
    }

    /**
     * @return list<list<int>>
     *
     * @throws InvalidArgumentException si el texto no es un patrón o alguna alternativa no suma `$horas`
     */
    public static function parsear(string $texto, int $horas): array
    {
        $patrones = [];

        foreach (explode('|', $texto) as $alternativa) {
            $bloques = [];

            foreach (explode('+', $alternativa) as $bloque) {
                $bloque = trim($bloque);

                if (preg_match('/^[1-9][0-9]*$/', $bloque) !== 1 || (int) $bloque > self::BLOQUE_MAXIMO) {
                    throw new InvalidArgumentException('«'.trim($texto).'» no es un patrón válido: escribe bloques como 2+1 y separa alternativas con |.');
                }

                $bloques[] = (int) $bloque;
            }

            $patrones[] = $bloques;
        }

        foreach ($patrones as $bloques) {
            $suma = array_sum($bloques);

            if ($suma !== $horas) {
                throw new InvalidArgumentException('El patrón '.implode('+', $bloques)." suma {$suma} y horas_semana es {$horas}.");
            }
        }

        return $patrones;
    }

    /**
     * @param  list<list<int>>  $patrones
     */
    public static function formatear(array $patrones): string
    {
        return implode(' | ', array_map(fn (array $alternativa) => implode('+', $alternativa), $patrones));
    }
}
