<?php

namespace App\Excel;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Lee un .xlsx hoja por hoja: la primera fila son los encabezados y cada fila con algún
 * valor se vuelve un arreglo con llaves de encabezado normalizado («Dirección» → direccion).
 */
final class LectorLibro
{
    /**
     * @return array<string, array{encabezados: list<string>, filas: list<array{fila: int, celdas: array<string, mixed>}>}>
     */
    public function leer(string $ruta): array
    {
        $lector = IOFactory::createReader('Xlsx');
        $lector->setReadDataOnly(true);
        $libro = $lector->load($ruta);
        $hojas = [];

        foreach ($libro->getWorksheetIterator() as $hoja) {
            $ultimaColumna = $hoja->getHighestDataColumn();
            $encabezados = array_map(
                fn (mixed $valor) => Normalizador::texto($valor) ?? '',
                $hoja->rangeToArray("A1:{$ultimaColumna}1", null, true, false)[0],
            );
            $llaves = array_map(fn (string $e) => $e === '' ? '' : Normalizador::encabezado($e), $encabezados);
            $filas = [];

            for ($n = 2; $n <= $hoja->getHighestDataRow(); $n++) {
                $celdas = [];

                foreach ($hoja->rangeToArray("A{$n}:{$ultimaColumna}{$n}", null, true, false)[0] as $i => $valor) {
                    $llave = $llaves[$i] ?? '';

                    if ($llave !== '' && ! array_key_exists($llave, $celdas)) {
                        $celdas[$llave] = $valor;
                    }
                }

                if (array_filter($celdas, fn (mixed $valor) => Normalizador::texto($valor) !== null) !== []) {
                    $filas[] = ['fila' => $n, 'celdas' => $celdas];
                }
            }

            $hojas[$hoja->getTitle()] = [
                'encabezados' => array_values(array_filter($encabezados, fn (string $e) => $e !== '')),
                'filas' => $filas,
            ];
        }

        $libro->disconnectWorksheets();

        return $hojas;
    }
}
