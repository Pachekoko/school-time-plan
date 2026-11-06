<?php

namespace Tests\Feature;

use App\Models\Franja;
use App\Models\PlantillaHoraria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ActuaComoAdmin;
use Tests\TestCase;

class PlantillasTest extends TestCase
{
    use ActuaComoAdmin, RefreshDatabase;

    public function test_crud_de_plantillas(): void
    {
        $this->comoAdmin();

        $this->get(route('plantillas.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('escuela/plantillas/Index')->has('plantillas', 0));
        $this->get(route('plantillas.create'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('escuela/plantillas/Form')->where('plantilla', null));

        $this->post(route('plantillas.store'), ['clave' => 'MAT', 'nombre' => 'Matutino']);
        $plantilla = PlantillaHoraria::firstWhere('clave', 'MAT');
        $this->assertNotNull($plantilla);

        $this->post(route('plantillas.generar', $plantilla), ['dias' => [0, 1], 'inicio' => '07:00', 'numero' => 3, 'duracion' => 50]);
        $this->get(route('plantillas.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('plantillas.0.nombre', 'Matutino')
                ->where('plantillas.0.dias', [0, 1])
                ->where('plantillas.0.clases', 6));

        $this->put(route('plantillas.update', $plantilla), ['clave' => 'MAT', 'nombre' => 'Turno matutino'])
            ->assertRedirect(route('plantillas.index'));
        $this->assertSame('Turno matutino', $plantilla->fresh()->nombre);

        $this->delete(route('plantillas.destroy', $plantilla))->assertRedirect(route('plantillas.index'));
        $this->assertModelMissing($plantilla);
        $this->assertSame(0, Franja::withoutGlobalScope('escuela')->count());
    }

    public function test_crear_plantilla_lleva_a_sus_franjas(): void
    {
        $this->comoAdmin();

        $respuesta = $this->post(route('plantillas.store'), ['clave' => 'MAT', 'nombre' => 'Matutino']);

        $respuesta->assertRedirect(route('plantillas.franjas', PlantillaHoraria::firstWhere('clave', 'MAT')));
    }

    public function test_generar_franjas_reemplaza_las_de_la_plantilla(): void
    {
        $this->comoAdmin();
        $plantilla = PlantillaHoraria::factory()->create();

        $this->post(route('plantillas.generar', $plantilla), [
            'dias' => [0, 1, 2, 3, 4], 'inicio' => '07:00', 'numero' => 6, 'duracion' => 50, 'receso_tras' => 3, 'receso_min' => 30,
        ])->assertRedirect(route('plantillas.franjas', $plantilla));
        $this->assertSame(35, $plantilla->franjas()->count());
        $this->assertSame(5, $plantilla->franjas()->where('tipo', 'receso')->count());

        $this->post(route('plantillas.generar', $plantilla), ['dias' => [5], 'inicio' => '08:00', 'numero' => 4, 'duracion' => 60])
            ->assertSessionHasNoErrors();
        $this->assertSame(4, $plantilla->franjas()->count());
        $this->assertSame([5], $plantilla->franjas()->distinct()->pluck('dia')->all());

        $this->get(route('plantillas.franjas', $plantilla))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('escuela/plantillas/Franjas')
                ->where('plantilla.id', $plantilla->id)
                ->has('franjas', 4)
                ->where('franjas.3.orden', 3)
                ->where('franjas.3.inicio', '11:00')
                ->where('franjas.3.fin', '12:00')
                ->where('franjas.3.tipo', 'clase'));
    }

    public function test_generar_valida_los_parametros(): void
    {
        $this->comoAdmin();
        $plantilla = PlantillaHoraria::factory()->create();
        $datos = ['dias' => [0], 'inicio' => '07:00', 'numero' => 4, 'duracion' => 60];

        $this->post(route('plantillas.generar', $plantilla), [...$datos, 'dias' => []])->assertSessionHasErrors('dias');
        $this->post(route('plantillas.generar', $plantilla), [...$datos, 'dias' => [7]])->assertSessionHasErrors('dias.0');
        $this->post(route('plantillas.generar', $plantilla), [...$datos, 'inicio' => '7am'])->assertSessionHasErrors('inicio');
        $this->post(route('plantillas.generar', $plantilla), [...$datos, 'receso_tras' => 4])->assertSessionHasErrors('receso_tras');
        $this->post(route('plantillas.generar', $plantilla), [...$datos, 'inicio' => '22:00'])
            ->assertSessionHasErrors(['numero' => 'Las franjas no caben en el día: terminarían a las 26:00.']);

        $this->assertSame(0, $plantilla->franjas()->count());
    }

    public function test_franjas_traslapadas_se_rechazan(): void
    {
        $this->comoAdmin();
        $plantilla = PlantillaHoraria::factory()->create();
        $this->post(route('plantillas.generar', $plantilla), ['dias' => [0], 'inicio' => '07:00', 'numero' => 3, 'duracion' => 50]);
        [$primera, $segunda] = $plantilla->franjas()->orderBy('orden')->take(2)->get()->all();

        $this->put(route('franjas.update', $primera), ['inicio' => '07:00', 'fin' => '08:00', 'tipo' => 'clase'])
            ->assertSessionHasErrors(['fin' => 'Las franjas Lun 0 y Lun 1 se traslapan.']);
        $this->put(route('franjas.update', $segunda), ['inicio' => '07:40', 'fin' => '08:40', 'tipo' => 'clase'])
            ->assertSessionHasErrors(['inicio' => 'Las franjas Lun 0 y Lun 1 se traslapan.']);

        $this->assertSame('07:50', $primera->fresh()->fin);
        $this->assertSame('07:50', $segunda->fresh()->inicio);
    }

    public function test_fin_debe_ser_despues_del_inicio(): void
    {
        $this->comoAdmin();
        $plantilla = PlantillaHoraria::factory()->create();
        $this->post(route('plantillas.generar', $plantilla), ['dias' => [0], 'inicio' => '07:00', 'numero' => 1, 'duracion' => 50]);

        $this->put(route('franjas.update', $plantilla->franjas()->first()), ['inicio' => '08:00', 'fin' => '08:00', 'tipo' => 'clase'])
            ->assertSessionHasErrors('fin');
    }

    public function test_editar_y_borrar_una_franja(): void
    {
        $this->comoAdmin();
        $plantilla = PlantillaHoraria::factory()->create();
        $this->post(route('plantillas.generar', $plantilla), ['dias' => [0], 'inicio' => '07:00', 'numero' => 3, 'duracion' => 50]);
        [, $segunda, $tercera] = $plantilla->franjas()->orderBy('orden')->get()->all();

        $this->put(route('franjas.update', $tercera), ['inicio' => '08:50', 'fin' => '09:20', 'tipo' => 'receso'])
            ->assertRedirect(route('plantillas.franjas', $plantilla));
        $this->assertSame(['08:50', '09:20', 'receso'], [$tercera->fresh()->inicio, $tercera->fresh()->fin, $tercera->fresh()->tipo->value]);

        $this->delete(route('franjas.destroy', $segunda))->assertRedirect(route('plantillas.franjas', $plantilla));
        $this->assertModelMissing($segunda);
        $this->assertSame(2, $plantilla->franjas()->count());
    }

    public function test_plantillas_y_franjas_de_otra_escuela_dan_404(): void
    {
        $this->comoAdmin();
        [$plantilla, $franja] = $this->enOtraEscuela(function () {
            $plantilla = PlantillaHoraria::factory()->create();

            return [$plantilla, Franja::factory()->for($plantilla, 'plantilla')->create()];
        });

        $this->get(route('plantillas.franjas', $plantilla))->assertNotFound();
        $this->post(route('plantillas.generar', $plantilla), ['dias' => [0], 'inicio' => '07:00', 'numero' => 1, 'duracion' => 50])->assertNotFound();
        $this->put(route('franjas.update', $franja), ['inicio' => '07:00', 'fin' => '07:30', 'tipo' => 'clase'])->assertNotFound();
        $this->delete(route('franjas.destroy', $franja))->assertNotFound();

        $this->assertSame('07:50', Franja::withoutGlobalScope('escuela')->find($franja->id)->fin);
    }
}
