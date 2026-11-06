<?php

namespace Tests\Feature;

use App\Solver\ClienteSolver;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Lo que cambia al correr detrás del proxy de Render y con su CPU.
 */
class ProduccionTest extends TestCase
{
    public function test_detras_del_proxy_las_urls_salen_con_https(): void
    {
        Route::get('/_prueba/url', fn () => url('/'));

        $url = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.7'])
            ->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->get('/_prueba/url')
            ->getContent();

        $this->assertStringStartsWith('https://', (string) $url);
    }

    public function test_el_host_que_manda_el_cliente_no_cambia_los_enlaces(): void
    {
        Route::get('/_prueba/url', fn () => url('/'));

        $url = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.7'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'malo.example'])
            ->get('/_prueba/url')
            ->getContent();

        $this->assertStringStartsWith('https://', (string) $url);
        $this->assertStringNotContainsString('malo.example', (string) $url);
    }

    public function test_la_espera_de_la_respuesta_suma_la_holgura_configurada(): void
    {
        $this->assertSame(120, ClienteSolver::esperaDeRespuesta(30));

        config(['solver.holgura_s' => 180]);
        $this->assertSame(270, ClienteSolver::esperaDeRespuesta(30));
    }
}
