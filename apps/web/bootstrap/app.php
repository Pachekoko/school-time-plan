<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IdentificarEscuela;
use App\Http\Middleware\RequiereCicloActual;
use App\Http\Middleware\RequiereRol;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Render termina el TLS en su proxy, que no tiene IPs fijas: se le cree el esquema y el
        // puerto (sin esto los assets salen por http y el navegador los bloquea), pero no el
        // host, para que nadie cambie a dónde apuntan los enlaces de los correos.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'escuela' => IdentificarEscuela::class,
            'rol' => RequiereRol::class,
            'ciclo' => RequiereCicloActual::class,
        ]);

        // La escuela y el rol se resuelven antes que los modelos de la ruta, para que
        // el global scope ya filtre (un registro de otra escuela da 404) y un rol
        // sin permiso reciba 403 sin revelar qué ids existen.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: IdentificarEscuela::class);
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: RequiereRol::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
