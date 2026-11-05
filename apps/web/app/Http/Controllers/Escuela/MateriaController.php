<?php

namespace App\Http\Controllers\Escuela;

use App\Domain\Patrones;
use App\Enums\TipoEspacio;
use App\Http\Controllers\Controller;
use App\Http\Requests\MateriaRequest;
use App\Models\Materia;
use App\Models\PlanEstudios;
use App\Support\Borrado;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MateriaController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('escuela/materias/Index', [
            'materias' => Materia::query()
                ->with('plan:id,clave')
                ->orderBy('semestre')
                ->orderBy('clave')
                ->get()
                ->map(fn (Materia $materia) => [
                    'id' => $materia->id,
                    'clave' => $materia->clave,
                    'nombre' => $materia->nombre,
                    'plan' => $materia->plan->clave,
                    'semestre' => $materia->semestre,
                    'horas_semana' => $materia->horas_semana,
                    'patron' => Patrones::formatear($materia->patronesEfectivos()),
                    'patron_capturado' => $materia->patrones !== null,
                    'tipo_espacio' => $materia->tipo_espacio->etiqueta(),
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('escuela/materias/Form', ['materia' => null, ...$this->opciones()]);
    }

    public function store(MateriaRequest $request): RedirectResponse
    {
        Materia::create($request->datos());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Materia creada.']);

        return to_route('materias.index');
    }

    public function edit(Materia $materia): Response
    {
        return Inertia::render('escuela/materias/Form', [
            'materia' => [
                ...$materia->only(['id', 'plan_id', 'clave', 'nombre', 'semestre', 'horas_semana', 'tipo_espacio']),
                'patrones' => $materia->patrones === null ? '' : Patrones::formatear($materia->patrones),
            ],
            ...$this->opciones(),
        ]);
    }

    public function update(MateriaRequest $request, Materia $materia): RedirectResponse
    {
        $materia->update($request->datos());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Materia guardada.']);

        return to_route('materias.index');
    }

    public function destroy(Materia $materia): RedirectResponse
    {
        Borrado::impedirSiSeUsa($materia->nombre, [
            'curso|cursos' => $materia->cursos(),
            'profesor|profesores' => $materia->profesores(),
        ]);

        $materia->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Materia borrada.']);

        return to_route('materias.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function opciones(): array
    {
        return [
            'planes' => PlanEstudios::query()->orderBy('clave')->get(['id', 'clave', 'nombre']),
            'tipos' => TipoEspacio::opciones(),
        ];
    }
}
