<?php

namespace App\Http\Controllers\Escuela;

use App\Http\Controllers\Controller;
use App\Http\Requests\GrupoRequest;
use App\Models\Grupo;
use App\Models\PlanEstudios;
use App\Models\Plantel;
use App\Models\PlantillaHoraria;
use App\Support\Borrado;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Los grupos del ciclo actual; los de otros ciclos dan 404.
 */
class GrupoController extends Controller
{
    public function index(Request $request): Response
    {
        $ciclo = $this->cicloActual($request);

        return Inertia::render('escuela/grupos/Index', [
            'ciclo' => $ciclo->clave,
            'grupos' => $ciclo->grupos()
                ->with(['plantel:id,nombre', 'plantilla:id,nombre', 'plan:id,clave'])
                ->orderBy('semestre')
                ->orderBy('clave')
                ->get()
                ->map(fn (Grupo $grupo) => [
                    'id' => $grupo->id,
                    'clave' => $grupo->clave,
                    'semestre' => $grupo->semestre,
                    'alumnos' => $grupo->alumnos,
                    'plantel' => $grupo->plantel->nombre,
                    'plantilla' => $grupo->plantilla->nombre,
                    'plan' => $grupo->plan->clave,
                ]),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('escuela/grupos/Form', ['grupo' => null, ...$this->opciones($request)]);
    }

    public function store(GrupoRequest $request): RedirectResponse
    {
        $this->cicloActual($request)->grupos()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Grupo creado.']);

        return to_route('grupos.index');
    }

    public function edit(Request $request, Grupo $grupo): Response
    {
        $this->delCicloActual($request, $grupo);

        return Inertia::render('escuela/grupos/Form', [
            'grupo' => $grupo->only(['id', 'plantel_id', 'plantilla_id', 'plan_id', 'semestre', 'clave', 'alumnos']),
            ...$this->opciones($request),
        ]);
    }

    public function update(GrupoRequest $request, Grupo $grupo): RedirectResponse
    {
        $this->delCicloActual($request, $grupo);

        $grupo->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Grupo guardado.']);

        return to_route('grupos.index');
    }

    public function destroy(Request $request, Grupo $grupo): RedirectResponse
    {
        $this->delCicloActual($request, $grupo);

        Borrado::impedirSiSeUsa($grupo->clave, ['curso|cursos' => $grupo->cursos()]);

        $grupo->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Grupo borrado.']);

        return to_route('grupos.index');
    }

    private function delCicloActual(Request $request, Grupo $grupo): void
    {
        abort_unless($grupo->ciclo_id === $this->cicloActual($request)->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function opciones(Request $request): array
    {
        return [
            'ciclo' => $this->cicloActual($request)->clave,
            'planteles' => Plantel::query()->orderBy('nombre')->get(['id', 'nombre']),
            'plantillas' => PlantillaHoraria::query()->orderBy('nombre')->get(['id', 'nombre']),
            'planes' => PlanEstudios::query()->orderBy('clave')->get(['id', 'clave', 'nombre']),
        ];
    }
}
