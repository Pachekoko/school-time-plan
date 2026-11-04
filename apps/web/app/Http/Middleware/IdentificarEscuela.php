<?php

namespace App\Http\Middleware;

use App\Support\EscuelaActual;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fija la escuela del usuario como escuela actual mientras dura la petición.
 */
class IdentificarEscuela
{
    public function __construct(private EscuelaActual $escuela) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->user()?->escuela_id;

        abort_if($id === null, 403);

        return $this->escuela->usar($id, fn () => $next($request));
    }
}
