<?php

namespace Tests\Feature;

use Tests\TestCase;

class IdiomaTest extends TestCase
{
    public function test_validacion_en_espanol(): void
    {
        $this->assertSame('es', app()->getLocale());
        $this->assertStringContainsString(
            'obligatorio',
            __('validation.required', ['attribute' => 'nombre'])
        );
    }

    public function test_zona_horaria_de_mexico(): void
    {
        $this->assertSame('America/Mexico_City', config('app.timezone'));
    }
}
