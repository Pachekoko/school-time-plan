<?php

namespace Tests\Unit;

use App\Domain\Patrones;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PatronesTest extends TestCase
{
    public function test_derivar(): void
    {
        $this->assertSame([[1]], Patrones::derivar(1));
        $this->assertSame([[2], [1, 1]], Patrones::derivar(2));
        $this->assertSame([[2, 1]], Patrones::derivar(3));
        $this->assertSame([[2, 2]], Patrones::derivar(4));
        $this->assertSame([[2, 2, 1]], Patrones::derivar(5));
        $this->assertSame([[2, 2, 2]], Patrones::derivar(6));
        $this->assertSame([[2, 2, 2, 1]], Patrones::derivar(7));
        $this->assertSame([[2, 2, 2, 2]], Patrones::derivar(8));
    }

    public function test_parsear_y_formatear(): void
    {
        $this->assertSame([[2], [1, 1]], Patrones::parsear('2 | 1+1', 2));
        $this->assertSame([[2, 1]], Patrones::parsear(' 2 + 1 ', 3));
        $this->assertSame('2 | 1+1', Patrones::formatear([[2], [1, 1]]));
        $this->assertSame('2+2+1', Patrones::formatear(Patrones::parsear('2+2+1', 5)));
    }

    public function test_patron_que_no_suma(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El patrón 2+1 suma 3 y horas_semana es 4.');

        Patrones::parsear('2+1', 4);
    }

    public function test_texto_que_no_es_patron(): void
    {
        foreach (['', '2+', 'dos', '2 |', '0+2', '2 1', '1.5+1.5'] as $texto) {
            try {
                Patrones::parsear($texto, 3);
                $this->fail("«{$texto}» debió rechazarse");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('no es un patrón válido', $e->getMessage());
            }
        }
    }
}
