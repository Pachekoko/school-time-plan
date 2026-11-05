<?php

namespace Tests\Feature;

use App\Models\Ciclo;
use App\Models\Curso;
use App\Models\Escuela;
use App\Models\Grupo;
use App\Models\Materia;
use App\Solver\ConstructorProblema;
use App\Solver\ParametrosCorrida;
use App\Support\EscuelaActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\ValidationResult;
use Opis\JsonSchema\Validator;
use Tests\Support\EscuelaDePrueba;
use Tests\TestCase;

class ConstructorProblemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function problema(?Ciclo $ciclo = null): array
    {
        $ciclo ??= EscuelaDePrueba::crear()['ciclo'];

        return app(ConstructorProblema::class)->construir($ciclo, ParametrosCorrida::normalizar([], $ciclo->escuela));
    }

    private function validar(mixed $problema): ValidationResult
    {
        $schema = json_decode((string) file_get_contents(base_path('../../contracts/solve-request.schema.json')));

        return (new Validator)->validate(json_decode((string) json_encode($problema)), $schema);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lista
     * @return array<string, array<string, mixed>>
     */
    private function porId(array $lista): array
    {
        return array_column($lista, null, 'id');
    }

    public function test_el_problema_cumple_el_json_schema(): void
    {
        $resultado = $this->validar($this->problema());

        $this->assertTrue($resultado->isValid(), json_encode($resultado->isValid() ? [] : (new ErrorFormatter)->format($resultado->error())));
    }

    public function test_el_schema_rechaza_un_problema_sin_version(): void
    {
        $problema = $this->problema();
        unset($problema['schema_version']);

        $this->assertFalse($this->validar($problema)->isValid());
        $this->assertFalse($this->validar([...$this->problema(), 'extra' => true])->isValid());
    }

    public function test_ids_son_cadenas_y_las_referencias_existen(): void
    {
        $p = $this->problema();
        $ids = fn (string $coleccion) => array_column($p[$coleccion], 'id');

        foreach (['plantillas', 'planteles', 'espacios', 'materias', 'grupos', 'profesores', 'cursos'] as $coleccion) {
            $this->assertNotEmpty($p[$coleccion], $coleccion);
            $this->assertContainsOnly('string', $ids($coleccion), message: $coleccion);
        }

        foreach ($p['espacios'] as $espacio) {
            $this->assertContains($espacio['plantel'], $ids('planteles'));
        }
        foreach ($p['grupos'] as $grupo) {
            $this->assertContains($grupo['plantel'], $ids('planteles'));
            $this->assertContains($grupo['plantilla'], $ids('plantillas'));
        }
        foreach ($p['cursos'] as $curso) {
            $this->assertContains($curso['materia'], $ids('materias'));
            $this->assertEmpty(array_diff($curso['grupos'], $ids('grupos')));
            if ($curso['profesor'] !== null) {
                $this->assertContains($curso['profesor'], $ids('profesores'));
            }
        }
        foreach ($p['profesores'] as $profesor) {
            $this->assertEmpty(array_diff($profesor['planteles'], $ids('planteles')));
            $this->assertEmpty(array_diff($profesor['materias'], $ids('materias')));
        }
    }

    public function test_disponibilidad_como_ref_franja(): void
    {
        $escuela = EscuelaDePrueba::crear();
        $p = $this->problema($escuela['ciclo']);
        $ana = $this->porId($p['profesores'])[(string) $escuela['profesores']['P1']->id];
        $plantilla = (string) $escuela['plantilla']->id;

        $this->assertSame([['plantilla' => $plantilla, 'dia' => 0, 'orden' => 0]], $ana['no_disponible']);
        $this->assertSame([['plantilla' => $plantilla, 'dia' => 1, 'orden' => 1]], $ana['preferente']);
        $this->assertTrue($ana['sin_huecos']);
        $this->assertSame([], $this->porId($p['profesores'])[(string) $escuela['profesores']['P2']->id]['no_disponible']);

        $franjas = $this->porId($p['plantillas'])[$plantilla]['franjas'];
        $this->assertCount(20, $franjas);
        $this->assertSame(['dia' => 0, 'orden' => 0, 'inicio' => '07:00', 'fin' => '07:50', 'tipo' => 'clase'], $franjas[0]);
    }

    public function test_patrones_derivados_cuando_no_hay(): void
    {
        $escuela = EscuelaDePrueba::crear();
        $materias = $this->porId($this->problema($escuela['ciclo'])['materias']);

        $this->assertSame([[2, 1]], $materias[(string) $escuela['materias']['MAT1']->id]['patrones']);
        $this->assertSame([[2], [1, 1]], $materias[(string) $escuela['materias']['QUI1']->id]['patrones']);
        $this->assertSame('laboratorio', $materias[(string) $escuela['materias']['QUI1']->id]['tipo_espacio']);
    }

    public function test_vacante_va_sin_profesor_y_sincronia_con_su_id(): void
    {
        $escuela = EscuelaDePrueba::crear();
        $cursos = $this->porId($this->problema($escuela['ciclo'])['cursos']);
        $id = fn (string $clave) => (string) $escuela['cursos'][$clave]->id;

        $this->assertTrue($cursos[$id('1A-QUI1')]['vacante']);
        $this->assertNull($cursos[$id('1A-QUI1')]['profesor']);
        $this->assertSame((string) $escuela['profesores']['P1']->id, $cursos[$id('1A-MAT1')]['profesor']);
        $this->assertSame((string) $escuela['sincronia']->id, $cursos[$id('1A-ING1')]['sincronia']);
        $this->assertSame((string) $escuela['sincronia']->id, $cursos[$id('1B-ING1')]['sincronia']);
        $this->assertNull($cursos[$id('1A-MAT1')]['sincronia']);
        $this->assertSame([(string) $escuela['grupos']['1B']->id], $cursos[$id('1B-ING1')]['grupos']);
    }

    public function test_solo_grupos_y_cursos_del_ciclo_y_nada_de_otra_escuela(): void
    {
        $escuela = EscuelaDePrueba::crear();
        $otroCiclo = Ciclo::factory()->create();
        $viejo = Grupo::factory()->for($otroCiclo)->create();
        Curso::factory()->for($otroCiclo)->for($escuela['materias']['MAT1'])->create()->grupos()->attach($viejo);
        $p = $this->problema($escuela['ciclo']);

        $this->assertCount(2, $p['grupos']);
        $this->assertCount(4, $p['cursos']);
        $this->assertSame([], $p['fijadas']);
        $this->assertSame('1A', $this->porId($p['grupos'])[(string) $escuela['grupos']['1A']->id]['nombre']);

        $otra = EscuelaDePrueba::crear();
        $deLaPrimera = $this->problema($escuela['ciclo']);
        $this->assertNotContains((string) $otra['materias']['MAT1']->id, array_column($deLaPrimera['materias'], 'id'));
        $this->assertCount(3, $deLaPrimera['materias']);
    }

    public function test_reglas_por_defecto_y_s3_no_obligatoria(): void
    {
        $escuela = EscuelaDePrueba::crear()['escuela'];
        $escuela->update(['minutos_traslado' => 30, 'tiempo_limite_s' => 90]);
        config(['solver.workers' => 4]);

        $parametros = ParametrosCorrida::normalizar([], $escuela);

        $this->assertSame(90, $parametros['tiempo_limite_s']);
        $this->assertSame(0, $parametros['semilla']);
        $this->assertSame(4, $parametros['num_workers']);
        $this->assertFalse($parametros['permitir_parcial']);
        $this->assertSame(30, $parametros['minutos_traslado']);
        $this->assertSame([
            'S1' => ['modo' => 'obligatoria', 'peso' => 5],
            'S2' => ['modo' => 'preferencia', 'peso' => 7],
            'S3' => ['modo' => 'preferencia', 'peso' => 5],
            'S4' => ['modo' => 'preferencia', 'peso' => 4],
            'S5' => ['modo' => 'preferencia', 'peso' => 3],
            'S6' => ['modo' => 'preferencia', 'peso' => 5],
            'S6_restricciones' => [],
            'S7' => ['modo' => 'obligatoria', 'peso' => 5],
        ], $parametros['reglas']);

        $cambiados = ParametrosCorrida::normalizar(['tiempo_limite_s' => 120, 'permitir_parcial' => true, 'reglas' => ['S2' => ['modo' => 'apagada', 'peso' => 2]]], $escuela);
        $this->assertSame(120, $cambiados['tiempo_limite_s']);
        $this->assertTrue($cambiados['permitir_parcial']);
        $this->assertSame(['modo' => 'apagada', 'peso' => 2], $cambiados['reglas']['S2']);
        $this->assertSame(['modo' => 'preferencia', 'peso' => 4], $cambiados['reglas']['S4']);

        try {
            ParametrosCorrida::normalizar(['reglas' => ['S3' => ['modo' => 'obligatoria', 'peso' => 5]]], $escuela);
            $this->fail('S3 obligatoria debió rechazarse');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reglas.S3.modo', $e->errors());
        }

        $this->expectException(ValidationException::class);
        ParametrosCorrida::normalizar(['tiempo_limite_s' => 301], $escuela);
    }

    public function test_el_problema_no_depende_de_la_escuela_actual(): void
    {
        $escuela = EscuelaDePrueba::crear();
        app(EscuelaActual::class)->establecer(null);

        $p = $this->problema($escuela['ciclo']);

        $this->assertCount(3, $p['materias']);
        $this->assertNull(app(EscuelaActual::class)->id());
        $this->assertSame(0, Materia::count());
    }

    public function test_una_escuela_recien_creada_usa_los_valores_por_omision(): void
    {
        $parametros = ParametrosCorrida::normalizar([], Escuela::factory()->create());

        $this->assertSame(50, $parametros['minutos_traslado']);
        $this->assertSame(60, $parametros['tiempo_limite_s']);
    }
}
