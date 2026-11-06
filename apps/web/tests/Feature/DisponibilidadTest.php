<?php

namespace Tests\Feature;

use App\Domain\GeneradorFranjas;
use App\Enums\EstadoCiclo;
use App\Enums\EstadoDisponibilidad;
use App\Models\Franja;
use App\Models\PlantillaHoraria;
use App\Models\Profesor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ActuaComoAdmin;
use Tests\TestCase;

class DisponibilidadTest extends TestCase
{
    use ActuaComoAdmin, RefreshDatabase;

    /**
     * Lunes y martes, 3 franjas de 50 min desde las 07:00.
     */
    private function plantillaConFranjas(): PlantillaHoraria
    {
        $plantilla = PlantillaHoraria::factory()->create(['nombre' => 'Matutino']);
        $plantilla->franjas()->createMany((new GeneradorFranjas)->generar([0, 1], '07:00', 3, 50));

        return $plantilla;
    }

    /**
     * @return array<int, string>
     */
    private function estados(Profesor $profesor): array
    {
        return $profesor->disponibilidades()->get()->mapWithKeys(fn ($d) => [$d->franja_id => $d->estado->value])->all();
    }

    public function test_guardar_disponibilidad_solo_guarda_p_y_n(): void
    {
        $this->comoAdmin();
        $plantilla = $this->plantillaConFranjas();
        [$f0, $f1, $f2] = $plantilla->franjas()->where('dia', 0)->orderBy('orden')->get()->all();
        $profesor = Profesor::factory()->create();
        $profesor->disponibilidades()->create(['franja_id' => $f2->id, 'estado' => EstadoDisponibilidad::NoDisponible]);

        $this->get(route('profesores.disponibilidad', $profesor))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('escuela/profesores/Disponibilidad')
                ->has('plantillas', 1)
                ->has('plantillas.0.franjas', 6)
                ->where("estados.{$f2->id}", 'N'));

        $this->put(route('profesores.guardar-disponibilidad', $profesor), ['celdas' => [
            ['franja_id' => $f0->id, 'estado' => 'P'],
            ['franja_id' => $f1->id, 'estado' => 'N'],
            ['franja_id' => $f2->id, 'estado' => 'D'],
        ]])->assertRedirect(route('profesores.disponibilidad', $profesor));

        $this->assertSame([$f0->id => 'preferente', $f1->id => 'no_disponible'], $this->estados($profesor));
    }

    public function test_franja_de_otra_escuela_da_422(): void
    {
        $this->comoAdmin();
        $profesor = Profesor::factory()->create();
        $ajena = $this->enOtraEscuela(fn () => Franja::factory()->create());

        $this->put(route('profesores.guardar-disponibilidad', $profesor), ['celdas' => [['franja_id' => $ajena->id, 'estado' => 'N']]])
            ->assertSessionHasErrors('celdas.0.franja_id');
        $this->put(route('profesores.guardar-disponibilidad', $profesor), ['celdas' => [['franja_id' => $ajena->id, 'estado' => 'X']]])
            ->assertSessionHasErrors('celdas.0.estado');

        $this->assertSame([], $this->estados($profesor));
    }

    public function test_profesor_edita_su_disponibilidad_en_captura(): void
    {
        $admin = $this->comoAdmin();
        $this->conCicloActual();
        $franja = $this->plantillaConFranjas()->franjas()->first();
        $usuario = User::factory()->profesor()->create(['escuela_id' => $admin->escuela_id]);
        $profesor = Profesor::factory()->create(['user_id' => $usuario->id, 'nombre' => 'Ana López']);

        $this->actingAs($usuario)->get(route('mi-disponibilidad'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('MiDisponibilidad')
                ->where('profesor.nombre', 'Ana López')
                ->where('cerrada', false)
                ->has('plantillas', 1));

        $this->actingAs($usuario)->put(route('guardar-mi-disponibilidad'), ['celdas' => [['franja_id' => $franja->id, 'estado' => 'N']]])
            ->assertRedirect(route('mi-disponibilidad'));

        $this->assertSame([$franja->id => 'no_disponible'], $this->estados($profesor));
    }

    public function test_profesor_no_edita_con_ciclo_cerrado(): void
    {
        $admin = $this->comoAdmin();
        $this->conCicloActual()->update(['estado' => EstadoCiclo::Cerrado]);
        $franja = $this->plantillaConFranjas()->franjas()->first();
        $usuario = User::factory()->profesor()->create(['escuela_id' => $admin->escuela_id]);
        $profesor = Profesor::factory()->create(['user_id' => $usuario->id]);

        $this->actingAs($usuario)->get(route('mi-disponibilidad'))
            ->assertInertia(fn (Assert $page) => $page->where('cerrada', true));
        $this->actingAs($usuario)->put(route('guardar-mi-disponibilidad'), ['celdas' => [['franja_id' => $franja->id, 'estado' => 'N']]])
            ->assertForbidden()
            ->assertSee('La captura de disponibilidad está cerrada.');

        $this->assertSame([], $this->estados($profesor));
    }

    public function test_profesor_no_ve_la_de_otro(): void
    {
        $admin = $this->comoAdmin();
        $usuario = User::factory()->profesor()->create(['escuela_id' => $admin->escuela_id]);
        Profesor::factory()->create(['user_id' => $usuario->id]);
        $otro = Profesor::factory()->create();

        $this->actingAs($usuario)->get(route('profesores.disponibilidad', $otro))->assertForbidden();
        $this->actingAs($usuario)->put(route('profesores.guardar-disponibilidad', $otro), ['celdas' => []])->assertForbidden();
    }

    public function test_mi_disponibilidad_sin_profesor_vinculado(): void
    {
        $admin = $this->comoAdmin();
        $usuario = User::factory()->profesor()->create(['escuela_id' => $admin->escuela_id]);

        $this->actingAs($usuario)->get(route('mi-disponibilidad'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('profesor', null)->has('plantillas', 0));
        $this->actingAs($usuario)->put(route('guardar-mi-disponibilidad'), ['celdas' => []])->assertNotFound();
    }

    public function test_borrar_franja_con_disponibilidad_se_bloquea(): void
    {
        $this->comoAdmin();
        $franja = $this->plantillaConFranjas()->franjas()->where('dia', 0)->where('orden', 1)->first();
        Profesor::factory()->count(2)->create()->each(
            fn (Profesor $p) => $p->disponibilidades()->create(['franja_id' => $franja->id, 'estado' => EstadoDisponibilidad::Preferente]),
        );

        $this->delete(route('franjas.destroy', $franja))
            ->assertSessionHasErrors(['borrar' => 'No se puede borrar «Lun 1»: lo usan 2 profesores con disponibilidad.']);

        $this->assertModelExists($franja);
    }

    public function test_generar_franjas_no_pisa_disponibilidad(): void
    {
        $this->comoAdmin();
        $plantilla = $this->plantillaConFranjas();
        Profesor::factory()->create()->disponibilidades()->create([
            'franja_id' => $plantilla->franjas()->first()->id, 'estado' => EstadoDisponibilidad::NoDisponible,
        ]);

        $this->post(route('plantillas.generar', $plantilla), ['dias' => [0], 'inicio' => '08:00', 'numero' => 2, 'duracion' => 60])
            ->assertSessionHasErrors(['generar' => 'La plantilla ya tiene disponibilidad capturada; edita las franjas una por una.']);

        $this->assertSame(6, $plantilla->franjas()->count());
    }

    public function test_borrar_plantilla_con_disponibilidad_se_bloquea(): void
    {
        $this->comoAdmin();
        $plantilla = $this->plantillaConFranjas();
        Profesor::factory()->create()->disponibilidades()->create([
            'franja_id' => $plantilla->franjas()->first()->id, 'estado' => EstadoDisponibilidad::NoDisponible,
        ]);

        $this->delete(route('plantillas.destroy', $plantilla))
            ->assertSessionHasErrors(['borrar' => 'No se puede borrar «Matutino»: lo usa 1 profesor con disponibilidad.']);

        $this->assertModelExists($plantilla);
        $this->assertSame(6, $plantilla->franjas()->count());
    }
}
