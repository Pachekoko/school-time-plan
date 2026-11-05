<?php

namespace Tests\Feature;

use App\Enums\TipoEspacio;
use App\Models\Materia;
use App\Models\PlanEstudios;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ActuaComoAdmin;
use Tests\TestCase;

class MateriasTest extends TestCase
{
    use ActuaComoAdmin, RefreshDatabase;

    public function test_crud_de_planes(): void
    {
        $this->comoAdmin();

        $this->get(route('planes.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('escuela/planes/Index')->has('planes', 0));
        $this->get(route('planes.create'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('escuela/planes/Form')->where('plan', null));

        $this->post(route('planes.store'), ['clave' => 'BGE-23', 'nombre' => 'Bachillerato general 2023'])
            ->assertRedirect(route('planes.index'));
        $plan = PlanEstudios::firstWhere('clave', 'BGE-23');

        $this->put(route('planes.update', $plan), ['clave' => 'BGE-23', 'nombre' => 'Plan 2023'])->assertRedirect(route('planes.index'));
        $this->get(route('planes.index'))->assertInertia(fn (Assert $page) => $page
            ->where('planes.0.nombre', 'Plan 2023')
            ->where('planes.0.materias_count', 0));

        $this->delete(route('planes.destroy', $plan))->assertRedirect(route('planes.index'));
        $this->assertModelMissing($plan);
    }

    public function test_borrar_plan_con_materias_se_bloquea(): void
    {
        $this->comoAdmin();
        $plan = PlanEstudios::factory()->create(['nombre' => 'Plan 2023']);
        Materia::factory()->count(2)->for($plan, 'plan')->create();

        $this->delete(route('planes.destroy', $plan))
            ->assertSessionHasErrors(['borrar' => 'No se puede borrar «Plan 2023»: lo usan 2 materias.']);

        $this->assertModelExists($plan);
    }

    public function test_crud_de_materias(): void
    {
        $this->comoAdmin();
        $plan = PlanEstudios::factory()->create(['clave' => 'BGE-23']);

        $this->get(route('materias.create'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('escuela/materias/Form')
                ->where('materia', null)
                ->has('planes', 1)
                ->has('tipos', 6));

        $datos = ['plan_id' => $plan->id, 'clave' => 'MAT1', 'nombre' => 'Matemáticas I', 'semestre' => 1, 'horas_semana' => 3, 'patrones' => '', 'tipo_espacio' => 'aula'];
        $this->post(route('materias.store'), $datos)->assertRedirect(route('materias.index'));
        $materia = Materia::firstWhere('clave', 'MAT1');
        $this->assertSame(TipoEspacio::Aula, $materia->tipo_espacio);

        $this->get(route('materias.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('escuela/materias/Index')
                ->where('materias.0.plan', 'BGE-23')
                ->where('materias.0.patron', '2+1')
                ->where('materias.0.patron_capturado', false)
                ->where('materias.0.tipo_espacio', 'Aula'));
        $this->get(route('materias.edit', $materia))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('materia.patrones', '')->where('materia.horas_semana', 3));

        $this->put(route('materias.update', $materia), [...$datos, 'nombre' => 'Matemáticas 1', 'tipo_espacio' => 'computo'])
            ->assertRedirect(route('materias.index'));
        $this->assertSame(TipoEspacio::Computo, $materia->fresh()->tipo_espacio);

        $this->delete(route('materias.destroy', $materia))->assertRedirect(route('materias.index'));
        $this->assertModelMissing($materia);
    }

    public function test_materia_guarda_patrones_capturados_o_null(): void
    {
        $this->comoAdmin();
        $plan = PlanEstudios::factory()->create();
        $datos = ['plan_id' => $plan->id, 'nombre' => 'Inglés', 'semestre' => 1, 'horas_semana' => 2, 'tipo_espacio' => 'aula'];

        $this->post(route('materias.store'), [...$datos, 'clave' => 'ING1', 'patrones' => '2 | 1+1'])->assertSessionHasNoErrors();
        $this->post(route('materias.store'), [...$datos, 'clave' => 'ING2', 'patrones' => ''])->assertSessionHasNoErrors();

        $capturada = Materia::firstWhere('clave', 'ING1');
        $derivada = Materia::firstWhere('clave', 'ING2');
        $this->assertSame([[2], [1, 1]], $capturada->patrones);
        $this->assertNull($derivada->patrones);
        $this->assertSame([[2], [1, 1]], $derivada->patronesEfectivos());

        $this->get(route('materias.edit', $capturada))->assertInertia(fn (Assert $page) => $page->where('materia.patrones', '2 | 1+1'));
    }

    public function test_patron_que_no_suma_da_422(): void
    {
        $this->comoAdmin();
        $plan = PlanEstudios::factory()->create();

        $this->post(route('materias.store'), [
            'plan_id' => $plan->id, 'clave' => 'MAT1', 'nombre' => 'Matemáticas I', 'semestre' => 1, 'horas_semana' => 4, 'patrones' => '2+1', 'tipo_espacio' => 'aula',
        ])->assertSessionHasErrors(['patrones' => 'El patrón 2+1 suma 3 y horas_semana es 4.']);

        $this->assertSame(0, Materia::count());
    }

    public function test_materia_con_plan_de_otra_escuela_da_422(): void
    {
        $this->comoAdmin();
        $ajeno = $this->enOtraEscuela(fn () => PlanEstudios::factory()->create());

        $this->post(route('materias.store'), [
            'plan_id' => $ajeno->id, 'clave' => 'MAT1', 'nombre' => 'Matemáticas I', 'semestre' => 1, 'horas_semana' => 3, 'tipo_espacio' => 'aula',
        ])->assertSessionHasErrors('plan_id');

        $this->assertSame(0, Materia::withoutGlobalScope('escuela')->count());
    }
}
