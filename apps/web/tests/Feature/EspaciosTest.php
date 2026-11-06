<?php

namespace Tests\Feature;

use App\Enums\TipoEspacio;
use App\Models\Espacio;
use App\Models\Plantel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ActuaComoAdmin;
use Tests\TestCase;

class EspaciosTest extends TestCase
{
    use ActuaComoAdmin, RefreshDatabase;

    public function test_crud_de_espacios(): void
    {
        $this->comoAdmin();
        $plantel = Plantel::factory()->create(['nombre' => 'Plantel Centro']);

        $this->get(route('espacios.create'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('escuela/espacios/Form')
                ->where('espacio', null)
                ->has('planteles', 1)
                ->has('tipos', 6)
                ->where('tipos.2', ['valor' => 'computo', 'etiqueta' => 'Cómputo']));

        $datos = ['plantel_id' => $plantel->id, 'clave' => 'LAB1', 'nombre' => 'Laboratorio 1', 'tipo' => 'laboratorio', 'capacidad' => 30];
        $this->post(route('espacios.store'), $datos)->assertRedirect(route('espacios.index'));
        $espacio = Espacio::firstWhere('clave', 'LAB1');
        $this->assertSame(TipoEspacio::Laboratorio, $espacio->tipo);
        $this->assertSame(30, $espacio->capacidad);

        $this->get(route('espacios.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('escuela/espacios/Index')
                ->has('espacios', 1)
                ->where('espacios.0.tipo', 'Laboratorio')
                ->where('espacios.0.plantel', 'Plantel Centro'));
        $this->get(route('espacios.edit', $espacio))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('espacio.tipo', 'laboratorio')->where('espacio.plantel_id', $plantel->id));

        $this->put(route('espacios.update', $espacio), [...$datos, 'capacidad' => 25])->assertRedirect(route('espacios.index'));
        $this->assertSame(25, $espacio->fresh()->capacidad);

        $this->delete(route('espacios.destroy', $espacio))->assertRedirect(route('espacios.index'));
        $this->assertModelMissing($espacio);
    }

    public function test_espacio_con_plantel_de_otra_escuela_da_422(): void
    {
        $this->comoAdmin();
        $ajeno = $this->enOtraEscuela(fn () => Plantel::factory()->create());

        $this->post(route('espacios.store'), ['plantel_id' => $ajeno->id, 'clave' => 'A1', 'nombre' => 'Aula 1', 'tipo' => 'aula', 'capacidad' => 40])
            ->assertSessionHasErrors('plantel_id');

        $this->assertSame(0, Espacio::withoutGlobalScope('escuela')->count());
    }

    public function test_tipo_de_espacio_invalido_da_422(): void
    {
        $this->comoAdmin();
        $plantel = Plantel::factory()->create();

        $this->post(route('espacios.store'), ['plantel_id' => $plantel->id, 'clave' => 'A1', 'nombre' => 'Aula 1', 'tipo' => 'alberca', 'capacidad' => 40])
            ->assertSessionHasErrors('tipo');

        $this->assertSame(0, Espacio::count());
    }

    public function test_capacidad_de_1_a_10000(): void
    {
        $this->comoAdmin();
        $plantel = Plantel::factory()->create();
        $datos = ['plantel_id' => $plantel->id, 'clave' => 'A1', 'nombre' => 'Aula 1', 'tipo' => 'aula'];

        $this->post(route('espacios.store'), [...$datos, 'capacidad' => 0])->assertSessionHasErrors('capacidad');
        $this->post(route('espacios.store'), [...$datos, 'capacidad' => 10001])->assertSessionHasErrors('capacidad');
        $this->post(route('espacios.store'), [...$datos, 'capacidad' => 10000])->assertSessionHasNoErrors();
    }

    public function test_editar_espacio_de_otra_escuela_da_404(): void
    {
        $this->comoAdmin();
        $ajeno = $this->enOtraEscuela(fn () => Espacio::factory()->create());

        $this->get(route('espacios.edit', $ajeno))->assertNotFound();
        $this->delete(route('espacios.destroy', $ajeno))->assertNotFound();

        $this->assertSame(1, Espacio::withoutGlobalScope('escuela')->count());
    }
}
