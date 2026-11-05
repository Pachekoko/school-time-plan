<?php

namespace Tests\Feature;

use App\Enums\Contrato;
use App\Enums\Rol;
use App\Models\Materia;
use App\Models\Plantel;
use App\Models\Profesor;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ActuaComoAdmin;
use Tests\TestCase;

class ProfesoresTest extends TestCase
{
    use ActuaComoAdmin, RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function datos(array $extra = []): array
    {
        return [
            'clave' => 'P001', 'nombre' => 'Ana López', 'contrato' => 'asignatura',
            'horas_min' => 0, 'horas_max' => 20, 'max_dia' => 6, 'sin_huecos' => '1',
            ...$extra,
        ];
    }

    public function test_crud_de_profesores(): void
    {
        $this->comoAdmin();
        $plantel = Plantel::factory()->create();
        [$mate, $fisica] = Materia::factory()->count(2)->create()->all();

        $this->get(route('profesores.create'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('escuela/profesores/Form')
                ->where('profesor', null)
                ->has('planteles', 1)
                ->has('materias', 2)
                ->has('contratos', 3));

        $this->post(route('profesores.store'), $this->datos(['planteles' => [$plantel->id], 'materias' => [$mate->id, $fisica->id]]))
            ->assertRedirect(route('profesores.index'));
        $profesor = Profesor::firstWhere('clave', 'P001');
        $this->assertSame(Contrato::Asignatura, $profesor->contrato);
        $this->assertTrue($profesor->sin_huecos);
        $this->assertEqualsCanonicalizing([$mate->id, $fisica->id], $profesor->materias()->pluck('materias.id')->all());

        $this->get(route('profesores.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('escuela/profesores/Index')
                ->where('profesores.0.nombre', 'Ana López')
                ->where('profesores.0.materias', 2)
                ->where('profesores.0.cuenta', null));
        $this->get(route('profesores.edit', $profesor))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('profesor.planteles', [$plantel->id])
                ->where('profesor.sin_huecos', true));

        $this->put(route('profesores.update', $profesor), $this->datos(['materias' => [$fisica->id]]))
            ->assertRedirect(route('profesores.index'));
        $this->assertSame([$fisica->id], $profesor->materias()->pluck('materias.id')->all());
        $this->assertSame(0, $profesor->planteles()->count());

        $this->delete(route('profesores.destroy', $profesor))->assertRedirect(route('profesores.index'));
        $this->assertModelMissing($profesor);
    }

    public function test_sin_huecos_apagado_si_no_viene(): void
    {
        $this->comoAdmin();

        $this->post(route('profesores.store'), $this->datos(['sin_huecos' => null]))->assertSessionHasNoErrors();

        $this->assertFalse(Profesor::firstWhere('clave', 'P001')->sin_huecos);
    }

    public function test_horas_max_no_puede_ser_menor_que_min(): void
    {
        $this->comoAdmin();

        $this->post(route('profesores.store'), $this->datos(['horas_min' => 10, 'horas_max' => 5]))->assertSessionHasErrors('horas_max');
    }

    public function test_profesor_con_planteles_o_materias_de_otra_escuela_da_422(): void
    {
        $this->comoAdmin();
        [$plantelAjeno, $materiaAjena] = $this->enOtraEscuela(fn () => [Plantel::factory()->create(), Materia::factory()->create()]);

        $this->post(route('profesores.store'), $this->datos(['planteles' => [$plantelAjeno->id]]))->assertSessionHasErrors('planteles.0');
        $this->post(route('profesores.store'), $this->datos(['materias' => [$materiaAjena->id]]))->assertSessionHasErrors('materias.0');

        $this->assertSame(0, Profesor::withoutGlobalScope('escuela')->count());
    }

    public function test_correo_crea_usuario_profesor_y_manda_enlace(): void
    {
        Notification::fake();
        $admin = $this->comoAdmin();

        $this->post(route('profesores.store'), $this->datos(['correo' => 'ana@ejemplo.mx']))->assertSessionHasNoErrors();

        $usuario = User::firstWhere('email', 'ana@ejemplo.mx');
        $this->assertSame(Rol::Profesor, $usuario->rol);
        $this->assertSame($admin->escuela_id, $usuario->escuela_id);
        $this->assertSame('Ana López', $usuario->name);
        $this->assertSame($usuario->id, Profesor::firstWhere('clave', 'P001')->user_id);
        Notification::assertSentTo($usuario, ResetPassword::class);
        $this->get(route('profesores.index'))->assertInertia(fn (Assert $page) => $page->where('profesores.0.cuenta', 'ana@ejemplo.mx'));
    }

    public function test_correo_vincula_un_usuario_profesor_existente_sin_mandar_enlace(): void
    {
        Notification::fake();
        $admin = $this->comoAdmin();
        $existente = User::factory()->profesor()->create(['escuela_id' => $admin->escuela_id, 'email' => 'beto@ejemplo.mx']);

        $this->post(route('profesores.store'), $this->datos(['correo' => 'beto@ejemplo.mx']))->assertSessionHasNoErrors();

        $this->assertSame($existente->id, Profesor::firstWhere('clave', 'P001')->user_id);
        Notification::assertNothingSent();
    }

    public function test_correo_que_no_se_puede_vincular(): void
    {
        $admin = $this->comoAdmin();
        User::factory()->profesor()->create(['email' => 'de-otra@ejemplo.mx']);
        $ocupado = User::factory()->profesor()->create(['escuela_id' => $admin->escuela_id, 'email' => 'ocupado@ejemplo.mx']);
        Profesor::factory()->create(['user_id' => $ocupado->id]);

        $this->post(route('profesores.store'), $this->datos(['correo' => 'de-otra@ejemplo.mx']))
            ->assertSessionHasErrors(['correo' => 'Ese correo ya tiene una cuenta que no se puede vincular.']);
        $this->post(route('profesores.store'), $this->datos(['correo' => $admin->email]))
            ->assertSessionHasErrors(['correo' => 'Ese correo ya tiene una cuenta que no se puede vincular.']);
        $this->post(route('profesores.store'), $this->datos(['correo' => 'ocupado@ejemplo.mx']))
            ->assertSessionHasErrors(['correo' => 'Ese correo ya está vinculado a otro profesor.']);

        $this->assertSame(1, Profesor::count());
    }

    public function test_borrar_plantel_o_materia_con_profesores_se_bloquea(): void
    {
        $this->comoAdmin();
        $plantel = Plantel::factory()->create(['nombre' => 'Plantel Centro']);
        $materia = Materia::factory()->create(['nombre' => 'Química']);
        $profesor = Profesor::factory()->create();
        $profesor->planteles()->attach($plantel);
        $profesor->materias()->attach($materia);

        $this->delete(route('planteles.destroy', $plantel))
            ->assertSessionHasErrors(['borrar' => 'No se puede borrar «Plantel Centro»: lo usa 1 profesor.']);
        $this->delete(route('materias.destroy', $materia))
            ->assertSessionHasErrors(['borrar' => 'No se puede borrar «Química»: lo usa 1 profesor.']);

        $this->assertModelExists($plantel);
        $this->assertModelExists($materia);
    }
}
