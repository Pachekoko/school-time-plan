<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'escuela' => 'Prepa de Prueba',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_al_registrarse_entra_directo_sin_confirmar_el_correo(): void
    {
        Notification::fake();

        $this->post(route('register.store'), [
            'escuela' => 'Prepa de Prueba', 'name' => 'Ana', 'email' => 'ana@ejemplo.mx',
            'password' => 'contraseña-larga-123', 'password_confirmation' => 'contraseña-larga-123',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->get(route('escuela.inicio'))->assertOk();
        Notification::assertNothingSent();
    }

    public function test_una_cuenta_sin_correo_confirmado_entra_igual(): void
    {
        $this->actingAs(User::factory()->admin()->unverified()->create())
            ->get(route('escuela.inicio'))
            ->assertOk();
    }
}
