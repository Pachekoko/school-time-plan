<?php

namespace App\Http\Controllers;

use App\Enums\EstadoCiclo;
use App\Http\Requests\DisponibilidadRequest;
use App\Models\Profesor;
use App\Support\Disponibilidades;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El profesor captura su propia disponibilidad mientras el ciclo actual está en captura.
 */
class MiDisponibilidadController extends Controller
{
    public function edit(Request $request): Response
    {
        $profesor = $this->profesor($request);

        return Inertia::render('MiDisponibilidad', [
            'profesor' => $profesor?->only(['nombre']),
            'cerrada' => $this->cerrada($request),
            ...($profesor === null ? ['plantillas' => [], 'estados' => (object) []] : Disponibilidades::props($profesor)),
        ]);
    }

    public function update(DisponibilidadRequest $request): RedirectResponse
    {
        abort_if($this->cerrada($request), 403, 'La captura de disponibilidad está cerrada.');

        $profesor = $this->profesor($request) ?? abort(404);

        Disponibilidades::guardar($profesor, $request->celdas());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tu disponibilidad quedó guardada.']);

        return to_route('mi-disponibilidad');
    }

    private function profesor(Request $request): ?Profesor
    {
        return Profesor::query()->firstWhere('user_id', $request->user()?->id);
    }

    private function cerrada(Request $request): bool
    {
        return $request->user()?->escuela?->cicloActual?->estado === EstadoCiclo::Cerrado;
    }
}
