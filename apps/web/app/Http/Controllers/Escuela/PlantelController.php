<?php

namespace App\Http\Controllers\Escuela;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlantelRequest;
use App\Models\Plantel;
use App\Support\Borrado;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PlantelController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('escuela/planteles/Index', [
            'planteles' => Plantel::query()
                ->select(['id', 'clave', 'nombre', 'direccion'])
                ->withCount('espacios')
                ->orderBy('clave')
                ->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('escuela/planteles/Form', ['plantel' => null]);
    }

    public function store(PlantelRequest $request): RedirectResponse
    {
        Plantel::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plantel creado.']);

        return to_route('planteles.index');
    }

    public function edit(Plantel $plantel): Response
    {
        return Inertia::render('escuela/planteles/Form', [
            'plantel' => $plantel->only(['id', 'clave', 'nombre', 'direccion']),
        ]);
    }

    public function update(PlantelRequest $request, Plantel $plantel): RedirectResponse
    {
        $plantel->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plantel guardado.']);

        return to_route('planteles.index');
    }

    public function destroy(Plantel $plantel): RedirectResponse
    {
        Borrado::impedirSiSeUsa($plantel->nombre, [
            'espacio|espacios' => $plantel->espacios(),
            'grupo|grupos' => $plantel->grupos(),
            'profesor|profesores' => $plantel->profesores(),
        ]);

        $plantel->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plantel borrado.']);

        return to_route('planteles.index');
    }
}
