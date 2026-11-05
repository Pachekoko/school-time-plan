<?php

namespace Tests\Feature;

use App\Enums\EstadoCorrida;
use App\Jobs\ResolverCorrida;
use App\Models\Ciclo;
use App\Models\Corrida;
use App\Models\Curso;
use App\Solver\GuardarResultado;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Tests\Support\ActuaComoAdmin;
use Tests\Support\EscuelaDePrueba;
use Tests\Support\RespuestaFalsa;
use Tests\TestCase;

class CorridasTest extends TestCase
{
    use ActuaComoAdmin, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['solver.url' => 'http://motor.test', 'solver.token' => 'dev', 'solver.espera_s' => 90]);
        Sleep::fake();
    }

    /**
     * @return array<string, mixed>
     */
    private function preparar(): array
    {
        $escuela = EscuelaDePrueba::crear();
        $this->actingAs($escuela['admin']);

        return $escuela;
    }

    /**
     * @param  array<string, mixed>  $solve
     */
    private function motor(array $solve, int $estado = 200): void
    {
        Http::fake([
            'motor.test/health' => Http::response(['status' => 'ok']),
            'motor.test/solve' => Http::response($solve, $estado),
        ]);
    }

    /**
     * Como el worker de producción: la cola database y un intento por llamada.
     */
    private function trabajarUnaVez(): void
    {
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--stop-when-empty' => true]);
    }

    public function test_flujo_completo_con_motor_falso(): void
    {
        $e = $this->preparar();
        $respuestaOk = RespuestaFalsa::ok($e['ciclo']);
        $this->motor($respuestaOk);

        $this->post(route('corridas.store'), ['tiempo_limite_s' => 10])->assertRedirect();

        $c = Corrida::sole();
        $this->assertSame(EstadoCorrida::Ok, $c->estado);
        $this->assertSame(10, $c->tiempo_limite_s);
        $this->assertSame(12, $c->puntaje);
        $this->assertNotNull($c->iniciada_en);
        $this->assertNotNull($c->terminada_en);
        $this->assertSame('OPTIMAL', $c->estadisticas['estado_cp_sat']);
        $this->assertSame(count($respuestaOk['sesiones']), $c->sesiones()->count());
        $mate = $c->sesiones()->where('curso_id', $e['cursos']['1A-MAT1']->id)->orderBy('bloque')->pluck('duracion')->all();
        $this->assertSame([2, 1], $mate);
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer dev') && $r['schema_version'] === '1'
            && $r['parametros']['tiempo_limite_s'] === 10);
    }

    public function test_motor_dormido_se_espera(): void
    {
        $e = $this->preparar();
        Http::fake([
            'motor.test/health' => Http::sequence()->push([], 503)->push([], 503)->push(['status' => 'ok']),
            'motor.test/solve' => Http::response(RespuestaFalsa::ok($e['ciclo'])),
        ]);

        $this->post(route('corridas.store'))->assertRedirect();

        $this->assertSame(EstadoCorrida::Ok, Corrida::sole()->estado);
        Sleep::assertSleptTimes(2);
    }

    public function test_motor_caido_deja_error_con_mensaje(): void
    {
        $this->preparar();
        config(['queue.default' => 'database']);
        Http::fake(['motor.test/*' => Http::response([], 503)]);

        $this->post(route('corridas.store'))->assertRedirect();
        $c = Corrida::sole();

        $this->trabajarUnaVez();
        $this->assertSame(EstadoCorrida::Resolviendo, $c->fresh()->estado);
        $this->travel(11)->seconds();
        $this->trabajarUnaVez();
        $this->assertSame(EstadoCorrida::Resolviendo, $c->fresh()->estado);
        $this->travel(31)->seconds();
        $this->trabajarUnaVez();

        $this->assertSame(EstadoCorrida::Error, $c->fresh()->estado);
        $this->assertSame('No se pudo conectar con el motor de horarios. Reintenta en unos minutos.', $c->fresh()->mensaje_error);
        $this->assertSame(1, DB::table('failed_jobs')->count());
    }

    public function test_contrato_rechazado_no_reintenta(): void
    {
        $this->preparar();
        config(['queue.default' => 'database']);
        $this->motor(['detail' => [['loc' => ['body', 'grupos', 0, 'alumnos'], 'msg' => 'Input should be greater than or equal to 1']]], 422);

        $this->post(route('corridas.store'))->assertRedirect();
        $this->trabajarUnaVez();

        $c = Corrida::sole();
        $this->assertSame(EstadoCorrida::Error, $c->estado);
        $this->assertSame('El motor rechazó el problema: grupos.0.alumnos: Input should be greater than or equal to 1', $c->mensaje_error);
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(1, DB::table('failed_jobs')->count());
    }

    public function test_sin_solucion_guarda_diagnostico(): void
    {
        $this->preparar();
        $diagnostico = [['codigo' => 'NUCLEO', 'regla' => 'H4', 'entidades' => [['tipo' => 'plantel', 'id' => '1']], 'mensaje' => 'No alcanzan las aulas.']];
        $this->motor(RespuestaFalsa::sinSolucion($diagnostico));

        $this->post(route('corridas.store'))->assertRedirect();

        $c = Corrida::sole();
        $this->assertSame(EstadoCorrida::SinSolucion, $c->estado);
        $this->assertSame($diagnostico, $c->diagnostico);
        $this->assertSame(0, $c->sesiones()->count());
    }

    public function test_parcial_guarda_sin_acomodar(): void
    {
        $e = $this->preparar();
        $this->motor(RespuestaFalsa::parcial($e['ciclo']));

        $this->post(route('corridas.store'))->assertRedirect();

        $c = Corrida::sole();
        $this->assertSame(EstadoCorrida::FactibleParcial, $c->estado);
        $sinAcomodar = $c->sesiones()->where('sin_acomodar', true)->get();
        $this->assertNotEmpty($sinAcomodar);
        $this->assertNull($sinAcomodar->first()->dia);
        $this->assertNull($sinAcomodar->first()->profesor_id);
    }

    public function test_guardar_dos_veces_no_duplica_sesiones(): void
    {
        $e = $this->preparar();
        $respuesta = RespuestaFalsa::ok($e['ciclo']);
        $this->motor($respuesta);
        $this->post(route('corridas.store'))->assertRedirect();
        $c = Corrida::sole();

        app(GuardarResultado::class)->guardar($c, $respuesta);

        $this->assertSame(count($respuesta['sesiones']), $c->sesiones()->count());
    }

    public function test_un_curso_borrado_mientras_resuelve_se_omite(): void
    {
        $e = $this->preparar();
        $respuesta = RespuestaFalsa::ok($e['ciclo']);
        Queue::fake();
        $this->post(route('corridas.store'))->assertRedirect();
        $c = Corrida::sole();
        $e['cursos']['1B-ING1']->delete();

        app(GuardarResultado::class)->guardar($c, $respuesta);

        $this->assertSame(0, $c->sesiones()->where('curso_id', $e['cursos']['1B-ING1']->id)->count());
        $this->assertSame(count($respuesta['sesiones']) - 1, $c->sesiones()->count());
    }

    public function test_solo_una_corrida_activa_por_ciclo(): void
    {
        $this->preparar();
        Queue::fake();

        $this->post(route('corridas.store'))->assertRedirect();
        $this->post(route('corridas.store'))
            ->assertSessionHasErrors(['corrida' => 'Ya hay una corrida en curso para este ciclo.']);

        $this->assertSame(1, Corrida::count());
        Queue::assertPushed(ResolverCorrida::class, 1);
    }

    public function test_indice_unico_impide_dos_activas(): void
    {
        $e = $this->preparar();
        $datos = ['estado' => EstadoCorrida::Pendiente, 'parametros' => [], 'tiempo_limite_s' => 60];
        $e['ciclo']->corridas()->create([...$datos, 'estado' => EstadoCorrida::Ok]);
        $e['ciclo']->corridas()->create($datos);

        $this->expectException(QueryException::class);
        $e['ciclo']->corridas()->create([...$datos, 'estado' => EstadoCorrida::Resolviendo]);
    }

    public function test_parametros_invalidos_no_crean_corrida(): void
    {
        $this->preparar();
        Queue::fake();

        $this->post(route('corridas.store'), ['reglas' => ['S3' => ['modo' => 'obligatoria']]])->assertSessionHasErrors('reglas.S3.modo');
        $this->post(route('corridas.store'), ['tiempo_limite_s' => 0])->assertSessionHasErrors('tiempo_limite_s');

        $this->assertSame(0, Corrida::count());
    }

    public function test_reintentar_desde_error(): void
    {
        $e = $this->preparar();
        Queue::fake();
        $corrida = $e['ciclo']->corridas()->create([
            'estado' => EstadoCorrida::Error, 'parametros' => [], 'tiempo_limite_s' => 60, 'mensaje_error' => 'No se pudo conectar…',
        ]);
        $lista = $e['ciclo']->corridas()->create(['estado' => EstadoCorrida::Ok, 'parametros' => [], 'tiempo_limite_s' => 60]);

        $this->post(route('corridas.reintentar', $lista))
            ->assertSessionHasErrors(['corrida' => 'Solo se puede reintentar una corrida con error.']);
        $this->post(route('corridas.reintentar', $corrida))->assertRedirect(route('corridas.show', $corrida));

        $this->assertSame(EstadoCorrida::Pendiente, $corrida->fresh()->estado);
        $this->assertNull($corrida->fresh()->mensaje_error);
        Queue::assertPushed(ResolverCorrida::class, fn (ResolverCorrida $job) => $job->corridaId === $corrida->id);
    }

    public function test_corrida_de_otra_escuela_da_404(): void
    {
        $this->preparar();
        $ajena = $this->enOtraEscuela(fn () => Ciclo::factory()->create()->corridas()->create([
            'estado' => EstadoCorrida::Error, 'parametros' => [], 'tiempo_limite_s' => 60,
        ]));

        $this->get(route('corridas.show', $ajena))->assertNotFound();
        $this->post(route('corridas.reintentar', $ajena))->assertNotFound();
    }

    public function test_borrar_ciclo_con_corridas_se_bloquea(): void
    {
        $e = $this->preparar();
        Curso::query()->delete();
        $e['ciclo']->grupos()->each(fn ($g) => $g->delete());
        $e['sincronia']->delete();
        $e['ciclo']->corridas()->create(['estado' => EstadoCorrida::Ok, 'parametros' => [], 'tiempo_limite_s' => 60]);

        $this->delete(route('ciclos.destroy', $e['ciclo']))
            ->assertSessionHasErrors(['borrar' => "No se puede borrar «{$e['ciclo']->clave}»: lo usa 1 corrida."]);
    }

    public function test_job_reintenta_tres_veces_con_espera(): void
    {
        $job = new ResolverCorrida(1);

        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 30], $job->backoff);
        $this->assertSame(1000, $job->timeout);
        $this->assertSame(3040, ResolverCorrida::duracionMaxima());
    }

    public function test_la_cola_no_reparte_una_corrida_que_sigue_resolviendo(): void
    {
        $this->assertGreaterThan((new ResolverCorrida(1))->timeout, config('queue.connections.database.retry_after'));
    }

    public function test_una_corrida_atascada_no_bloquea_el_ciclo(): void
    {
        $e = $this->preparar();
        Queue::fake();
        $atascada = $e['ciclo']->corridas()->create(['estado' => EstadoCorrida::Resolviendo, 'parametros' => [], 'tiempo_limite_s' => 60]);

        $this->travel(ResolverCorrida::duracionMaxima() - 60)->seconds();
        $this->post(route('corridas.store'))->assertSessionHasErrors('corrida');

        $this->travel(120)->seconds();
        $this->post(route('corridas.store'))->assertSessionHasNoErrors();

        $this->assertSame(EstadoCorrida::Error, $atascada->fresh()->estado);
        $this->assertSame('La corrida no avanzó en 51 min y se dio por fallida; puedes reintentarla.', $atascada->fresh()->mensaje_error);
        $this->assertSame(2, Corrida::count());
        Queue::assertPushed(ResolverCorrida::class, 1);
    }
}
