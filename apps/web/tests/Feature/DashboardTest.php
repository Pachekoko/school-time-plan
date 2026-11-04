<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_el_admin_va_al_inicio_de_su_escuela()
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('dashboard'))->assertRedirect(route('escuela.inicio'));
    }

    public function test_el_profesor_va_a_su_disponibilidad()
    {
        $this->actingAs(User::factory()->profesor()->create());

        $this->get(route('dashboard'))->assertRedirect(route('mi-disponibilidad'));
    }

    public function test_el_alumno_no_tiene_inicio_en_esta_fase()
    {
        $this->actingAs(User::factory()->alumno()->create());

        $this->get(route('dashboard'))->assertForbidden();
    }
}
