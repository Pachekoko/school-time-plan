<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class IdiomaTest extends TestCase
{
    use RefreshDatabase;

    /** Código cuyos textos llegan a la pantalla o al correo. */
    private const CON_TEXTOS = [
        'app',
        'resources/views',
        'vendor/laravel/fortify/src',
        'vendor/laravel/passkeys/src',
        'vendor/laravel/framework/src/Illuminate/Auth/Notifications',
        'vendor/laravel/framework/src/Illuminate/Notifications/resources/views',
        'vendor/laravel/framework/src/Illuminate/Mail/resources/views',
        'vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/views',
        'vendor/laravel/framework/src/Illuminate/Pagination/resources/views',
    ];

    /** Textos que el código arma sin escribirlos completos dentro de __(). */
    private const TEXTOS_ARMADOS = [
        "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\ninto your web browser:",
        'This action is unauthorized.',
    ];

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

    public function test_cada_texto_que_se_ve_tiene_traduccion(): void
    {
        $traducciones = json_decode(File::get(lang_path('es_MX.json')), true, flags: JSON_THROW_ON_ERROR);
        $claves = self::TEXTOS_ARMADOS;

        foreach (self::CON_TEXTOS as $carpeta) {
            foreach (File::allFiles(base_path($carpeta)) as $archivo) {
                preg_match_all(
                    '/(?:\b__|@lang|Lang::get|\btrans|InvalidPasskeyException::make)\(\s*(?:\'((?:\\\\.|[^\'\\\\])*)\'|"((?:\\\\.|[^"\\\\])*)")\s*[,)]/',
                    $archivo->getContents(),
                    $usos,
                    PREG_SET_ORDER,
                );

                foreach ($usos as $uso) {
                    $claves[] = isset($uso[2]) ? stripcslashes($uso[2]) : str_replace(["\\'", '\\\\'], ["'", '\\'], $uso[1]);
                }
            }
        }

        // Las claves de grupo (validation.required) viven en los archivos de lang/es_MX.
        $faltan = array_filter(
            array_unique($claves),
            fn (string $clave): bool => ! preg_match('/^[a-z_-]+\.[\w.-]+$/', $clave) && ! array_key_exists($clave, $traducciones),
        );

        $this->assertSame([], array_values($faltan));
    }

    public function test_los_mensajes_del_framework_estan_completos(): void
    {
        $ingles = base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en');

        foreach (['auth', 'pagination', 'passwords', 'validation'] as $grupo) {
            $faltan = array_diff(
                array_keys(Arr::dot(require "{$ingles}/{$grupo}.php")),
                array_keys(Arr::dot(require lang_path("es_MX/{$grupo}.php"))),
            );
            $faltan = array_filter($faltan, fn (string $clave): bool => ! str_starts_with($clave, 'custom.') && $clave !== 'attributes');

            $this->assertSame([], array_values($faltan), "Faltan en lang/es_MX/{$grupo}.php");
        }
    }

    public function test_las_traducciones_van_de_tu_y_con_palabras_de_mexico(): void
    {
        $textos = array_values(json_decode(File::get(lang_path('es_MX.json')), true, flags: JSON_THROW_ON_ERROR));

        foreach (File::files(lang_path('es_MX')) as $archivo) {
            $textos = [...$textos, ...Arr::flatten(require $archivo->getPathname())];
        }

        $deUstedODeEspana = '/(*UCP)\b(usted|haga|elija|ingrese|introduzca|seleccione|restablezca|verifique|confirme|inicie|visite|inténtelo|espere|le hemos|le ha|su (contraseña|cuenta|correo)|añad\w*|ordenador|móvil|fichero)\b/iu';

        $this->assertSame([], array_values(preg_grep($deUstedODeEspana, $textos)));
    }

    public function test_el_error_al_iniciar_sesion_sale_de_tu(): void
    {
        $usuario = User::factory()->create();

        $this->post(route('login.store'), ['email' => $usuario->email, 'password' => 'no-es-la-contraseña'])
            ->assertSessionHasErrors(['email' => 'El correo o la contraseña no son correctos.']);
    }

    public function test_demasiados_intentos_al_iniciar_sesion(): void
    {
        $usuario = User::factory()->create();
        $datos = ['email' => $usuario->email, 'password' => 'no-es-la-contraseña'];

        foreach (range(1, 5) as $intento) {
            $this->post(route('login.store'), $datos);
        }

        $this->post(route('login.store'), $datos)->assertTooManyRequests()->assertSee('Demasiados intentos');
    }

    public function test_el_correo_para_restablecer_la_contrasena_sale_de_tu(): void
    {
        Notification::fake();
        $usuario = User::factory()->create();

        $this->post(route('password.email'), ['email' => $usuario->email])
            ->assertSessionHas('status', 'Te enviamos un correo con el enlace para restablecer tu contraseña.');

        Notification::assertSentTo($usuario, ResetPassword::class, function (ResetPassword $aviso) use ($usuario): bool {
            $correo = $aviso->toMail($usuario);
            $html = (string) $correo->render();

            $this->assertSame('Restablece tu contraseña', $correo->subject);
            foreach (['¡Hola!', 'Recibes este correo porque', 'Restablecer contraseña', 'Este enlace vence en 60 minutos.', 'Si no pediste', 'Saludos,', 'Si el botón'] as $texto) {
                $this->assertStringContainsString($texto, $html);
            }

            return true;
        });
    }

    public function test_la_pagina_que_no_existe_sale_en_espanol(): void
    {
        $this->get('/no-existe')
            ->assertNotFound()
            ->assertSee('<html lang="es-MX"', false)
            ->assertSee('Página no encontrada');
    }

    public function test_sin_permiso_sale_en_espanol(): void
    {
        $this->actingAs(User::factory()->profesor()->create())
            ->get(route('ciclos.index'))
            ->assertForbidden()
            ->assertSee('No tienes permiso');
    }
}
