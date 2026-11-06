<?php

namespace App\Http\Controllers\Escuela;

use App\Http\Controllers\Controller;
use App\Http\Requests\DisponibilidadRequest;
use App\Models\Profesor;
use App\Support\Disponibilidades;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El admin captura la disponibilidad de cualquier profesor de su escuela.
 */
class DisponibilidadController extends Controller
{
    public function edit(Profesor $profesor): Response
    {
        return Inertia::render('escuela/profesores/Disponibilidad', [
            'profesor' => $profesor->only(['id', 'clave', 'nombre']),
            ...Disponibilidades::props($profesor),
        ]);
    }

    public function update(DisponibilidadRequest $request, Profesor $profesor): RedirectResponse
    {
        Disponibilidades::guardar($profesor, $request->celdas());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Disponibilidad guardada.']);

        return to_route('profesores.disponibilidad', $profesor);
    }
}
