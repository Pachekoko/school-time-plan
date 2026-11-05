<?php

namespace App\Http\Middleware;

use App\Models\Ciclo;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Para las pantallas que trabajan sobre el ciclo actual (grupos, carga, corridas): sin
 * ciclo actual manda a elegirlo; con él, lo deja en el atributo `ciclo` de la petición.
 */
class RequiereCicloActual
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ciclo = $request->user()?->escuela?->cicloActual;

        if (! $ciclo instanceof Ciclo) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => 'Primero elige el ciclo actual.']);

            return to_route('ciclos.index');
        }

        $request->attributes->set('ciclo', $ciclo);

        return $next($request);
    }
}
