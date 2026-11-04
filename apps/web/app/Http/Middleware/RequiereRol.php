<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `rol:admin`, `rol:profesor` o varios a la vez (`rol:admin,profesor`); 403 si no coincide.
 */
class RequiereRol
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $rol = $request->user()?->rol;

        abort_unless($rol !== null && in_array($rol->value, $roles, true), 403);

        return $next($request);
    }
}
