<?php

namespace App\Http\Middleware;

use App\Enums\Rol;
use App\Guia\PrimerosPasos;
use App\Support\EscuelaActual;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            // La escuela sale de la relación del usuario: estas props comparten una sola consulta.
            'escuela' => fn () => $request->user()?->escuela?->only(['id', 'nombre']),
            'rol' => fn () => $request->user()?->rol->value,
            'cicloActual' => fn () => $this->cicloActual($request),
            'guia' => fn () => $this->guia($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * La guía de primeros pasos: solo para el admin con escuela.
     *
     * @return array{hechos: array<string, bool>, cerrada: bool}|null
     */
    private function guia(Request $request): ?array
    {
        $usuario = $request->user();
        $escuela = $usuario?->rol === Rol::Admin ? $usuario->escuela : null;

        if ($usuario === null || $escuela === null) {
            return null;
        }

        return ['hechos' => app(PrimerosPasos::class)->hechos($escuela), 'cerrada' => $usuario->guia_cerrada_en !== null];
    }

    /**
     * El ciclo actual de la escuela del usuario, también en páginas fuera del grupo
     * `escuela` (como los ajustes), donde no hay escuela actual fijada.
     *
     * @return array{id: int, clave: string, estado: string}|null
     */
    private function cicloActual(Request $request): ?array
    {
        $escuela = $request->user()?->escuela;

        if ($escuela?->ciclo_actual_id === null) {
            return null;
        }

        $ciclo = app(EscuelaActual::class)->usar($escuela->id, fn () => $escuela->cicloActual()->first());

        return $ciclo === null ? null : ['id' => $ciclo->id, 'clave' => $ciclo->clave, 'estado' => $ciclo->estado->value];
    }
}
