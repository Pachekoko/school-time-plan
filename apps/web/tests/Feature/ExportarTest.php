<?php

namespace Tests\Feature;

use App\Domain\GeneradorFranjas;
use App\Enums\EstadoDisponibilidad;
use App\Excel\Hojas;
use App\Models\PlantillaHoraria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\Support\ActuaComoAdmin;
use Tests\Support\EscuelaDePrueba;
use Tests\TestCase;

class ExportarTest extends TestCase
{
    use ActuaComoAdmin, RefreshDatabase;

    private function leer(TestResponse $respuesta): Spreadsheet
    {
        $ruta = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($ruta, $respuesta->streamedContent());

        try {
            return IOFactory::load($ruta);
        } finally {
            unlink($ruta);
        }
    }

    /**
     * @return list<string>
     */
    private function fila(Worksheet $hoja, int $fila): array
    {
        $valores = $hoja->rangeToArray('A'.$fila.':'.$hoja->getHighestDataColumn().$fila, null, false, false)[0];

        return array_map(fn (mixed $v) => (string) $v, $valores);
    }

    /**
     * @return array<string, string> encabezado => valor de la fila
     */
    private function registro(Worksheet $hoja, int $fila): array
    {
        return array_combine($this->fila($hoja, 1), $this->fila($hoja, $fila));
    }

    /**
     * Filas con algún valor después del encabezado (las listas desplegables crean celdas vacías).
     */
    private function filasConDatos(Worksheet $hoja): int
    {
        $filas = 0;

        for ($fila = 2; $fila <= $hoja->getHighestRow(); $fila++) {
            if (array_filter($this->fila($hoja, $fila), fn (string $v) => $v !== '') !== []) {
                $filas++;
            }
        }

        return $filas;
    }

    private function buscar(Worksheet $hoja, string $columna, string $valor): int
    {
        for ($fila = 2; $fila <= $hoja->getHighestDataRow(); $fila++) {
            if ($this->registro($hoja, $fila)[$columna] === $valor) {
                return $fila;
            }
        }

        $this->fail("No hay fila con {$columna} = {$valor} en {$hoja->getTitle()}");
    }

    public function test_plantilla_vacia_tiene_hojas_y_encabezados_exactos(): void
    {
        $this->comoAdmin();

        $libro = $this->leer($this->get(route('exportar.plantilla'))->assertOk()->assertDownload('plantilla-horarios.xlsx'));

        $this->assertSame(['Instrucciones', ...array_keys(Hojas::COLUMNAS)], $libro->getSheetNames());
        $this->assertSame(['Planteles', 'Espacios', 'Plantillas', 'Materias', 'Grupos', 'Profesores', 'Disponibilidad', 'Carga'], array_keys(Hojas::COLUMNAS));
        $this->assertSame(['clave', 'nombre', 'contrato', 'horas_min', 'horas_max', 'max_dia', 'sin_huecos', 'planteles', 'materias'], Hojas::COLUMNAS['Profesores']);
        $this->assertSame(['clave', 'grupos', 'materia', 'profesor', 'vacante', 'sincronía', 'alumnos'], Hojas::COLUMNAS['Carga']);

        foreach (Hojas::COLUMNAS as $nombre => $columnas) {
            $hoja = $libro->getSheetByName($nombre);
            $this->assertSame($columnas, $this->fila($hoja, 1), $nombre);
            $this->assertSame(0, $this->filasConDatos($hoja), $nombre);
        }

        $this->assertStringContainsString('Disponibilidad', implode(' ', $this->fila($libro->getSheetByName('Instrucciones'), 8)));
    }

    public function test_listas_desplegables(): void
    {
        $this->comoAdmin();
        $libro = $this->leer($this->get(route('exportar.plantilla')));
        $lista = fn (string $hoja, string $celda) => $libro->getSheetByName($hoja)->getCell($celda)->getDataValidation();

        $this->assertSame(DataValidation::TYPE_LIST, $lista('Espacios', 'D2')->getType());
        $this->assertSame('"aula,laboratorio,computo,cancha,cocina,taller"', $lista('Espacios', 'D2')->getFormula1());
        $this->assertSame('"Lun,Mar,Mié,Jue,Vie,Sáb,Dom"', $lista('Plantillas', 'B2')->getFormula1());
        $this->assertSame('"clase,receso"', $lista('Plantillas', 'F2')->getFormula1());
        $this->assertSame('"aula,laboratorio,computo,cancha,cocina,taller"', $lista('Materias', 'G2')->getFormula1());
        $this->assertSame('"asignatura,medio_tiempo,tiempo_completo"', $lista('Profesores', 'C2')->getFormula1());
        $this->assertSame('"S,N"', $lista('Profesores', 'G2')->getFormula1());
        $this->assertSame('"S,N"', $lista('Carga', 'E2')->getFormula1());
    }

    public function test_exporta_datos(): void
    {
        $e = EscuelaDePrueba::crear();
        $this->actingAs($e['admin']);

        $libro = $this->leer($this->get(route('exportar.datos'))->assertOk()
            ->assertDownload('datos-prepa-de-prueba-'.now()->toDateString().'.xlsx'));

        $materias = $libro->getSheetByName('Materias');
        $this->assertSame('2 | 1+1', $this->registro($materias, $this->buscar($materias, 'clave', 'QUI1'))['patrones']);
        $this->assertSame('', $this->registro($materias, $this->buscar($materias, 'clave', 'MAT1'))['patrones']);
        $this->assertSame('BGE', $this->registro($materias, $this->buscar($materias, 'clave', 'MAT1'))['plan']);

        $plantillas = $libro->getSheetByName('Plantillas');
        $this->assertSame(20, $this->filasConDatos($plantillas));
        $this->assertSame(['MAT', 'Lun', '0', '07:00', '07:50', 'clase'], $this->fila($plantillas, 2));
        $this->assertSame(DataType::TYPE_STRING, $plantillas->getCell('D2')->getDataType());

        $grupos = $libro->getSheetByName('Grupos');
        $this->assertSame(['1A', 'CEN', 'MAT', 'BGE', '1', '30'], $this->fila($grupos, $this->buscar($grupos, 'clave', '1A')));

        $profesores = $libro->getSheetByName('Profesores');
        $ana = $this->registro($profesores, $this->buscar($profesores, 'clave', 'P1'));
        $this->assertSame(['tiempo_completo', 'S', 'CEN', 'ING1, MAT1, QUI1'], [$ana['contrato'], $ana['sin_huecos'], $ana['planteles'], $ana['materias']]);

        $carga = $libro->getSheetByName('Carga');
        $this->assertSame(4, $this->filasConDatos($carga));
        $quimica = $this->registro($carga, $this->buscar($carga, 'clave', '1A-QUI1'));
        $this->assertSame(['1A', 'QUI1', '', 'S'], [$quimica['grupos'], $quimica['materia'], $quimica['profesor'], $quimica['vacante']]);
        $ingles = $this->registro($carga, $this->buscar($carga, 'clave', '1A-ING1'));
        $this->assertSame(['P2', 'N', 'ING'], [$ingles['profesor'], $ingles['vacante'], $ingles['sincronía']]);

        $disponibilidad = $libro->getSheetByName('Disponibilidad');
        $encabezados = $this->fila($disponibilidad, 1);
        $this->assertCount(22, $encabezados);
        $this->assertSame(['clave_profesor', 'plantilla', 'Lun 07:00', 'Lun 07:50'], array_slice($encabezados, 0, 4));
        $this->assertSame('Vie 09:30', end($encabezados));
        $fila = $this->registro($disponibilidad, $this->buscar($disponibilidad, 'clave_profesor', 'P1'));
        $this->assertSame(['MAT', 'N', 'P', ''], [$fila['plantilla'], $fila['Lun 07:00'], $fila['Mar 07:50'], $fila['Mié 07:00']]);
        $this->assertSame('"D,P,N"', $disponibilidad->getCell('C2')->getDataValidation()->getFormula1());
    }

    public function test_celdas_que_no_existen_en_la_plantilla_van_grises(): void
    {
        $e = EscuelaDePrueba::crear();
        $this->actingAs($e['admin']);
        $sabado = PlantillaHoraria::factory()->create(['clave' => 'SAB', 'nombre' => 'Sabatino']);
        $sabado->franjas()->createMany((new GeneradorFranjas)->generar([5], '08:00', 2, 60));
        $e['profesores']['P2']->disponibilidades()->create([
            'franja_id' => $sabado->franjas()->where('orden', 1)->firstOrFail()->id, 'estado' => EstadoDisponibilidad::NoDisponible,
        ]);

        $disponibilidad = $this->leer($this->get(route('exportar.datos')))->getSheetByName('Disponibilidad');
        $encabezados = $this->fila($disponibilidad, 1);
        $columna = fn (string $encabezado) => Coordinate::stringFromColumnIndex(array_search($encabezado, $encabezados, true) + 1);
        $filaDe = function (string $profesor, string $plantilla) use ($disponibilidad): int {
            for ($fila = 2; $fila <= $disponibilidad->getHighestDataRow(); $fila++) {
                $registro = $this->registro($disponibilidad, $fila);
                if ($registro['clave_profesor'] === $profesor && $registro['plantilla'] === $plantilla) {
                    return $fila;
                }
            }
            $this->fail("Falta {$profesor}/{$plantilla}");
        };
        $gris = fn (string $celda) => $disponibilidad->getStyle($celda)->getFill()->getFillType() === Fill::FILL_SOLID;

        $this->assertSame('Sáb 09:00', end($encabezados));
        $this->assertTrue($gris($columna('Sáb 08:00').$filaDe('P1', 'MAT')));
        $this->assertFalse($gris($columna('Lun 07:00').$filaDe('P1', 'MAT')));
        $this->assertTrue($gris($columna('Lun 07:00').$filaDe('P2', 'SAB')));
        $this->assertSame('N', (string) $disponibilidad->getCell($columna('Sáb 09:00').$filaDe('P2', 'SAB'))->getValue());
    }

    public function test_profesor_no_exporta(): void
    {
        $this->actingAs(User::factory()->profesor()->create())->get(route('exportar.datos'))->assertForbidden();
    }
}
