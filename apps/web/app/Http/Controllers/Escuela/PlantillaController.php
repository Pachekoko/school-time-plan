<?php

namespace App\Http\Controllers\Escuela;

use App\Enums\TipoFranja;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlantillaRequest;
use App\Models\PlantillaHoraria;
use App\Support\Borrado;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PlantillaController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('escuela/plantillas/Index', [
            'plantillas' => PlantillaHoraria::query()
                ->with(['franjas' => fn ($consulta) => $consulta->select(['id', 'plantilla_id', 'dia', 'tipo'])])
                ->orderBy('clave')
                ->get()
                ->map(fn (PlantillaHoraria $plantilla) => [
                    'id' => $plantilla->id,
                    'clave' => $plantilla->clave,
                    'nombre' => $plantilla->nombre,
                    'dias' => $plantilla->franjas->pluck('dia')->unique()->sort()->values()->all(),
                    'clases' => $plantilla->franjas->where('tipo', TipoFranja::Clase)->count(),
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('escuela/plantillas/Form', ['plantilla' => null]);
    }

    public function store(PlantillaRequest $request): RedirectResponse
    {
        $plantilla = PlantillaHoraria::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plantilla creada. Ahora genera sus franjas.']);

        return to_route('plantillas.franjas', $plantilla);
    }

    public function edit(PlantillaHoraria $plantilla): Response
    {
        return Inertia::render('escuela/plantillas/Form', [
            'plantilla' => $plantilla->only(['id', 'clave', 'nombre']),
        ]);
    }

    public function update(PlantillaRequest $request, PlantillaHoraria $plantilla): RedirectResponse
    {
        $plantilla->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plantilla guardada.']);

        return to_route('plantillas.index');
    }

    public function destroy(PlantillaHoraria $plantilla): RedirectResponse
    {
        Borrado::impedirSiSeUsa($plantilla->nombre, ['grupo|grupos' => $plantilla->grupos()]);

        $plantilla->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plantilla borrada.']);

        return to_route('plantillas.index');
    }
}
