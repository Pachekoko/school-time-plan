<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_profesor_no_entra_al_admin(): void
    {
        $this->actingAs(User::factory()->profesor()->create())
            ->get(route('escuela.inicio'))
            ->assertForbidden();
    }

    public function test_alumno_no_entra_al_admin(): void
    {
        $this->actingAs(User::factory()->alumno()->create())
            ->get(route('escuela.inicio'))
            ->assertForbidden();
    }

    public function test_admin_entra_y_ve_su_escuela_en_props(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('escuela.inicio'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('escuela/Inicio')
                ->where('escuela.id', $admin->escuela_id)
                ->where('escuela.nombre', $admin->escuela->nombre)
                ->where('rol', 'admin'));
    }

    public function test_profesor_entra_a_mi_disponibilidad(): void
    {
        $this->actingAs(User::factory()->profesor()->create())
            ->get(route('mi-disponibilidad'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('MiDisponibilidad')
                ->where('rol', 'profesor'));
    }
}
