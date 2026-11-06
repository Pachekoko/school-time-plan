<?php

namespace Tests\Feature;

use App\Enums\EstadoCorrida;
use App\Enums\TipoFranja;
use App\Guia\PrimerosPasos;
use App\Models\Ciclo;
use App\Models\Escuela;
use App\Models\Franja;
use App\Support\EscuelaActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EscuelaDePrueba;
use Tests\TestCase;

class PrimerosPasosTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_escuela_vacia_tiene_todo_pendiente(): void
    {
        $hechos = app(PrimerosPasos::class)->hechos(Escuela::factory()->create());

        $this->assertSame(PrimerosPasos::PASOS, array_keys($hechos));
        $this->assertSame([], array_keys(array_filter($hechos)));
    }

    public function test_la_escuela_de_prueba_solo_le_falta_generar(): void
    {
        $e = EscuelaDePrueba::crear();

        $hechos = app(PrimerosPasos::class)->hechos($e['escuela']->fresh());

        $this->assertSame(['generar'], array_keys(array_filter($hechos, fn (bool $h) => ! $h)));
    }

    public function test_una_corrida_con_horario_completa_la_guia_y_una_con_error_no(): void
    {
        $e = EscuelaDePrueba::crear();
        $guia = app(PrimerosPasos::class);
        $datos = ['parametros' => [], 'tiempo_limite_s' => 60];

        $e['ciclo']->corridas()->create([...$datos, 'estado' => EstadoCorrida::Error]);
        $this->assertFalse($guia->hechos($e['escuela']->fresh())['generar']);

        $e['ciclo']->corridas()->create([...$datos, 'estado' => EstadoCorrida::Ok]);
        $this->assertTrue($guia->hechos($e['escuela']->fresh())['generar']);
    }

    public function test_los_datos_de_otra_escuela_no_cuentan(): void
    {
        EscuelaDePrueba::crear();

        $hechos = app(PrimerosPasos::class)->hechos(Escuela::factory()->create());

        $this->assertSame([], array_keys(array_filter($hechos)));
    }

    public function test_otra_escuela_con_su_ciclo_vacio_solo_tiene_el_ciclo(): void
    {
        EscuelaDePrueba::crear();
        $otra = Escuela::factory()->create();
        $ciclo = app(EscuelaActual::class)->usar($otra->id, fn () => Ciclo::factory()->create(['clave' => '2027-B']));
        $otra->forceFill(['ciclo_actual_id' => $ciclo->id])->save();

        $hechos = app(PrimerosPasos::class)->hechos($otra->fresh());

        $this->assertSame(['ciclo'], array_keys(array_filter($hechos)));
    }

    public function test_una_plantilla_solo_con_recesos_no_completa_el_paso(): void
    {
        $escuela = Escuela::factory()->create();
        app(EscuelaActual::class)->usar($escuela->id, fn () => Franja::factory()->create(['tipo' => TipoFranja::Receso]));

        $this->assertFalse(app(PrimerosPasos::class)->hechos($escuela->fresh())['plantilla']);
    }

    public function test_una_corrida_factible_parcial_tambien_completa_la_guia(): void
    {
        $e = EscuelaDePrueba::crear();
        $e['ciclo']->corridas()->create(['parametros' => [], 'tiempo_limite_s' => 60, 'estado' => EstadoCorrida::FactibleParcial]);

        $this->assertTrue(app(PrimerosPasos::class)->hechos($e['escuela']->fresh())['generar']);
    }

    public function test_un_ciclo_actual_nuevo_vuelve_a_dejar_pendientes_los_pasos_del_ciclo(): void
    {
        $e = EscuelaDePrueba::crear();
        $e['ciclo']->corridas()->create(['parametros' => [], 'tiempo_limite_s' => 60, 'estado' => EstadoCorrida::Ok]);
        $nuevo = Ciclo::factory()->create(['clave' => '2027-B']);
        $e['escuela']->forceFill(['ciclo_actual_id' => $nuevo->id])->save();

        $hechos = app(PrimerosPasos::class)->hechos($e['escuela']->fresh());

        $this->assertSame(['grupos', 'carga', 'generar'], array_keys(array_filter($hechos, fn (bool $h) => ! $h)));
    }

    public function test_franjas_tiene_indice_por_escuela_y_tipo(): void
    {
        $this->assertTrue(Schema::hasIndex('franjas', ['escuela_id', 'tipo']));
    }

    public function test_el_avance_sale_de_una_sola_consulta(): void
    {
        $escuela = EscuelaDePrueba::crear()['escuela']->fresh();
        DB::flushQueryLog();
        DB::enableQueryLog();

        app(PrimerosPasos::class)->hechos($escuela);

        $this->assertCount(1, DB::getQueryLog());
    }
}
