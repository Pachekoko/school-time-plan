<?php

namespace Tests\Unit;

use App\Domain\GeneradorFranjas;
use PHPUnit\Framework\TestCase;

class GeneradorFranjasTest extends TestCase
{
    public function test_receso_ocupa_un_orden(): void
    {
        $f = (new GeneradorFranjas)->generar([0], '07:00', 6, 50, recesoTras: 3);

        $this->assertSame(['clase', 'clase', 'clase', 'receso', 'clase', 'clase', 'clase'], array_column($f, 'tipo'));
        $this->assertSame(range(0, 6), array_column($f, 'orden'));
        $this->assertSame(['09:30', '10:00'], [$f[3]['inicio'], $f[3]['fin']]);
        $this->assertSame(['10:00', '10:50'], [$f[4]['inicio'], $f[4]['fin']]);
    }

    public function test_cada_dia_empieza_en_orden_0_a_la_misma_hora(): void
    {
        $f = (new GeneradorFranjas)->generar([0, 2], '08:00', 2, 60);

        $this->assertSame([
            ['dia' => 0, 'orden' => 0, 'inicio' => '08:00', 'fin' => '09:00', 'tipo' => 'clase'],
            ['dia' => 0, 'orden' => 1, 'inicio' => '09:00', 'fin' => '10:00', 'tipo' => 'clase'],
            ['dia' => 2, 'orden' => 0, 'inicio' => '08:00', 'fin' => '09:00', 'tipo' => 'clase'],
            ['dia' => 2, 'orden' => 1, 'inicio' => '09:00', 'fin' => '10:00', 'tipo' => 'clase'],
        ], $f);
    }

    public function test_receso_con_duracion_propia(): void
    {
        $f = (new GeneradorFranjas)->generar([4], '07:00', 2, 60, recesoTras: 1, recesoMin: 20);

        $this->assertSame(['07:00-08:00', '08:00-08:20', '08:20-09:20'], array_map(fn ($x) => $x['inicio'].'-'.$x['fin'], $f));
    }
}
