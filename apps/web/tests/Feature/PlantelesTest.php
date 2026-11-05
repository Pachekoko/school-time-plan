<?php

namespace Tests\Feature;

use App\Models\Espacio;
use App\Models\Plantel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ActuaComoAdmin;
use Tests\TestCase;

class PlantelesTest extends TestCase
{
    use ActuaComoAdmin, RefreshDatabase;

    public function test_crud_de_planteles(): void
    {
        $this->comoAdmin();

        $this->get(route('planteles.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('escuela/planteles/Index')->has('planteles', 0));
        $this->get(route('planteles.create'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('escuela/planteles/Form')->where('plantel', null));

        $this->post(route('planteles.store'), ['clave' => 'CEN', 'nombre' => 'Plantel Centro', 'direccion' => 'Av. Juárez 100'])
            ->assertRedirect(route('planteles.index'));
        $plantel = Plantel::firstWhere('clave', 'CEN');
        $this->assertSame('Plantel Centro', $plantel->nombre);

        $this->get(route('planteles.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('planteles', 1)
                ->where('planteles.0.clave', 'CEN')
                ->where('planteles.0.espacios_count', 0));
        $this->get(route('planteles.edit', $plantel))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('escuela/planteles/Form')
                ->where('plantel.nombre', 'Plantel Centro')
                ->where('plantel.direccion', 'Av. Juárez 100'));

        $this->put(route('planteles.update', $plantel), ['clave' => 'CEN', 'nombre' => 'Centro', 'direccion' => ''])
            ->assertRedirect(route('planteles.index'));
        $this->assertSame('Centro', $plantel->fresh()->nombre);
        $this->assertNull($plantel->fresh()->direccion);

        $this->delete(route('planteles.destroy', $plantel))->assertRedirect(route('planteles.index'));
        $this->assertModelMissing($plantel);
    }

    public function test_clave_unica_solo_dentro_de_la_escuela(): void
    {
        $this->comoAdmin();
        $this->enOtraEscuela(fn () => Plantel::factory()->create(['clave' => 'CEN']));

        $this->post(route('planteles.store'), ['clave' => 'CEN', 'nombre' => 'Centro'])->assertSessionHasNoErrors();
        $this->post(route('planteles.store'), ['clave' => 'CEN', 'nombre' => 'Otro'])->assertSessionHasErrors('clave');

        $this->assertSame(1, Plantel::count());
    }

    public function test_campos_requeridos_y_largos(): void
    {
        $this->comoAdmin();

        $this->post(route('planteles.store'), ['clave' => '', 'nombre' => ''])->assertSessionHasErrors(['clave', 'nombre']);
        $this->post(route('planteles.store'), ['clave' => str_repeat('C', 31), 'nombre' => str_repeat('n', 121)])
            ->assertSessionHasErrors(['clave', 'nombre']);

        $this->assertSame(0, Plantel::count());
    }

    public function test_editar_plantel_de_otra_escuela_da_404(): void
    {
        $this->comoAdmin();
        $ajeno = $this->enOtraEscuela(fn () => Plantel::factory()->create(['nombre' => 'Ajeno']));

        $this->get(route('planteles.edit', $ajeno))->assertNotFound();
        $this->put(route('planteles.update', $ajeno), ['clave' => 'X', 'nombre' => 'Cambiado'])->assertNotFound();
        $this->delete(route('planteles.destroy', $ajeno))->assertNotFound();

        $this->assertSame('Ajeno', Plantel::withoutGlobalScope('escuela')->find($ajeno->id)->nombre);
    }

    public function test_borrar_plantel_con_espacios_se_bloquea(): void
    {
        $this->comoAdmin();
        $plantel = Plantel::factory()->create(['nombre' => 'Plantel Centro']);
        Espacio::factory()->count(2)->for($plantel)->create();

        $this->delete(route('planteles.destroy', $plantel))
            ->assertSessionHasErrors(['borrar' => 'No se puede borrar «Plantel Centro»: lo usan 2 espacios.']);

        $this->assertModelExists($plantel);
    }

    public function test_con_un_solo_uso_el_mensaje_va_en_singular(): void
    {
        $this->comoAdmin();
        $plantel = Plantel::factory()->create(['nombre' => 'Plantel Centro']);
        Espacio::factory()->for($plantel)->create();

        $this->delete(route('planteles.destroy', $plantel))
            ->assertSessionHasErrors(['borrar' => 'No se puede borrar «Plantel Centro»: lo usa 1 espacio.']);

        $this->assertModelExists($plantel);
    }

    public function test_profesor_no_entra_a_planteles(): void
    {
        $this->actingAs(User::factory()->profesor()->create())
            ->get(route('planteles.index'))
            ->assertForbidden();
    }
}
