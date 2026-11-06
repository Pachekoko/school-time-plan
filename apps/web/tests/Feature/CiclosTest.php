<?php

namespace Tests\Feature;

use App\Enums\EstadoCiclo;
use App\Models\Ciclo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ActuaComoAdmin;
use Tests\TestCase;

class CiclosTest extends TestCase
{
    use ActuaComoAdmin, RefreshDatabase;

    public function test_crud_de_ciclos(): void
    {
        $this->comoAdmin();

        $this->get(route('ciclos.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('escuela/ciclos/Index')->has('ciclos', 0));
        $this->get(route('ciclos.create'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('escuela/ciclos/Form')->where('ciclo', null)->has('estados', 2));

        $this->post(route('ciclos.store'), ['clave' => '2026-A', 'inicio' => '2026-02-01', 'fin' => '2026-07-15', 'estado' => 'captura'])
            ->assertRedirect(route('ciclos.index'));
        $ciclo = Ciclo::firstWhere('clave', '2026-A');
        $this->assertSame(EstadoCiclo::Captura, $ciclo->estado);
        $this->assertSame('2026-02-01', $ciclo->inicio?->toDateString());

        $this->get(route('ciclos.edit', $ciclo))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('escuela/ciclos/Form')
                ->where('ciclo.inicio', '2026-02-01')
                ->where('ciclo.estado', 'captura'));

        $this->put(route('ciclos.update', $ciclo), ['clave' => '2026-A', 'inicio' => '', 'fin' => '', 'estado' => 'cerrado'])
            ->assertRedirect(route('ciclos.index'));
        $this->assertSame(EstadoCiclo::Cerrado, $ciclo->fresh()->estado);
        $this->assertNull($ciclo->fresh()->inicio);

        $this->delete(route('ciclos.destroy', $ciclo))->assertRedirect(route('ciclos.index'));
        $this->assertModelMissing($ciclo);
    }

    public function test_fin_no_puede_ser_antes_del_inicio(): void
    {
        $this->comoAdmin();

        $this->post(route('ciclos.store'), ['clave' => '2026-A', 'inicio' => '2026-07-15', 'fin' => '2026-02-01', 'estado' => 'captura'])
            ->assertSessionHasErrors('fin');
        $this->post(route('ciclos.store'), ['clave' => '2026-B', 'inicio' => '', 'fin' => '2026-02-01', 'estado' => 'captura'])
            ->assertSessionHasNoErrors();
    }

    public function test_activar_ciclo_lo_comparte_en_props(): void
    {
        $admin = $this->comoAdmin();
        $ciclo = Ciclo::factory()->create(['clave' => '2026-A']);

        $this->get(route('escuela.inicio'))->assertInertia(fn (Assert $page) => $page->where('cicloActual', null));

        $this->post(route('ciclos.activar', $ciclo))->assertRedirect(route('ciclos.index'));
        $this->assertSame($ciclo->id, $admin->escuela->fresh()->ciclo_actual_id);

        $this->get(route('escuela.inicio'))->assertInertia(fn (Assert $page) => $page
            ->where('cicloActual.id', $ciclo->id)
            ->where('cicloActual.clave', '2026-A')
            ->where('cicloActual.estado', 'captura'));
        $this->get(route('ciclos.index'))->assertInertia(fn (Assert $page) => $page->where('ciclos.0.actual', true));
    }

    public function test_activar_ciclo_de_otra_escuela_da_404(): void
    {
        $admin = $this->comoAdmin();
        $ajeno = $this->enOtraEscuela(fn () => Ciclo::factory()->create());

        $this->post(route('ciclos.activar', $ajeno))->assertNotFound();

        $this->assertNull($admin->escuela->fresh()->ciclo_actual_id);
    }

    public function test_borrar_el_ciclo_actual_lo_quita_de_la_escuela(): void
    {
        $admin = $this->comoAdmin();
        $ciclo = Ciclo::factory()->create();
        $this->post(route('ciclos.activar', $ciclo));

        $this->delete(route('ciclos.destroy', $ciclo))->assertRedirect(route('ciclos.index'));

        $this->assertNull($admin->escuela->fresh()->ciclo_actual_id);
    }

    public function test_el_primer_ciclo_queda_como_actual(): void
    {
        $admin = $this->comoAdmin();

        $this->post(route('ciclos.store'), ['clave' => '2026-A', 'estado' => 'captura']);
        $primero = Ciclo::firstWhere('clave', '2026-A');
        $this->post(route('ciclos.store'), ['clave' => '2026-B', 'estado' => 'captura']);

        $this->assertSame($primero->id, $admin->escuela->fresh()->ciclo_actual_id);
    }
}
