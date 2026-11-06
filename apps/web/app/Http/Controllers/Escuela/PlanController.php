<?php

namespace App\Http\Controllers\Escuela;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlanRequest;
use App\Models\PlanEstudios;
use App\Support\Borrado;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('escuela/planes/Index', [
            'planes' => PlanEstudios::query()
                ->select(['id', 'clave', 'nombre'])
                ->withCount('materias')
                ->orderBy('clave')
                ->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('escuela/planes/Form', ['plan' => null]);
    }

    public function store(PlanRequest $request): RedirectResponse
    {
        PlanEstudios::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plan creado.']);

        return to_route('planes.index');
    }

    public function edit(PlanEstudios $plan): Response
    {
        return Inertia::render('escuela/planes/Form', ['plan' => $plan->only(['id', 'clave', 'nombre'])]);
    }

    public function update(PlanRequest $request, PlanEstudios $plan): RedirectResponse
    {
        $plan->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plan guardado.']);

        return to_route('planes.index');
    }

    public function destroy(PlanEstudios $plan): RedirectResponse
    {
        Borrado::impedirSiSeUsa($plan->nombre, [
            'materia|materias' => $plan->materias(),
            'grupo|grupos' => $plan->grupos(),
        ]);

        $plan->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plan borrado.']);

        return to_route('planes.index');
    }
}
