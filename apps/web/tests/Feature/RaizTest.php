<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La raíz no tiene página propia: lleva al login o, con sesión, al panel.
 */
class RaizTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_sesion_la_raiz_lleva_al_login(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    public function test_con_sesion_la_raiz_lleva_al_panel(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('home'))
            ->assertRedirect(route('dashboard'));
    }
}
