<?php

namespace Tests\Unit;

use App\Excel\Normalizador;
use App\Excel\ValorInvalido;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class NormalizadorTest extends TestCase
{
    public function test_celdas_reales(): void
    {
        $this->assertSame(3, Normalizador::entero(3.0));
        $this->assertSame('07:00', Normalizador::hora(0.2916666667));
        $this->assertSame(0, Normalizador::dia('Lunes'));
        $this->assertSame('Aula 1', Normalizador::texto('  Aula   1 '));
    }

    public function test_texto(): void
    {
        $this->assertNull(Normalizador::texto(null));
        $this->assertNull(Normalizador::texto("  \u{00A0} "));
        $this->assertSame('101', Normalizador::texto(101.0));
        $this->assertSame('2.5', Normalizador::texto(2.5));
        $this->assertSame('Química I', Normalizador::texto("Química\u{00A0} I"));
    }

    public function test_entero(): void
    {
        $this->assertSame(3, Normalizador::entero('3'));
        $this->assertSame(3, Normalizador::entero(' 3.0 '));
        $this->assertNull(Normalizador::entero(''));
        $this->expectException(ValorInvalido::class);
        $this->expectExceptionMessage('«3.5» no es un número entero');
        Normalizador::entero(3.5);
    }

    public function test_hora(): void
    {
        $this->assertSame('07:00', Normalizador::hora('7:00'));
        $this->assertSame('13:20', Normalizador::hora('13:20:00'));
        $this->assertSame('07:30', Normalizador::hora(new DateTimeImmutable('2026-01-01 07:30')));
        $this->assertSame('07:50', Normalizador::hora(45000.3263888889));
        $this->assertNull(Normalizador::hora(null));
        $this->expectException(ValorInvalido::class);
        Normalizador::hora('25:00');
    }

    public function test_dia(): void
    {
        foreach (['Lun' => 0, 'LUN' => 0, 'lunes' => 0, 'Mié' => 2, 'mie' => 2, 'Miércoles' => 2, 'sáb' => 5, 'Domingo' => 6] as $texto => $dia) {
            $this->assertSame($dia, Normalizador::dia($texto), $texto);
        }

        $this->expectException(ValorInvalido::class);
        $this->expectExceptionMessage('«marzo» no es un día');
        Normalizador::dia('marzo');
    }

    public function test_si_no_y_disponibilidad(): void
    {
        foreach (['S', 'Sí', 'si', 'SI', '1', 1, true, 'true'] as $si) {
            $this->assertTrue(Normalizador::siNo($si), var_export($si, true));
        }
        foreach (['N', 'No', 'no', '0', 0, false, 'false', null, ''] as $no) {
            $this->assertFalse(Normalizador::siNo($no), var_export($no, true));
        }

        $this->assertSame('D', Normalizador::disponibilidad(null));
        $this->assertSame('D', Normalizador::disponibilidad(' d '));
        $this->assertSame('P', Normalizador::disponibilidad('p'));
        $this->assertSame('N', Normalizador::disponibilidad('N'));
        $this->expectException(ValorInvalido::class);
        Normalizador::disponibilidad('X');
    }

    public function test_encabezado(): void
    {
        $this->assertSame('mie_07:00', Normalizador::encabezado(' Mié 07:00 '));
        $this->assertSame('direccion', Normalizador::encabezado('Dirección'));
        $this->assertSame('sincronia', Normalizador::encabezado('sincronía'));
        $this->assertSame('clave_profesor', Normalizador::encabezado('Clave profesor'));
    }
}
