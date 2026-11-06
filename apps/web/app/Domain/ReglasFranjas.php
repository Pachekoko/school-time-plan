<?php

namespace App\Domain;

/**
 * Las mismas reglas de franjas que aplica el motor: dentro de una plantilla y un día,
 * ordenadas por `orden`, cada franja empieza cuando termina la anterior o después.
 */
final class ReglasFranjas
{
    /**
     * Pares consecutivos que se traslapan, por día.
     *
     * @param  iterable<array{dia: int, orden: int, inicio: string, fin: string}>  $franjas  las de una plantilla
     * @return list<array{dia: int, antes: int, despues: int, mensaje: string}>
     */
    public static function traslapes(iterable $franjas): array
    {
        $porDia = [];

        foreach ($franjas as $franja) {
            $porDia[$franja['dia']][] = $franja;
        }

        ksort($porDia);
        $traslapes = [];

        foreach ($porDia as $dia => $lista) {
            usort($lista, fn (array $a, array $b) => $a['orden'] <=> $b['orden']);

            for ($i = 1; $i < count($lista); $i++) {
                [$antes, $despues] = [$lista[$i - 1], $lista[$i]];

                if (Hora::aMinutos($despues['inicio']) < Hora::aMinutos($antes['fin'])) {
                    $traslapes[] = [
                        'dia' => $dia,
                        'antes' => $antes['orden'],
                        'despues' => $despues['orden'],
                        'mensaje' => self::mensajeTraslape($dia, $antes['orden'], $despues['orden']),
                    ];
                }
            }
        }

        return $traslapes;
    }

    public static function mensajeTraslape(int $dia, int $antes, int $despues): string
    {
        $nombre = Dias::corto($dia);

        return "Las franjas {$nombre} {$antes} y {$nombre} {$despues} se traslapan.";
    }
}
