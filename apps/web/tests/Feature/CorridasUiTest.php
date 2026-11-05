<?php

namespace Tests\Feature;

use App\Enums\EstadoCorrida;
use App\Models\Corrida;
use App\Models\Espacio;
use App\Solver\GuardarResultado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\EscuelaDePrueba;
use Tests\Support\RespuestaFalsa;
use Tests\TestCase;

class CorridasUiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $escuela
     * @param  array<string, mixed>|null  $respuesta
     */
    private function corridaTerminada(array $escuela, ?array $respuesta = null): Corrida
    {
        $corrida = $escuela['ciclo']->corridas()->create(['estado' => EstadoCorrida::Resolviendo, 'parametros' => [], 'tiempo_limite_s' => 10]);
        app(GuardarResultado::class)->guardar($corrida, $respuesta ?? RespuestaFalsa::ok($escuela['ciclo']));

        return $corrida->fresh();
    }

    public function test_show_trae_sesiones_con_hora(): void
    {
        $e = EscuelaDePrueba::crear();
        $this->actingAs($e['admin']);
        $c = $this->corridaTerminada($e);

        $this->get(route('corridas.show', $c))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('escuela/corridas/Show')
                ->where('corrida.estado', 'ok')
                ->where('corrida.activa', false)
                ->has('franjas', 20)
                ->where('franjas.0.plantilla_id', $e['plantilla']->id)
                ->has('sesiones', 5)
                ->where('sesiones.0.materia', 'Matemáticas I')
                ->where('sesiones.0.grupos.0.nombre', '1A')
                ->where('sesiones.0.profesor.nombre', 'Ana López')
                ->where('sesiones.0.espacio.nombre', 'Aula 1')
                ->where('sesiones.0.dia', 0)
                ->where('sesiones.0.inicio', '07:00')
                ->where('sesiones.0.fin', '08:40')
                ->where('sesiones.0.plantilla_id', $e['plantilla']->id)
                ->where('sesiones.0.sin_acomodar', false)
                ->has('catalogos.grupos', 2)
                ->where('catalogos.grupos.0.nombre', '1A')
                ->where('catalogos.grupos.0.plantilla_ids', [$e['plantilla']->id])
                ->has('catalogos.profesores', 2)
                ->where('catalogos.profesores.0.nombre', 'Ana López')
                ->has('catalogos.espacios', 2)
                ->has('reglas', 7));
    }

    public function test_sesion_con_espacio_borrado_sale_sin_espacio(): void
    {
        $e = EscuelaDePrueba::crear();
        $this->actingAs($e['admin']);
        $c = $this->corridaTerminada($e);
        Espacio::query()->where('clave', 'A1')->firstOrFail()->delete();

        $this->get(route('corridas.show', $c))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('sesiones.0.espacio', null)->has('sesiones', 5));
    }

    public function test_entidad_borrada_en_diagnostico_no_rompe(): void
    {
        $e = EscuelaDePrueba::crear();
        $this->actingAs($e['admin']);
        $grupo = $e['grupos']['1B'];
        $ana = $e['profesores']['P1'];
        $c = $this->corridaTerminada($e, RespuestaFalsa::sinSolucion([[
            'codigo' => 'NUCLEO', 'regla' => 'H1', 'mensaje' => 'El grupo 1B no cabe con Ana.',
            'entidades' => [
                ['tipo' => 'grupo', 'id' => (string) $grupo->id],
                ['tipo' => 'profesor', 'id' => (string) $ana->id],
                ['tipo' => 'sesion', 'id' => '1A-MAT1#0'],
            ],
        ]]));
        $e['cursos']['1B-ING1']->delete();
        $grupo->delete();

        $this->get(route('corridas.show', $c))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('corrida.estado', 'sin_solucion')
                ->where('corrida.diagnostico.0.mensaje', 'El grupo 1B no cabe con Ana.')
                ->where("entidades.grupo:{$grupo->id}", ['nombre' => null, 'url' => null])
                ->where("entidades.profesor:{$ana->id}.nombre", 'Ana López')
                ->where("entidades.profesor:{$ana->id}.url", route('profesores.edit', $ana))
                ->where('entidades.sesion:1A-MAT1#0', ['nombre' => null, 'url' => null]));
    }

    public function test_recarga_parcial_trae_el_estado(): void
    {
        $e = EscuelaDePrueba::crear();
        $this->actingAs($e['admin']);
        $c = $e['ciclo']->corridas()->create(['estado' => EstadoCorrida::Pendiente, 'parametros' => [], 'tiempo_limite_s' => 10]);

        $this->get(route('corridas.show', $c))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('corrida.activa', true)
                ->reloadOnly('corrida', fn (Assert $recarga) => $recarga
                    ->where('corrida.estado', 'pendiente')
                    ->missing('sesiones')
                    ->missing('catalogos')));
    }

    public function test_formulario_rechaza_s3_obligatoria(): void
    {
        $e = EscuelaDePrueba::crear();
        $this->actingAs($e['admin']);
        Queue::fake();

        $this->get(route('corridas.create'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('escuela/corridas/Create')
                ->has('reglas', 7)
                ->where('reglas.0.clave', 'S1')
                ->where('reglas.0.nombre', 'Sin huecos para los grupos')
                ->where('reglas.0.modo', 'obligatoria')
                ->where('reglas.1.peso', 7)
                ->where('reglas.2.modos', ['apagada', 'preferencia'])
                ->where('reglas.3.modos', ['apagada', 'preferencia', 'obligatoria']));

        $this->post(route('corridas.store'), ['reglas' => ['S3' => ['modo' => 'obligatoria', 'peso' => 5]]])
            ->assertSessionHasErrors('reglas.S3.modo');

        $this->assertSame(0, Corrida::count());
    }

    public function test_index_lista_las_corridas_del_ciclo(): void
    {
        $e = EscuelaDePrueba::crear();
        $this->actingAs($e['admin']);
        $vieja = $this->corridaTerminada($e);
        $nueva = $e['ciclo']->corridas()->create(['estado' => EstadoCorrida::Error, 'parametros' => [], 'tiempo_limite_s' => 10]);

        $this->get(route('corridas.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('escuela/corridas/Index')
                ->has('corridas', 2)
                ->where('corridas.0.id', $nueva->id)
                ->where('corridas.0.estado_etiqueta', 'Error')
                ->where('corridas.1.id', $vieja->id)
                ->where('corridas.1.sesiones', 5));
    }
}
