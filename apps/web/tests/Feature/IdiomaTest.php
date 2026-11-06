<?php

namespace Tests\Feature;

use Tests\TestCase;

class IdiomaTest extends TestCase
{
    public function test_la_app_esta_en_espanol_de_mexico(): void
    {
        $this->assertSame('es_MX', app()->getLocale());
        $this->assertSame('es_MX', config('app.fallback_locale'));
        $this->assertSame('es_MX', config('app.faker_locale'));
    }

    public function test_el_idioma_no_se_toma_del_entorno(): void
    {
        // Render tenía APP_LOCALE=es: si el entorno mandara, la app se quedaría sin traducciones.
        $antes = $_SERVER['APP_LOCALE'] ?? null;
        $_SERVER['APP_LOCALE'] = 'en';

        try {
            $config = require config_path('app.php');
        } finally {
            if ($antes === null) {
                unset($_SERVER['APP_LOCALE']);
            } else {
                $_SERVER['APP_LOCALE'] = $antes;
            }
        }

        $this->assertSame('es_MX', $config['locale']);
    }

    public function test_la_pagina_se_declara_en_espanol_de_mexico(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('<html lang="es-MX"', false);
    }

    public function test_validacion_en_espanol(): void
    {
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
