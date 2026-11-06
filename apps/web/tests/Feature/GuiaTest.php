<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\EscuelaDePrueba;
use Tests\TestCase;

class GuiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_admin_recibe_su_avance(): void
    {
        $e = EscuelaDePrueba::crear();

        $this->actingAs($e['admin'])->get(route('escuela.inicio'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('guia.hechos.planteles', true)
                ->where('guia.hechos.generar', false)
                ->where('guia.cerrada', false));
    }

    public function test_el_profesor_no_recibe_la_guia(): void
    {
        $this->actingAs(User::factory()->profesor()->create())->get(route('mi-disponibilidad'))
            ->assertInertia(fn (Assert $page) => $page->where('guia', null));
    }

    public function test_cerrar_y_abrir_la_guia_se_recuerda(): void
    {
        $admin = EscuelaDePrueba::crear()['admin'];
        $this->actingAs($admin);

        $this->put(route('guia.update'), ['abierta' => false])->assertNoContent();
        $this->assertNotNull($admin->fresh()->guia_cerrada_en);
        $this->get(route('escuela.inicio'))->assertInertia(fn (Assert $page) => $page->where('guia.cerrada', true));

        $this->put(route('guia.update'), ['abierta' => true])->assertNoContent();
        $this->assertNull($admin->fresh()->guia_cerrada_en);
    }

    public function test_el_profesor_no_puede_cambiar_la_guia(): void
    {
        $this->actingAs(User::factory()->profesor()->create())
            ->put(route('guia.update'), ['abierta' => false])
            ->assertForbidden();
    }

    public function test_abierta_es_obligatoria(): void
    {
        $this->actingAs(EscuelaDePrueba::crear()['admin'])
            ->put(route('guia.update'), [])
            ->assertSessionHasErrors('abierta');
    }
}
