<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Http\Requests\Concerns\ReglasEscuela;
use App\Models\Escuela;
use App\Models\Plantel;
use App\Models\User;
use App\Support\EscuelaActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Unique;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class MultiEscuelaTest extends TestCase
{
    use RefreshDatabase;

    private EscuelaActual $actual;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actual = app(EscuelaActual::class);
    }

    public function test_registro_crea_escuela_y_admin(): void
    {
        $this->post(route('register.store'), [
            'escuela' => 'Prepa Horizonte', 'name' => 'Ana', 'email' => 'ana@ejemplo.mx',
            'password' => 'contraseña-larga-123', 'password_confirmation' => 'contraseña-larga-123',
        ])->assertRedirect();

        $u = User::firstWhere('email', 'ana@ejemplo.mx');
        $this->assertSame('Prepa Horizonte', $u->escuela->nombre);
        $this->assertSame(Rol::Admin, $u->rol);
    }

    public function test_registro_exige_el_nombre_de_la_escuela(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Ana', 'email' => 'ana@ejemplo.mx',
            'password' => 'contraseña-larga-123', 'password_confirmation' => 'contraseña-larga-123',
        ])->assertSessionHasErrors('escuela');

        $this->assertSame(0, Escuela::count());
        $this->assertGuest();
    }

    public function test_sin_escuela_actual_no_se_ve_nada(): void
    {
        $escuela = Escuela::factory()->create();
        $this->actual->usar($escuela->id, fn () => Plantel::factory()->count(2)->create());

        $this->assertNull($this->actual->id());
        $this->assertSame(0, Plantel::count());
        $this->assertSame(2, Plantel::withoutGlobalScope('escuela')->count());
    }

    public function test_cada_escuela_ve_solo_lo_suyo(): void
    {
        [$a, $b] = Escuela::factory()->count(2)->create();
        $deB = $this->actual->usar($b->id, fn () => Plantel::factory()->create());

        $this->assertSame($b->id, $deB->escuela_id);

        $this->actual->establecer($a->id);
        $this->assertNull(Plantel::find($deB->id));

        $this->actual->establecer($b->id);
        $this->assertTrue(Plantel::find($deB->id)->is($deB));
    }

    public function test_crear_sin_escuela_actual_lanza(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No hay escuela actual');

        Plantel::create(['clave' => 'CEN', 'nombre' => 'Centro']);
    }

    public function test_usar_restaura_la_escuela_anterior(): void
    {
        [$a, $b] = Escuela::factory()->count(2)->create();
        $this->actual->establecer($a->id);

        $dentro = $this->actual->usar($b->id, fn () => $this->actual->id());

        $this->assertSame($b->id, $dentro);
        $this->assertSame($a->id, $this->actual->id());

        try {
            $this->actual->usar($b->id, fn () => throw new RuntimeException('falla'));
        } catch (RuntimeException) {
        }

        $this->assertSame($a->id, $this->actual->id());
    }

    public function test_un_registro_de_otra_escuela_en_la_ruta_da_404(): void
    {
        Route::middleware(['web', 'auth', 'escuela'])
            ->get('/_prueba/planteles/{plantel}', fn (Plantel $plantel) => $plantel->nombre);
        $ana = User::factory()->admin()->create();
        $beto = User::factory()->admin()->create();
        $propio = $this->actual->usar($ana->escuela_id, fn () => Plantel::factory()->create());
        $ajeno = $this->actual->usar($beto->escuela_id, fn () => Plantel::factory()->create());

        $this->actingAs($ana)->get("/_prueba/planteles/{$propio->id}")->assertOk()->assertContent($propio->nombre);
        $this->actingAs($ana)->get("/_prueba/planteles/{$ajeno->id}")->assertNotFound();
    }

    public function test_existe_solo_acepta_registros_de_la_escuela_actual(): void
    {
        [$a, $b] = Escuela::factory()->count(2)->create();
        $propio = $this->actual->usar($a->id, fn () => Plantel::factory()->create());
        $ajeno = $this->actual->usar($b->id, fn () => Plantel::factory()->create());

        $this->actual->establecer($a->id);
        $regla = $this->reglas()->existe('planteles');

        $this->assertTrue(Validator::make(['p' => $propio->id], ['p' => [$regla]])->passes());
        $this->assertTrue(Validator::make(['p' => $ajeno->id], ['p' => [$regla]])->fails());
    }

    public function test_unica_se_limita_a_la_escuela_y_a_las_columnas_extra(): void
    {
        [$a, $b] = Escuela::factory()->count(2)->create();
        $centro = $this->actual->usar($a->id, fn () => Plantel::factory()->create(['clave' => 'CEN', 'nombre' => 'Centro']));
        $valida = fn (Unique $regla) => Validator::make(['c' => 'CEN'], ['c' => [$regla]])->passes();

        $this->actual->establecer($a->id);
        $this->assertFalse($valida($this->reglas()->unica('planteles')));
        $this->assertTrue($valida($this->reglas()->unica('planteles', ignorar: $centro->id)));
        $this->assertTrue($valida($this->reglas()->unica('planteles', donde: ['nombre' => 'Norte'])));
        $this->assertFalse($valida($this->reglas()->unica('planteles', donde: ['nombre' => 'Centro'])));

        $this->actual->establecer($b->id);
        $this->assertTrue($valida($this->reglas()->unica('planteles')));
    }

    private function reglas(): object
    {
        return new class
        {
            use ReglasEscuela {
                existe as public;
                unica as public;
            }
        };
    }
}
