<?php

namespace App\Domain;

use App\Enums\TipoFranja;

/**
 * Arma las franjas de una plantilla: `$n` clases seguidas por día, con un receso opcional.
 * Misma semántica que `franjas_seguidas` de las pruebas del motor: el receso ocupa un
 * `orden` propio y va después de la `$recesoTras`-ésima clase.
 */
final class GeneradorFranjas
{
    /**
     * @param  list<int>  $dias
     * @return list<array{dia: int, orden: int, inicio: string, fin: string, tipo: string}>
     */
    public function generar(array $dias, string $inicio, int $n, int $duracionMin, ?int $recesoTras = null, int $recesoMin = 30): array
    {
        $filas = [];

        foreach ($dias as $dia) {
            $t = Hora::aMinutos($inicio);
            $orden = 0;

            for ($k = 0; $k < $n; $k++) {
                if ($recesoTras !== null && $k === $recesoTras) {
                    $filas[] = $this->fila($dia, $orden++, $t, $t + $recesoMin, TipoFranja::Receso);
                    $t += $recesoMin;
                }

                $filas[] = $this->fila($dia, $orden++, $t, $t + $duracionMin, TipoFranja::Clase);
                $t += $duracionMin;
            }
        }

        return $filas;
    }

    /**
     * @return array{dia: int, orden: int, inicio: string, fin: string, tipo: string}
     */
    private function fila(int $dia, int $orden, int $inicio, int $fin, TipoFranja $tipo): array
    {
        return [
            'dia' => $dia,
            'orden' => $orden,
            'inicio' => Hora::deMinutos($inicio),
            'fin' => Hora::deMinutos($fin),
            'tipo' => $tipo->value,
        ];
    }
}
