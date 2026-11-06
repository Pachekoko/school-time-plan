<?php

namespace Tests\Feature;

use App\Enums\EstadoCorrida;
use App\Guia\PrimerosPasos;
use App\Models\Escuela;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_el_avance_sale_de_una_sola_consulta(): void
    {
        $escuela = EscuelaDePrueba::crear()['escuela']->fresh();
        DB::flushQueryLog();
        DB::enableQueryLog();

        app(PrimerosPasos::class)->hechos($escuela);

        $this->assertCount(1, DB::getQueryLog());
    }
}
