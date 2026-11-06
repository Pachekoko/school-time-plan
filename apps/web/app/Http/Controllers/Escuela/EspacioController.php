<?php

namespace App\Http\Controllers\Escuela;

use App\Enums\TipoEspacio;
use App\Http\Controllers\Controller;
use App\Http\Requests\EspacioRequest;
use App\Models\Espacio;
use App\Models\Plantel;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EspacioController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('escuela/espacios/Index', [
            'espacios' => Espacio::query()
                ->with('plantel:id,nombre')
                ->orderBy('clave')
                ->get()
                ->map(fn (Espacio $espacio) => [
                    'id' => $espacio->id,
                    'clave' => $espacio->clave,
                    'nombre' => $espacio->nombre,
                    'tipo' => $espacio->tipo->etiqueta(),
                    'capacidad' => $espacio->capacidad,
                    'plantel' => $espacio->plantel->nombre,
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('escuela/espacios/Form', ['espacio' => null, ...$this->opciones()]);
    }

    public function store(EspacioRequest $request): RedirectResponse
    {
        Espacio::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Espacio creado.']);

        return to_route('espacios.index');
    }

    public function edit(Espacio $espacio): Response
    {
        return Inertia::render('escuela/espacios/Form', [
            'espacio' => $espacio->only(['id', 'plantel_id', 'clave', 'nombre', 'tipo', 'capacidad']),
            ...$this->opciones(),
        ]);
    }

    public function update(EspacioRequest $request, Espacio $espacio): RedirectResponse
    {
        $espacio->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Espacio guardado.']);

        return to_route('espacios.index');
    }

    public function destroy(Espacio $espacio): RedirectResponse
    {
        $espacio->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Espacio borrado.']);

        return to_route('espacios.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function opciones(): array
    {
        return [
            'planteles' => Plantel::query()->orderBy('nombre')->get(['id', 'nombre']),
            'tipos' => TipoEspacio::opciones(),
        ];
    }
}
