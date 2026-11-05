<?php

namespace Tests\Feature;

use App\Domain\ClaveCurso;
use App\Models\Ciclo;
use App\Models\Curso;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Plantel;
use App\Models\Profesor;
use App\Models\Sincronia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ActuaComoAdmin;
use Tests\TestCase;

class CursosTest extends TestCase
{
    use ActuaComoAdmin, RefreshDatabase;

    private Ciclo $ciclo;

    private function preparar(): void
    {
        $this->comoAdmin();
        $this->ciclo = $this->conCicloActual();
    }

    private function grupo(string $clave, array $extra = []): Grupo
    {
        return Grupo::factory()->for($this->ciclo)->create(['clave' => $clave, ...$extra]);
    }

    public function test_crud_de_cursos(): void
    {
        $this->preparar();
        $grupo = $this->grupo('1A');
        Grupo::factory()->for(Ciclo::factory())->create(['clave' => 'VIEJO']);
        $materia = Materia::factory()->create(['clave' => 'MAT1', 'nombre' => 'Matemáticas I']);
        $profesor = Profesor::factory()->create(['nombre' => 'Ana López']);

        $this->get(route('cursos.create'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('escuela/cursos/Form')
                ->where('curso', null)
                ->has('materias', 1)
                ->has('grupos', 1)
                ->has('profesores', 1)
                ->has('sincronias', 0));

        $this->post(route('cursos.store'), ['materia_id' => $materia->id, 'grupos' => [$grupo->id], 'profesor_id' => $profesor->id, 'clave' => ''])
            ->assertRedirect(route('cursos.index'));
        $curso = Curso::firstWhere('clave', '1A-MAT1');
        $this->assertSame($this->ciclo->id, $curso->ciclo_id);
        $this->assertSame([$grupo->id], $curso->grupos()->pluck('grupos.id')->all());

        $this->get(route('cursos.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('escuela/cursos/Index')
                ->has('cursos', 1)
                ->where('cursos.0.clave', '1A-MAT1')
                ->where('cursos.0.materia', 'Matemáticas I')
                ->where('cursos.0.grupos', '1A')
                ->where('cursos.0.profesor', 'Ana López'));
        $this->get(route('cursos.edit', $curso))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('curso.grupos', [$grupo->id])->where('curso.clave', '1A-MAT1'));

        $this->put(route('cursos.update', $curso), ['materia_id' => $materia->id, 'grupos' => [$grupo->id], 'vacante' => '1', 'clave' => ''])
            ->assertRedirect(route('cursos.index'));
        $this->assertTrue($curso->fresh()->vacante);
        $this->assertNull($curso->fresh()->profesor_id);
        $this->assertSame('1A-MAT1', $curso->fresh()->clave);

        $this->delete(route('cursos.destroy', $curso))->assertRedirect(route('cursos.index'));
        $this->assertModelMissing($curso);
        $this->assertModelExists($grupo);
    }

    public function test_clave_se_genera_y_no_choca(): void
    {
        $this->preparar();
        $grupo = $this->grupo('1A');
        $materia = Materia::factory()->create(['clave' => 'MAT1']);

        $this->post(route('cursos.store'), ['materia_id' => $materia->id, 'grupos' => [$grupo->id]])->assertSessionHasNoErrors();
        $this->post(route('cursos.store'), ['materia_id' => $materia->id, 'grupos' => [$grupo->id]])->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(['1A-MAT1', '1A-MAT1-2'], Curso::pluck('clave')->all());
        $this->assertSame('1A-MAT1-3', ClaveCurso::generar([$grupo], $materia, $this->ciclo->id));
        $this->assertSame('1A-MAT1-4', ClaveCurso::generar([$grupo], $materia, $this->ciclo->id, ['1A-MAT1-3']));
    }

    public function test_clave_con_varios_grupos_en_orden(): void
    {
        $this->preparar();
        $plantel = Plantel::factory()->create();
        $b = $this->grupo('1B', ['plantel_id' => $plantel->id]);
        $a = $this->grupo('1A', ['plantel_id' => $plantel->id, 'plantilla_id' => $b->plantilla_id]);
        $materia = Materia::factory()->create(['clave' => 'ING1']);

        $this->assertSame('1A+1B-ING1', ClaveCurso::generar([$b, $a], $materia, $this->ciclo->id));
    }

    public function test_grupos_de_distinto_plantel_da_422(): void
    {
        $this->preparar();
        $a = $this->grupo('1A');
        $b = $this->grupo('1B', ['plantilla_id' => $a->plantilla_id]);

        $this->post(route('cursos.store'), ['materia_id' => Materia::factory()->create()->id, 'grupos' => [$a->id, $b->id]])
            ->assertSessionHasErrors(['grupos' => 'Los grupos de un curso deben ser del mismo plantel y la misma plantilla.']);

        $this->assertSame(0, Curso::count());
    }

    public function test_grupo_repetido_da_422(): void
    {
        $this->preparar();
        $grupo = $this->grupo('1A');

        $this->post(route('cursos.store'), ['materia_id' => Materia::factory()->create()->id, 'grupos' => [$grupo->id, $grupo->id]])
            ->assertSessionHasErrors(['grupos.1' => 'Un grupo no puede repetirse en el mismo curso.']);
        $this->post(route('cursos.store'), ['materia_id' => Materia::factory()->create()->id, 'grupos' => []])
            ->assertSessionHasErrors('grupos');

        $this->assertSame(0, Curso::count());
    }

    public function test_grupo_de_otro_ciclo_da_422(): void
    {
        $this->preparar();
        $viejo = Grupo::factory()->for(Ciclo::factory())->create();

        $this->post(route('cursos.store'), ['materia_id' => Materia::factory()->create()->id, 'grupos' => [$viejo->id]])
            ->assertSessionHasErrors('grupos.0');
    }

    public function test_vacante_con_profesor_da_422(): void
    {
        $this->preparar();

        $this->post(route('cursos.store'), [
            'materia_id' => Materia::factory()->create()->id, 'grupos' => [$this->grupo('1A')->id],
            'vacante' => '1', 'profesor_id' => Profesor::factory()->create()->id,
        ])->assertSessionHasErrors(['profesor_id' => 'Un curso vacante no lleva profesor.']);

        $this->assertSame(0, Curso::count());
    }

    public function test_profesor_de_otra_escuela_da_422(): void
    {
        $this->preparar();
        [$profesor, $materia] = $this->enOtraEscuela(fn () => [Profesor::factory()->create(), Materia::factory()->create()]);
        $grupo = $this->grupo('1A');

        $this->post(route('cursos.store'), ['materia_id' => Materia::factory()->create()->id, 'grupos' => [$grupo->id], 'profesor_id' => $profesor->id])
            ->assertSessionHasErrors('profesor_id');
        $this->post(route('cursos.store'), ['materia_id' => $materia->id, 'grupos' => [$grupo->id]])
            ->assertSessionHasErrors('materia_id');

        $this->assertSame(0, Curso::withoutGlobalScope('escuela')->count());
    }

    public function test_borrar_materia_con_cursos_se_bloquea(): void
    {
        $this->preparar();
        $materia = Materia::factory()->create(['nombre' => 'Matemáticas I']);
        Curso::factory()->for($this->ciclo)->for($materia)->create()->grupos()->attach($this->grupo('1A'));

        $this->delete(route('materias.destroy', $materia))
            ->assertSessionHasErrors(['borrar' => 'No se puede borrar «Matemáticas I»: lo usa 1 curso.']);

        $this->assertModelExists($materia);
    }

    public function test_borrar_grupo_profesor_o_ciclo_con_cursos_se_bloquea(): void
    {
        $this->preparar();
        $grupo = $this->grupo('1A');
        $profesor = Profesor::factory()->create(['nombre' => 'Ana López']);
        Curso::factory()->count(2)->for($this->ciclo)->create(['profesor_id' => $profesor->id])
            ->each(fn (Curso $curso) => $curso->grupos()->attach($grupo));

        $this->delete(route('grupos.destroy', $grupo))
            ->assertSessionHasErrors(['borrar' => 'No se puede borrar «1A»: lo usan 2 cursos.']);
        $this->delete(route('profesores.destroy', $profesor))
            ->assertSessionHasErrors(['borrar' => 'No se puede borrar «Ana López»: lo usan 2 cursos.']);
        $this->delete(route('ciclos.destroy', $this->ciclo))
            ->assertSessionHasErrors(['borrar' => "No se puede borrar «{$this->ciclo->clave}»: lo usan 2 cursos."]);

        $this->assertModelExists($grupo);
        $this->assertModelExists($profesor);
    }

    public function test_sincronia_agrupa_cursos(): void
    {
        $this->preparar();

        $this->post(route('sincronias.store'), ['clave' => 'ING-1', 'nombre' => 'Inglés nivel 1'])->assertRedirect(route('sincronias.index'));
        $sincronia = Sincronia::firstWhere('clave', 'ING-1');
        $this->assertSame($this->ciclo->id, $sincronia->ciclo_id);
        $materia = Materia::factory()->create(['clave' => 'ING1']);
        foreach (['1A', '1B'] as $clave) {
            $this->post(route('cursos.store'), ['materia_id' => $materia->id, 'grupos' => [$this->grupo($clave)->id], 'sincronia_id' => $sincronia->id])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(2, $sincronia->cursos()->count());
        $this->get(route('sincronias.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('escuela/sincronias/Index')
                ->where('sincronias.0.cursos', ['1A-ING1', '1B-ING1']));

        $this->delete(route('sincronias.destroy', $sincronia))->assertRedirect(route('sincronias.index'));
        $this->assertSame(0, Curso::whereNotNull('sincronia_id')->count());
        $this->assertSame(2, Curso::count());
    }

    public function test_sincronia_de_otro_ciclo_da_422(): void
    {
        $this->preparar();
        $vieja = Sincronia::factory()->for(Ciclo::factory())->create();

        $this->post(route('cursos.store'), ['materia_id' => Materia::factory()->create()->id, 'grupos' => [$this->grupo('1A')->id], 'sincronia_id' => $vieja->id])
            ->assertSessionHasErrors('sincronia_id');
    }

    public function test_filtrar_cursos_por_grupo_y_materia(): void
    {
        $this->preparar();
        [$a, $b] = [$this->grupo('1A'), $this->grupo('1B')];
        [$mate, $fisica] = [Materia::factory()->create(['clave' => 'MAT1']), Materia::factory()->create(['clave' => 'FIS1'])];
        foreach ([[$a, $mate], [$a, $fisica], [$b, $mate]] as [$grupo, $materia]) {
            $this->post(route('cursos.store'), ['materia_id' => $materia->id, 'grupos' => [$grupo->id]]);
        }

        $this->get(route('cursos.index', ['grupo' => $a->id]))
            ->assertInertia(fn (Assert $page) => $page->has('cursos', 2)->where('filtros.grupo', $a->id));
        $this->get(route('cursos.index', ['materia' => $mate->id]))
            ->assertInertia(fn (Assert $page) => $page->has('cursos', 2));
        $this->get(route('cursos.index', ['grupo' => $a->id, 'materia' => $mate->id]))
            ->assertInertia(fn (Assert $page) => $page->has('cursos', 1)->where('cursos.0.clave', '1A-MAT1'));
    }

    public function test_cursos_y_sincronias_sin_ciclo_actual_redirigen(): void
    {
        $this->comoAdmin();

        $this->get(route('cursos.index'))->assertRedirect(route('ciclos.index'));
        $this->get(route('sincronias.index'))->assertRedirect(route('ciclos.index'));
    }
}
