<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Disponibilidad;
use App\Models\Escuela;
use App\Models\Sincronia;
use App\Models\User;
use App\Solver\ConstructorProblema;
use App\Solver\ParametrosCorrida;
use App\Support\EscuelaActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Tests\TestCase;

class CargarDemoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function demo(): array
    {
        return json_decode((string) file_get_contents(base_path('../../contracts/fixtures/demo/prepa-horizonte.json')), true);
    }

    public function test_la_demo_reproduce_el_problema(): void
    {
        $this->artisan('demo:cargar', ['--password' => 'secreta-123'])->assertSuccessful();

        $escuela = Escuela::firstWhere('nombre', 'Prepa Horizonte');
        $problema = app(EscuelaActual::class)->usar($escuela->id, fn () => app(ConstructorProblema::class)
            ->construir($escuela->cicloActual()->firstOrFail(), ParametrosCorrida::normalizar([], $escuela)));
        $json = $this->demo();

        foreach (['grupos', 'profesores', 'cursos', 'materias', 'espacios', 'plantillas', 'planteles'] as $k) {
            $this->assertCount(count($json[$k]), $problema[$k], $k);
        }

        $schema = json_decode((string) file_get_contents(base_path('../../contracts/solve-request.schema.json')));
        $resultado = (new Validator)->validate(json_decode((string) json_encode($problema)), $schema);
        $this->assertTrue($resultado->isValid(), json_encode($resultado->isValid() ? [] : (new ErrorFormatter)->format($resultado->error())));

        // Las franjas en las dos listas de un profesor cuentan una vez (quedan como no disponibles).
        $refs = fn (array $p, string $campo) => array_map(fn (array $r) => "{$r['plantilla']}-{$r['dia']}-{$r['orden']}", $p[$campo] ?? []);
        $disponibilidades = array_sum(array_map(fn (array $p) => count(array_unique([...$refs($p, 'no_disponible'), ...$refs($p, 'preferente')])), $json['profesores']));
        $noDisponibles = array_sum(array_map(fn (array $p) => count(array_unique($refs($p, 'no_disponible'))), $json['profesores']));
        app(EscuelaActual::class)->usar($escuela->id, function () use ($disponibilidades, $noDisponibles, $json, $escuela): void {
            $this->assertSame($disponibilidades, Disponibilidad::count());
            $this->assertSame($noDisponibles, Disponibilidad::query()->where('estado', 'no_disponible')->count());
            $this->assertSame(count(array_unique(array_filter(array_column($json['cursos'], 'sincronia')))), Sincronia::count());
            $this->assertSame('2026B', $escuela->cicloActual()->firstOrFail()->clave);
        });

        $admin = User::firstWhere('email', 'admin@prepa-horizonte.test');
        $this->assertSame(Rol::Admin, $admin->rol);
        $this->assertSame($escuela->id, $admin->escuela_id);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(Hash::check('secreta-123', $admin->password));
    }

    public function test_la_demo_chica_del_plan_gratis_se_carga(): void
    {
        $this->artisan('demo:cargar', ['--archivo' => '../../contracts/fixtures/demo/prepa-horizonte-chica.json', '--password' => 'secreta-123'])
            ->assertSuccessful();

        $escuela = Escuela::firstWhere('nombre', 'Prepa Horizonte');
        $problema = app(EscuelaActual::class)->usar($escuela->id, fn () => app(ConstructorProblema::class)
            ->construir($escuela->cicloActual()->firstOrFail(), ParametrosCorrida::normalizar([], $escuela)));

        $this->assertSame([1, 4, 32, 30], [
            count($problema['planteles']), count($problema['grupos']), count($problema['cursos']), $problema['parametros']['tiempo_limite_s'],
        ]);
    }

    public function test_sin_password_imprime_una(): void
    {
        $this->artisan('demo:cargar', ['--correo' => 'otra@prepa-horizonte.test'])
            ->expectsOutputToContain('Contraseña del admin:')
            ->assertSuccessful();
    }

    public function test_si_ya_existe_no_toca_nada(): void
    {
        $this->artisan('demo:cargar', ['--password' => 'secreta-123'])->assertSuccessful();

        $this->artisan('demo:cargar', ['--password' => 'otra-cosa-456'])
            ->expectsOutputToContain('La escuela demo ya existe; usa --correo con otro correo.')
            ->assertFailed();

        $this->assertSame(1, Escuela::count());
        $this->assertTrue(Hash::check('secreta-123', User::firstWhere('email', 'admin@prepa-horizonte.test')->password));
    }
}
