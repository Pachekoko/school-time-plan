<?php

namespace Tests\Feature;

use App\Domain\GeneradorFranjas;
use App\Models\Curso;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\PlanEstudios;
use App\Models\Plantel;
use App\Models\PlantillaHoraria;
use App\Models\Profesor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ActuaComoAdmin;
use Tests\Support\LibroUteg;
use Tests\TestCase;

class ImportarCargaUtegTest extends TestCase
{
    use ActuaComoAdmin, RefreshDatabase;

    /** @var array<string, int> */
    private array $opciones;

    private PlantillaHoraria $de1y2;

    private PlantillaHoraria $de3a6;

    private function preparar(): void
    {
        Storage::fake();
        $this->comoAdmin();
        $this->conCicloActual();
        $this->de1y2 = PlantillaHoraria::factory()->create(['clave' => 'P12', 'nombre' => '1.º y 2.º']);
        $this->de1y2->franjas()->createMany((new GeneradorFranjas)->generar([0, 1, 2, 3, 4], '08:00', 6, 60));
        $this->de3a6 = PlantillaHoraria::factory()->create(['clave' => 'P36', 'nombre' => '3.º a 6.º']);
        $this->de3a6->franjas()->createMany((new GeneradorFranjas)->generar([0, 1, 2, 3, 4], '08:00', 7, 60));
        $this->opciones = [
            'plantel_id' => Plantel::factory()->create(['clave' => 'CEN'])->id,
            'plantilla_1y2_id' => $this->de1y2->id,
            'plantilla_3a6_id' => $this->de3a6->id,
            'plan_id' => PlanEstudios::factory()->create(['clave' => 'BGE'])->id,
        ];
    }

    /**
     * @param  list<array<string, mixed>>|null  $filas
     */
    private function subir(?array $filas = null, ?array $encabezados = null, array $opciones = []): TestResponse
    {
        $ruta = LibroUteg::crear($filas ?? LibroUteg::filas(), $encabezados);

        return $this->post(route('importar.store'), [
            'archivo' => new UploadedFile($ruta, 'carga.xlsx', null, null, true),
            'formato' => 'uteg',
            ...$this->opciones,
            ...$opciones,
        ]);
    }

    private function confirmar(TestResponse $vistaPrevia): TestResponse
    {
        return $this->post(route('importar.confirmar', $vistaPrevia->viewData('page')['props']['uuid']), ['formato' => 'uteg', ...$this->opciones]);
    }

    /**
     * @return array<string, array{crear: int, actualizar: int, sin_cambios: int}>
     */
    private function conteos(TestResponse $vistaPrevia): array
    {
        return $vistaPrevia->viewData('page')['props']['conteos'];
    }

    public function test_lee_el_formato_uteg(): void
    {
        $this->preparar();

        $vistaPrevia = $this->subir()->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('escuela/importar/VistaPrevia')->where('tieneErrores', false));

        $conteos = $this->conteos($vistaPrevia);
        $this->assertSame(4, $conteos['Grupos']['crear']);
        $this->assertSame(4, $conteos['Materias']['crear']);
        $this->assertSame(2, $conteos['Profesores']['crear']);
        $this->assertSame(6, $conteos['Carga']['crear']);

        $this->confirmar($vistaPrevia)->assertRedirect(route('importar.index'));

        $this->assertSame([4, 4, 2, 6], [Grupo::count(), Materia::count(), Profesor::count(), Curso::count()]);
        $mate = Materia::firstWhere('clave', 'MATEMATICAS-I-3H');
        $this->assertSame(['Matemáticas I', 1, 3, null, 'BGE'], [$mate->nombre, $mate->semestre, $mate->horas_semana, $mate->patrones, $mate->plan->clave]);
        $this->assertSame($this->de1y2->id, Grupo::firstWhere('clave', '01A')->plantilla_id);
        $this->assertSame($this->de3a6->id, Grupo::firstWhere('clave', '03A')->plantilla_id);
        $this->assertSame(30, Grupo::firstWhere('clave', '01A')->alumnos);

        $ana = Profesor::firstWhere('clave', 'ANA-PEREZ');
        $this->assertSame([0, 10, 7], [$ana->horas_min, $ana->horas_max, $ana->max_dia]);
        $this->assertSame(['CEN'], $ana->planteles()->pluck('clave')->all());
        $this->assertEqualsCanonicalizing(['MATEMATICAS-I-3H', 'FISICA-I-4H'], $ana->materias()->pluck('clave')->all());
        $this->assertSame(2, Curso::query()->where('profesor_id', $ana->id)->whereHas('materia', fn ($q) => $q->where('clave', 'MATEMATICAS-I-3H'))->count());
    }

    public function test_generico_es_vacante(): void
    {
        $this->preparar();

        $this->confirmar($this->subir())->assertRedirect();

        $vacante = Curso::query()->whereHas('grupos', fn ($q) => $q->where('clave', '01B'))
            ->whereHas('materia', fn ($q) => $q->where('clave', 'COMPRENSION-LECTORA-II-2H'))->sole();
        $this->assertTrue($vacante->vacante);
        $this->assertNull($vacante->profesor_id);
        $this->assertNull(Profesor::firstWhere('clave', 'GENERICO'));
    }

    public function test_avisos_del_archivo_real(): void
    {
        $this->preparar();

        $vistaPrevia = $this->subir([
            ['grupo' => '01A', 'materia' => 'Tutoria de trayectoria||', 'horas' => 1, 'numero' => '101', 'docente' => 'Ana Pérez'],
            ['grupo' => '01A', 'materia' => 'Matemáticas I', 'horas' => 3, 'numero' => '101', 'docente' => 'Carla Díaz'],
            ['grupo' => '01A', 'materia' => 'Química I', 'horas' => 3, 'docente' => 'Beto Ruiz'],
            ['grupo' => '01B', 'a' => '01BB', 'materia' => 'Química I', 'horas' => 3, 'numero' => '102', 'docente' => 'Beto Ruiz'],
        ])->assertOk();

        $avisos = array_column($vistaPrevia->viewData('page')['props']['avisos'], 'mensaje');
        $this->assertContains('El N° docente 101 aparece con dos nombres: Ana Pérez y Carla Díaz.', $avisos);
        $this->assertContains('Fila 5: sin N° docente.', $avisos);
        $this->assertContains('Fila 6: la columna A (01BB) no coincide con la B (01B); se usa la B.', $avisos);

        $this->confirmar($vistaPrevia)->assertRedirect();
        $this->assertSame('Tutoria de trayectoria II', Materia::firstWhere('clave', 'TUTORIA-DE-TRAYECTORIA-II-1H')?->nombre);
    }

    public function test_semestre_09_es_sexto(): void
    {
        $this->preparar();

        $this->confirmar($this->subir())->assertRedirect();

        $grupo = Grupo::firstWhere('clave', '0901');
        $this->assertSame(6, $grupo->semestre);
        $this->assertSame($this->de3a6->id, $grupo->plantilla_id);
        $this->assertSame(6, Materia::firstWhere('clave', 'LENGUA-EXTRANJERA-VI-2H')->semestre);
    }

    public function test_reimportar_uteg_no_duplica(): void
    {
        $this->preparar();
        $this->confirmar($this->subir())->assertRedirect();
        Grupo::firstWhere('clave', '01A')->update(['alumnos' => 25]);

        $segunda = $this->subir()->assertOk();
        $this->confirmar($segunda)->assertRedirect();

        foreach ($this->conteos($segunda) as $hoja => $conteo) {
            $this->assertSame(0, $conteo['crear'] + $conteo['actualizar'], "{$hoja}: ".json_encode($conteo));
        }
        $this->assertSame([4, 4, 2, 6], [Grupo::count(), Materia::count(), Profesor::count(), Curso::count()]);
        $this->assertSame(25, Grupo::firstWhere('clave', '01A')->alumnos);
    }

    public function test_sin_columnas_obligatorias_da_error(): void
    {
        $this->preparar();

        $this->subir(encabezados: ['GRUPO', 'GRUPO', 'ASIGNATURA', 'HORAS', 'TURNO', 'AULA', 'N° docente', 'Docente'])->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tieneErrores', true)
                ->where('errores.0.mensaje', 'La hoja HORARIOS no tiene las columnas GRUPO, MATERIA, HORAS y Docente.'));
    }

    public function test_horas_invalidas_dan_error_en_su_fila(): void
    {
        $this->preparar();

        $this->subir([['grupo' => '01A', 'materia' => 'Matemáticas I', 'horas' => '#REF!', 'numero' => '101', 'docente' => 'Ana Pérez']])->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tieneErrores', true)
                ->where('errores.0', ['hoja' => 'HORARIOS', 'fila' => 3, 'columna' => 'HORAS', 'mensaje' => '«#REF!» no es un número entero']));
    }

    public function test_opciones_de_otra_escuela_o_faltantes_dan_422(): void
    {
        $this->preparar();
        $ajeno = $this->enOtraEscuela(fn () => Plantel::factory()->create());

        $this->subir(opciones: ['plantel_id' => $ajeno->id])->assertSessionHasErrors('plantel_id');
        $this->subir(opciones: ['plan_id' => null])->assertSessionHasErrors('plan_id');
    }
}
