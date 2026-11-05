<?php

namespace Tests\Feature;

use App\Models\Ciclo;
use App\Models\Grupo;
use App\Models\PlanEstudios;
use App\Models\Plantel;
use App\Models\PlantillaHoraria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ActuaComoAdmin;
use Tests\TestCase;

class GruposTest extends TestCase
{
    use ActuaComoAdmin, RefreshDatabase;

    public function test_grupos_sin_ciclo_actual_redirige(): void
    {
        $this->comoAdmin();

        $this->get(route('grupos.index'))
            ->assertRedirect(route('ciclos.index'))
            ->assertInertiaFlash('toast.message', 'Primero elige el ciclo actual.');
        $this->get(route('grupos.create'))->assertRedirect(route('ciclos.index'));
    }

    public function test_crud_de_grupos_en_el_ciclo_actual(): void
    {
        $this->comoAdmin();
        $ciclo = $this->conCicloActual();
        $plantel = Plantel::factory()->create(['nombre' => 'Plantel Centro']);
        $plantilla = PlantillaHoraria::factory()->create(['nombre' => 'Matutino']);
        $plan = PlanEstudios::factory()->create();
        Grupo::factory()->for(Ciclo::factory())->for($plantel)->for($plantilla, 'plantilla')->for($plan, 'plan')->create(['clave' => 'VIEJO']);

        $this->get(route('grupos.create'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('escuela/grupos/Form')
                ->where('grupo', null)
                ->has('planteles', 1)
                ->has('plantillas', 1)
                ->has('planes', 1));

        $datos = ['plantel_id' => $plantel->id, 'plantilla_id' => $plantilla->id, 'plan_id' => $plan->id, 'semestre' => 1, 'clave' => '1A', 'alumnos' => 35];
        $this->post(route('grupos.store'), $datos)->assertRedirect(route('grupos.index'));
        $grupo = Grupo::firstWhere('clave', '1A');
        $this->assertSame($ciclo->id, $grupo->ciclo_id);

        $this->get(route('grupos.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('escuela/grupos/Index')
                ->where('ciclo', $ciclo->clave)
                ->has('grupos', 1)
                ->where('grupos.0.clave', '1A')
                ->where('grupos.0.plantel', 'Plantel Centro')
                ->where('grupos.0.plantilla', 'Matutino'));

        $this->put(route('grupos.update', $grupo), [...$datos, 'alumnos' => 40])->assertRedirect(route('grupos.index'));
        $this->assertSame(40, $grupo->fresh()->alumnos);

        $this->delete(route('grupos.destroy', $grupo))->assertRedirect(route('grupos.index'));
        $this->assertModelMissing($grupo);
    }

    public function test_clave_unica_por_ciclo(): void
    {
        $this->comoAdmin();
        $this->conCicloActual();
        $otroCiclo = Ciclo::factory()->create();
        $grupoViejo = Grupo::factory()->for($otroCiclo)->create(['clave' => '1A']);
        $datos = [
            'plantel_id' => $grupoViejo->plantel_id, 'plantilla_id' => $grupoViejo->plantilla_id, 'plan_id' => $grupoViejo->plan_id,
            'semestre' => 1, 'clave' => '1A', 'alumnos' => 30,
        ];

        $this->post(route('grupos.store'), $datos)->assertSessionHasNoErrors();
        $this->post(route('grupos.store'), $datos)->assertSessionHasErrors('clave');
    }

    public function test_grupo_con_plantilla_de_otra_escuela_da_422(): void
    {
        $this->comoAdmin();
        $this->conCicloActual();
        $ajena = $this->enOtraEscuela(fn () => PlantillaHoraria::factory()->create());

        $this->post(route('grupos.store'), [
            'plantel_id' => Plantel::factory()->create()->id, 'plantilla_id' => $ajena->id, 'plan_id' => PlanEstudios::factory()->create()->id,
            'semestre' => 1, 'clave' => '1A', 'alumnos' => 30,
        ])->assertSessionHasErrors('plantilla_id');

        $this->assertSame(0, Grupo::count());
    }

    public function test_grupo_de_otro_ciclo_da_404(): void
    {
        $this->comoAdmin();
        $this->conCicloActual();
        $viejo = Grupo::factory()->for(Ciclo::factory())->create();

        $this->get(route('grupos.edit', $viejo))->assertNotFound();
        $this->delete(route('grupos.destroy', $viejo))->assertNotFound();

        $this->assertModelExists($viejo);
    }

    public function test_borrar_plantilla_o_plantel_con_grupos_se_bloquea(): void
    {
        $this->comoAdmin();
        $ciclo = $this->conCicloActual();
        $plantel = Plantel::factory()->create(['nombre' => 'Plantel Centro']);
        $plantilla = PlantillaHoraria::factory()->create(['nombre' => 'Matutino']);
        $plan = PlanEstudios::factory()->create(['nombre' => 'Plan 2023']);
        Grupo::factory()->for($ciclo)->for($plantel)->for($plantilla, 'plantilla')->for($plan, 'plan')->create();
        Grupo::factory()->for($ciclo)->for($plantel)->create();

        $this->delete(route('plantillas.destroy', $plantilla))
            ->assertSessionHasErrors(['borrar' => 'No se puede borrar «Matutino»: lo usa 1 grupo.']);
        $this->delete(route('planteles.destroy', $plantel))
            ->assertSessionHasErrors(['borrar' => 'No se puede borrar «Plantel Centro»: lo usan 2 grupos.']);
        $this->delete(route('planes.destroy', $plan))
            ->assertSessionHasErrors(['borrar' => 'No se puede borrar «Plan 2023»: lo usa 1 grupo.']);
        $this->delete(route('ciclos.destroy', $ciclo))
            ->assertSessionHasErrors(['borrar' => "No se puede borrar «{$ciclo->clave}»: lo usan 2 grupos."]);

        $this->assertModelExists($plantilla);
        $this->assertModelExists($plantel);
        $this->assertModelExists($plan);
        $this->assertModelExists($ciclo);
    }
}
