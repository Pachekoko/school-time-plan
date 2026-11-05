<?php

namespace Tests\Feature;

use App\Enums\Contrato;
use App\Enums\TipoEspacio;
use App\Enums\TipoFranja;
use App\Excel\AplicadorImportacion;
use App\Excel\ExportadorPlantilla;
use App\Excel\ImportadorPlantilla;
use App\Excel\LectorLibro;
use App\Models\Ciclo;
use App\Models\Curso;
use App\Models\Disponibilidad;
use App\Models\Espacio;
use App\Models\Franja;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Plantel;
use App\Models\Profesor;
use App\Models\User;
use App\Support\EscuelaActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\ActuaComoAdmin;
use Tests\Support\EscuelaDePrueba;
use Tests\TestCase;

class ImportarPlantillaTest extends TestCase
{
    use ActuaComoAdmin, RefreshDatabase;

    /** @var list<string> */
    private array $temporales = [];

    protected function tearDown(): void
    {
        array_map(unlink(...), array_filter($this->temporales, file_exists(...)));

        parent::tearDown();
    }

    private function guardar(Spreadsheet $libro): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new Xlsx($libro))->save($ruta);

        return $this->temporales[] = $ruta;
    }

    /**
     * Un libro con estas hojas y filas (la primera fila de cada hoja es el encabezado).
     *
     * @param  array<string, list<list<mixed>>>  $hojas
     */
    private function libro(array $hojas): string
    {
        $libro = new Spreadsheet;
        $libro->removeSheetByIndex(0);

        foreach ($hojas as $nombre => $filas) {
            $hoja = $libro->createSheet()->setTitle($nombre);
            foreach ($filas as $i => $valores) {
                foreach ($valores as $j => $valor) {
                    if ($valor !== null) {
                        $hoja->setCellValue([$j + 1, $i + 1], $valor);
                    }
                }
            }
        }

        return $this->guardar($libro);
    }

    /**
     * Los datos de la escuela de prueba exportados a un archivo.
     */
    private function exportado(): string
    {
        return $this->guardar(app(ExportadorPlantilla::class)->libro(true));
    }

    private function subir(string $ruta): TestResponse
    {
        return $this->post(route('importar.store'), [
            'archivo' => new UploadedFile($ruta, 'datos.xlsx', null, null, true),
            'formato' => 'plantilla',
        ]);
    }

    private function confirmar(TestResponse $vistaPrevia): TestResponse
    {
        return $this->post(route('importar.confirmar', $vistaPrevia->viewData('page')['props']['uuid']), ['formato' => 'plantilla']);
    }

    /**
     * @return array<string, array{crear: int, actualizar: int, sin_cambios: int}>
     */
    private function conteos(TestResponse $vistaPrevia): array
    {
        return $vistaPrevia->viewData('page')['props']['conteos'];
    }

    /**
     * Un admin de una escuela nueva con ciclo actual.
     */
    private function escuelaNueva(): User
    {
        $admin = $this->comoAdmin();
        $this->conCicloActual();

        return $admin;
    }

    public function test_importa_lo_exportado_por_otra_escuela(): void
    {
        Storage::fake();
        EscuelaDePrueba::crear();
        $archivo = $this->exportado();
        $this->escuelaNueva();

        $vistaPrevia = $this->subir($archivo)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('escuela/importar/VistaPrevia')->where('tieneErrores', false));

        $conteos = $this->conteos($vistaPrevia);
        $this->assertSame(['crear' => 2, 'actualizar' => 0, 'sin_cambios' => 0], $conteos['Planteles']);
        $this->assertSame(4, $conteos['Espacios']['crear']);
        $this->assertSame(20, $conteos['Plantillas']['crear']);
        $this->assertSame(3, $conteos['Materias']['crear']);
        $this->assertSame(2, $conteos['Grupos']['crear']);
        $this->assertSame(2, $conteos['Profesores']['crear']);
        $this->assertSame(['crear' => 1, 'actualizar' => 0, 'sin_cambios' => 1], $conteos['Disponibilidad']);
        $this->assertSame(4, $conteos['Carga']['crear']);

        $this->confirmar($vistaPrevia)->assertRedirect(route('importar.index'))
            ->assertInertiaFlash('toast.message', 'Importación aplicada: 38 registros nuevos y 0 actualizados.');

        $this->assertSame([2, 4, 20, 3, 2, 2, 2, 4], [
            Plantel::count(), Espacio::count(), Franja::count(), Materia::count(),
            Grupo::count(), Profesor::count(), Disponibilidad::count(), Curso::count(),
        ]);
        $ana = Profesor::firstWhere('clave', 'P1');
        $this->assertEqualsCanonicalizing(['ING1', 'MAT1', 'QUI1'], $ana->materias()->pluck('clave')->all());
        $this->assertTrue($ana->sin_huecos);
        $this->assertSame([[2], [1, 1]], Materia::firstWhere('clave', 'QUI1')->patrones);
        $ingles = Curso::firstWhere('clave', '1A-ING1');
        $this->assertSame('ING', $ingles->sincronia?->clave);
        $this->assertTrue(Curso::firstWhere('clave', '1A-QUI1')->vacante);
        $this->assertSame([], Storage::allFiles('importaciones'));
    }

    public function test_ida_y_vuelta_sin_cambios(): void
    {
        Storage::fake();
        $e = EscuelaDePrueba::crear();
        $this->actingAs($e['admin']);

        $conteos = $this->conteos($this->subir($this->exportado())->assertOk());

        foreach ($conteos as $hoja => $conteo) {
            $this->assertSame(0, $conteo['crear'] + $conteo['actualizar'], "{$hoja}: ".json_encode($conteo));
        }
        $this->assertSame(20, $conteos['Plantillas']['sin_cambios']);
        $this->assertSame(4, $conteos['Carga']['sin_cambios']);
    }

    public function test_reimportar_no_duplica(): void
    {
        Storage::fake();
        EscuelaDePrueba::crear();
        $archivo = $this->exportado();
        $this->escuelaNueva();

        $this->confirmar($this->subir($archivo))->assertRedirect();
        $segunda = $this->subir($archivo);
        $this->confirmar($segunda)->assertRedirect();

        $this->assertSame(0, array_sum(array_column($this->conteos($segunda), 'crear')));
        $this->assertSame([2, 3, 2, 4], [Plantel::count(), Materia::count(), Profesor::count(), Curso::count()]);
    }

    public function test_error_por_fila_y_columna_y_no_aplica_nada(): void
    {
        Storage::fake();
        $this->escuelaNueva();
        $archivo = $this->libro([
            'Planteles' => [['clave', 'nombre', 'dirección'], ['CEN', 'Centro', null]],
            'Materias' => [['clave', 'plan', 'nombre', 'semestre', 'horas_semana', 'patrones', 'tipo_espacio'], ['MAT1', 'BGE', 'Matemáticas I', 1, 3, null, 'aula']],
            'Profesores' => [
                ['clave', 'nombre', 'contrato', 'horas_min', 'horas_max', 'max_dia', 'sin_huecos', 'planteles', 'materias'],
                ['P1', 'Ana', 'asignatura', 0, 20, 6, 'N', 'CEN', 'MAT1'],
                ['P2', 'Beto', 'asignatura', 0, 20, 6, 'N', 'CEN', 'MAT1, MAT-3'],
            ],
        ]);

        $vistaPrevia = $this->subir($archivo)->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tieneErrores', true)
                ->where('errores.0', ['hoja' => 'Profesores', 'fila' => 3, 'columna' => 'materias', 'mensaje' => 'la materia MAT-3 no existe']));

        $this->confirmar($vistaPrevia)->assertSessionHasErrors('importacion');
        $this->assertSame(0, Plantel::count());
    }

    public function test_todo_o_nada(): void
    {
        Storage::fake();
        $this->escuelaNueva();
        $libro = (new LectorLibro)->leer($this->libro([
            'Planteles' => [['clave', 'nombre', 'dirección'], ['CEN', 'Centro', null]],
            'Carga' => [['clave', 'grupos', 'materia', 'profesor', 'vacante', 'sincronía', 'alumnos'], [null, '1A', 'NO-EXISTE', null, 'N', null, null]],
        ]));
        $plan = app(ImportadorPlantilla::class)->revisar($libro, Ciclo::firstOrFail());

        $this->assertTrue($plan->tieneErrores());

        try {
            app(AplicadorImportacion::class)->aplicar($plan);
            $this->fail('No debió aplicarse');
        } catch (LogicException) {
        }

        $this->assertSame(0, Plantel::count());
    }

    public function test_celdas_reales_de_excel_en_un_libro(): void
    {
        Storage::fake();
        $this->escuelaNueva();
        $archivo = $this->libro([
            'Planteles' => [['Clave', 'Nombre', 'Dirección', 'notas'], ['CEN ', '  Plantel   Centro ', null, 'ver'], [null, null, null, null], ['NTE', 'Norte', null, null]],
            'Espacios' => [['clave', 'plantel', 'nombre', 'tipo', 'capacidad'], ['A1', 'CEN', 'Aula 1', 'Aula', 40.0]],
            'Plantillas' => [
                ['plantilla', 'día', 'orden', 'inicio', 'fin', 'tipo'],
                ['MAT', 'Lunes', 0.0, 0.2916666667, 0.3263888889, null],
                ['MAT', 'LUN', '1', '7:50', '8:40', 'Clase'],
                ['MAT', 'Mié', 0, '07:00', '07:50', 'clase'],
            ],
        ]);

        $this->subir($archivo)->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tieneErrores', false)
                ->where('avisos.0.mensaje', 'columna «notas» ignorada'))
            ->tap(fn (TestResponse $r) => $this->confirmar($r)->assertRedirect());

        $this->assertSame('Plantel Centro', Plantel::firstWhere('clave', 'CEN')->nombre);
        $this->assertSame(2, Plantel::count());
        $this->assertSame(40, Espacio::firstWhere('clave', 'A1')->capacidad);
        $this->assertSame([['0-0', '07:00', '07:50'], ['0-1', '07:50', '08:40'], ['2-0', '07:00', '07:50']],
            Franja::query()->orderBy('dia')->orderBy('orden')->get()->map(fn ($f) => ["{$f->dia}-{$f->orden}", $f->inicio, $f->fin])->all());
    }

    public function test_valores_invalidos_dan_error_por_celda(): void
    {
        Storage::fake();
        $this->escuelaNueva();
        $archivo = $this->libro([
            'Espacios' => [['clave', 'plantel', 'nombre', 'tipo', 'capacidad'], ['A1', 'XX', 'Aula 1', 'alberca', 2.5]],
            'Plantillas' => [['plantilla', 'día', 'orden', 'inicio', 'fin', 'tipo'], ['MAT', 'marzo', 0, '07:00', '07:50', 'clase'], ['MAT', 'Lun', 1, '08:00', '07:50', 'clase']],
            'Materias' => [['clave', 'plan', 'nombre', 'semestre', 'horas_semana', 'patrones', 'tipo_espacio'], ['MAT1', 'BGE', 'Matemáticas', 1, 4, '2+1', 'aula']],
        ]);

        $errores = $this->subir($archivo)->assertOk()->viewData('page')['props']['errores'];
        $mensajes = array_map(fn (array $e) => "{$e['hoja']}:{$e['fila']}:{$e['columna']}: {$e['mensaje']}", $errores);

        $this->assertContains('Espacios:2:plantel: el plantel XX no existe', $mensajes);
        $this->assertContains('Espacios:2:tipo: «alberca» no es un tipo de espacio', $mensajes);
        $this->assertContains('Espacios:2:capacidad: «2.5» no es un número entero', $mensajes);
        $this->assertContains('Plantillas:2:día: «marzo» no es un día; usa Lun, Mar, Mié, Jue, Vie, Sáb, Dom', $mensajes);
        $this->assertContains('Plantillas:3:fin: el fin debe ser posterior al inicio', $mensajes);
        $this->assertContains('Materias:2:patrones: El patrón 2+1 suma 3 y horas_semana es 4.', $mensajes);
    }

    public function test_mayor_a_5mb_o_no_xlsx_da_422(): void
    {
        $this->escuelaNueva();

        $this->post(route('importar.store'), ['archivo' => UploadedFile::fake()->create('datos.txt', 10, 'text/plain'), 'formato' => 'plantilla'])
            ->assertSessionHasErrors('archivo');
        $this->post(route('importar.store'), ['archivo' => UploadedFile::fake()->create('grande.xlsx', 6000), 'formato' => 'plantilla'])
            ->assertSessionHasErrors('archivo');
    }

    public function test_no_borra_lo_que_no_viene(): void
    {
        Storage::fake();
        $this->escuelaNueva();
        Plantel::factory()->create(['clave' => 'NTE', 'nombre' => 'Norte']);
        $archivo = $this->libro(['Planteles' => [['clave', 'nombre', 'dirección'], ['CEN', 'Centro', null]]]);

        $this->confirmar($this->subir($archivo))->assertRedirect();

        $this->assertEqualsCanonicalizing(['CEN', 'NTE'], Plantel::pluck('clave')->all());
    }

    public function test_las_columnas_opcionales_que_no_vienen_se_quedan_como_estan(): void
    {
        Storage::fake();
        $e = EscuelaDePrueba::crear();
        $this->actingAs($e['admin']);
        $e['planteles']['CEN']->update(['direccion' => 'Av. Juárez 10']);
        $e['plantilla']->franjas()->where('dia', 0)->where('orden', 3)->update(['tipo' => TipoFranja::Receso]);
        $e['cursos']['1A-ING1']->update(['alumnos' => 25]);
        $archivo = $this->libro([
            'Planteles' => [['clave', 'nombre'], ['CEN', 'Plantel Centro']],
            'Plantillas' => [['plantilla', 'día', 'orden', 'inicio', 'fin'], ['MAT', 'Lun', 3, '09:30', '10:20']],
            'Materias' => [['clave', 'plan', 'nombre', 'semestre', 'horas_semana'], ['QUI1', 'BGE', 'Química I', 1, 2]],
            'Profesores' => [['clave', 'nombre', 'horas_max', 'max_dia'], ['P1', 'Ana López', 20, 6], ['P3', 'Caro Díaz', 10, 4]],
            'Carga' => [['clave', 'grupos', 'materia'], ['1A-ING1', '1A', 'ING1']],
        ]);

        $vistaPrevia = $this->subir($archivo)->assertOk()->assertInertia(fn (Assert $page) => $page->where('tieneErrores', false));

        foreach ($this->conteos($vistaPrevia) as $hoja => $conteo) {
            $this->assertSame($hoja === 'Profesores' ? 1 : 0, $conteo['crear'], "{$hoja}: ".json_encode($conteo));
            $this->assertSame(0, $conteo['actualizar'], "{$hoja}: ".json_encode($conteo));
        }

        $this->confirmar($vistaPrevia)->assertRedirect();
        $ana = Profesor::firstWhere('clave', 'P1');
        $this->assertSame([Contrato::TiempoCompleto, true], [$ana->contrato, $ana->sin_huecos]);
        $this->assertEqualsCanonicalizing(['ING1', 'MAT1', 'QUI1'], $ana->materias()->pluck('clave')->all());
        $this->assertSame(['CEN'], $ana->planteles()->pluck('clave')->all());
        $caro = Profesor::firstWhere('clave', 'P3');
        $this->assertSame([Contrato::Asignatura, 0, false, 0], [$caro->contrato, $caro->horas_min, $caro->sin_huecos, $caro->materias()->count()]);
        $quimica = Materia::firstWhere('clave', 'QUI1');
        $this->assertSame([[[2], [1, 1]], TipoEspacio::Laboratorio], [$quimica->patrones, $quimica->tipo_espacio]);
        $ingles = Curso::firstWhere('clave', '1A-ING1');
        $this->assertSame(['P2', 'ING', 25, false], [$ingles->profesor?->clave, $ingles->sincronia?->clave, $ingles->alumnos, $ingles->vacante]);
        $this->assertSame('Av. Juárez 10', Plantel::firstWhere('clave', 'CEN')->direccion);
        $this->assertSame(TipoFranja::Receso, $e['plantilla']->franjas()->where('dia', 0)->where('orden', 3)->firstOrFail()->tipo);
    }

    public function test_carga_sin_claves_reusa_los_cursos_iguales_que_ya_hay(): void
    {
        Storage::fake();
        $e = EscuelaDePrueba::crear();
        $this->actingAs($e['admin']);
        $segundo = Curso::factory()->for($e['ciclo'])->for($e['materias']['ING1'])->create(['clave' => '1A-ING1-2']);
        $segundo->grupos()->attach($e['grupos']['1A']);
        $columnas = ['clave', 'grupos', 'materia'];

        $dosSinClave = $this->subir($this->libro(['Carga' => [$columnas, [null, '1A', 'ING1'], [null, '1A', 'ING1']]]))->assertOk();
        $unaConClaveDespues = $this->subir($this->libro(['Carga' => [$columnas, [null, '1A', 'ING1'], ['1A-ING1', '1A', 'ING1']]]))->assertOk();

        $this->assertSame(['crear' => 0, 'actualizar' => 0, 'sin_cambios' => 2], $this->conteos($dosSinClave)['Carga']);
        $this->assertSame([], $unaConClaveDespues->viewData('page')['props']['errores']);
        $this->assertSame(['crear' => 0, 'actualizar' => 0, 'sin_cambios' => 2], $this->conteos($unaConClaveDespues)['Carga']);
    }

    public function test_mover_un_grupo_que_comparte_cursos_da_error(): void
    {
        Storage::fake();
        $e = EscuelaDePrueba::crear();
        $this->actingAs($e['admin']);
        $compartido = Curso::factory()->for($e['ciclo'])->for($e['materias']['ING1'])->create(['clave' => '1AB-ING1']);
        $compartido->grupos()->attach([$e['grupos']['1A']->id, $e['grupos']['1B']->id]);
        $plantillas = [['plantilla', 'día', 'orden', 'inicio', 'fin'], ['VESP', 'Lun', 0, '13:00', '13:50']];
        $columnas = ['clave', 'plantel', 'plantilla', 'plan', 'semestre', 'alumnos'];

        $solo1B = $this->subir($this->libro(['Plantillas' => $plantillas, 'Grupos' => [$columnas, ['1B', 'CEN', 'VESP', 'BGE', 1, 28]]]))->assertOk();
        $losDos = $this->subir($this->libro(['Plantillas' => $plantillas, 'Grupos' => [$columnas, ['1A', 'CEN', 'VESP', 'BGE', 1, 30], ['1B', 'CEN', 'VESP', 'BGE', 1, 28]]]))->assertOk();

        $this->assertSame(
            [['hoja' => 'Grupos', 'fila' => 2, 'columna' => 'plantilla', 'mensaje' => 'No se puede mover 1B: comparte el curso 1AB-ING1 con otros grupos.']],
            $solo1B->viewData('page')['props']['errores'],
        );
        $this->assertSame([], $losDos->viewData('page')['props']['errores']);
    }

    public function test_avisa_materias_casi_iguales(): void
    {
        Storage::fake();
        $this->escuelaNueva();
        $archivo = $this->libro(['Materias' => [
            ['clave', 'plan', 'nombre', 'semestre', 'horas_semana', 'patrones', 'tipo_espacio'],
            ['QUI1', 'BGE', 'Química I', 1, 3, null, 'laboratorio'],
            ['QUI2', 'BGE', 'quimica i', 1, 3, null, 'laboratorio'],
        ]]);

        $avisos = array_column($this->subir($archivo)->assertOk()->viewData('page')['props']['avisos'], 'mensaje');

        $this->assertContains('las materias QUI1 («Química I») y QUI2 («quimica i») solo difieren en mayúsculas o acentos', $avisos);
        $this->assertContains('se creará el plan BGE', $avisos);
    }

    public function test_uuid_de_otra_escuela_da_404(): void
    {
        Storage::fake();
        $this->escuelaNueva();
        $uuid = $this->subir($this->libro(['Planteles' => [['clave', 'nombre', 'dirección'], ['CEN', 'Centro', null]]]))
            ->viewData('page')['props']['uuid'];

        $this->escuelaNueva();
        $this->post(route('importar.confirmar', $uuid), ['formato' => 'plantilla'])->assertNotFound();
        $this->post(route('importar.confirmar', '../../etc'), ['formato' => 'plantilla'])->assertNotFound();

        $this->assertSame(0, Plantel::withoutGlobalScope('escuela')->count());
        app(EscuelaActual::class)->establecer(null);
    }
}
