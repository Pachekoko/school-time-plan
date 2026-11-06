<?php

namespace App\Http\Controllers\Escuela;

use App\Http\Controllers\Controller;
use App\Http\Requests\SincroniaRequest;
use App\Models\Sincronia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Las sincronías del ciclo actual. Borrar una deja sus cursos sin sincronía.
 */
class SincroniaController extends Controller
{
    public function index(Request $request): Response
    {
        $ciclo = $this->cicloActual($request);

        return Inertia::render('escuela/sincronias/Index', [
            'ciclo' => $ciclo->clave,
            'sincronias' => $ciclo->sincronias()
                ->with('cursos:id,sincronia_id,clave')
                ->orderBy('clave')
                ->get()
                ->map(fn (Sincronia $sincronia) => [
                    'id' => $sincronia->id,
                    'clave' => $sincronia->clave,
                    'nombre' => $sincronia->nombre,
                    'cursos' => $sincronia->cursos->pluck('clave')->sort(SORT_NATURAL)->values()->all(),
                ]),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('escuela/sincronias/Form', ['sincronia' => null, 'ciclo' => $this->cicloActual($request)->clave]);
    }

    public function store(SincroniaRequest $request): RedirectResponse
    {
        $this->cicloActual($request)->sincronias()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sincronía creada.']);

        return to_route('sincronias.index');
    }

    public function edit(Request $request, Sincronia $sincronia): Response
    {
        $this->delCicloActual($request, $sincronia);

        return Inertia::render('escuela/sincronias/Form', [
            'sincronia' => $sincronia->only(['id', 'clave', 'nombre']),
            'ciclo' => $this->cicloActual($request)->clave,
        ]);
    }

    public function update(SincroniaRequest $request, Sincronia $sincronia): RedirectResponse
    {
        $this->delCicloActual($request, $sincronia);

        $sincronia->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sincronía guardada.']);

        return to_route('sincronias.index');
    }

    public function destroy(Request $request, Sincronia $sincronia): RedirectResponse
    {
        $this->delCicloActual($request, $sincronia);

        $sincronia->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sincronía borrada.']);

        return to_route('sincronias.index');
    }

    private function delCicloActual(Request $request, Sincronia $sincronia): void
    {
        abort_unless($sincronia->ciclo_id === $this->cicloActual($request)->id, 404);
    }
}
