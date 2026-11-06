<?php

namespace Tests\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Un libro con el layout de la carga horaria de UTEG y nombres ficticios: título en la fila 1,
 * encabezados en la 2 (GRUPO dos veces, MATERIA, HORAS, N° docente, Docente) y datos desde
 * la 3; además una hoja DOCENTES con notas libres que se ignora.
 */
final class LibroUteg
{
    /**
     * @param  list<array{grupo: string, materia: string, horas: int|float|string, a?: string|null, numero?: string|int|null, docente?: string|null}>  $filas
     * @param  list<string>|null  $encabezados  los de la fila 2, de A a H
     */
    public static function crear(array $filas, ?array $encabezados = null): string
    {
        $libro = new Spreadsheet;
        $hoja = $libro->getActiveSheet()->setTitle('HORARIOS');
        $hoja->setCellValue('A1', 'CARGA HORARIA CICLO 27-A');

        foreach ($encabezados ?? ['GRUPO', 'GRUPO', 'MATERIA', 'HORAS', 'TURNO', 'AULA', 'N° docente', 'Docente'] as $i => $texto) {
            $hoja->setCellValue([$i + 1, 2], $texto);
        }

        foreach ($filas as $i => $fila) {
            $n = $i + 3;
            $hoja->setCellValue("A{$n}", $fila['a'] ?? $fila['grupo']);
            $hoja->setCellValue("B{$n}", $fila['grupo']);
            $hoja->setCellValue("C{$n}", $fila['materia']);
            $hoja->setCellValue("D{$n}", $fila['horas']);
            $hoja->setCellValue("E{$n}", 'MAT');
            $hoja->setCellValue("G{$n}", $fila['numero'] ?? null);
            $hoja->setCellValue("H{$n}", $fila['docente'] ?? null);
        }

        $docentes = $libro->createSheet()->setTitle('DOCENTES');
        $docentes->fromArray([['DOCENTE', 'HORAS'], ['Ana Pérez', '10 DE GESTORIA'], ['Beto Ruiz', 'PP']]);

        $ruta = tempnam(sys_get_temp_dir(), 'uteg').'.xlsx';
        (new Xlsx($libro))->save($ruta);

        return $ruta;
    }

    /**
     * Dos grupos de 1.º, uno de 3.º y uno de los 09 (6.º), dos docentes y una vacante.
     *
     * @return list<array{grupo: string, materia: string, horas: int, numero?: string, docente?: string}>
     */
    public static function filas(): array
    {
        return [
            ['grupo' => '01A', 'materia' => 'Matemáticas I', 'horas' => 3, 'numero' => '101', 'docente' => 'Ana Pérez'],
            ['grupo' => '01A', 'materia' => 'Comprension Lectora ||', 'horas' => 2, 'numero' => '102', 'docente' => 'Beto Ruiz'],
            ['grupo' => '01B', 'materia' => 'Matemáticas  I ', 'horas' => 3, 'numero' => '101', 'docente' => 'Ana Pérez'],
            ['grupo' => '01B', 'materia' => 'Comprension Lectora ||', 'horas' => 2, 'docente' => 'GENERICO'],
            ['grupo' => '03A', 'materia' => 'Física I', 'horas' => 4, 'numero' => '101', 'docente' => 'Ana Pérez'],
            ['grupo' => '0901', 'materia' => 'Lengua Extranjera VI', 'horas' => 2, 'numero' => '102', 'docente' => 'Beto  Ruiz'],
        ];
    }
}
