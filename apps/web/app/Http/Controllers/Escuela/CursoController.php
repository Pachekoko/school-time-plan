<?php

namespace App\Http\Controllers\Escuela;

use App\Domain\ClaveCurso;
use App\Http\Controllers\Controller;
use App\Http\Requests\CursoRequest;
use App\Models\Curso;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Profesor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La carga académica del ciclo actual: qué materia se da a qué grupos y con quién.
 */
class CursoController extends Controller
{
    public function index(Request $request): Response
    {
        $ciclo = $this->cicloActual($request);
        $filtros = [
            'grupo' => $request->integer('grupo') ?: null,
            'materia' => $request->integer('materia') ?: null,
        ];

        return Inertia::render('escuela/cursos/Index', [
            'ciclo' => $ciclo->clave,
            'filtros' => $filtros,
            'grupos' => $ciclo->grupos()->orderBy('semestre')->orderBy('clave')->get(['id', 'clave']),
            'materias' => Materia::query()->orderBy('semestre')->orderBy('clave')->get(['id', 'clave', 'nombre']),
            'cursos' => $ciclo->cursos()
                ->with(['materia:id,clave,nombre', 'grupos:id,clave', 'profesor:id,nombre', 'sincronia:id,clave'])
                ->when($filtros['grupo'], fn ($consulta, $id) => $consulta->whereHas('grupos', fn ($grupos) => $grupos->whereKey($id)))
                ->when($filtros['materia'], fn ($consulta, $id) => $consulta->where('materia_id', $id))
                ->orderBy('clave')
                ->get()
                ->map(fn (Curso $curso) => [
                    'id' => $curso->id,
                    'clave' => $curso->clave,
                    'materia' => $curso->materia->nombre,
                    'grupos' => $curso->grupos->pluck('clave')->sort(SORT_NATURAL)->implode(', '),
                    'profesor' => $curso->profesor?->nombre,
                    'vacante' => $curso->vacante,
                    'sincronia' => $curso->sincronia?->clave,
                    'alumnos' => $curso->alumnos,
                ]),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('escuela/cursos/Form', ['curso' => null, ...$this->opciones($request)]);
    }

    public function store(CursoRequest $request): RedirectResponse
    {
        $ciclo = $this->cicloActual($request);

        DB::transaction(function () use ($request, $ciclo): void {
            $grupos = Grupo::query()->whereKey($request->grupos())->get();
            $curso = $ciclo->cursos()->make($request->datos());
            $curso->clave = $request->filled('clave')
                ? $request->string('clave')->trim()->value()
                : ClaveCurso::generar($grupos, Materia::query()->findOrFail($request->integer('materia_id')), $ciclo->id);
            $curso->save();
            $curso->grupos()->sync($grupos->modelKeys());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Curso creado.']);

        return to_route('cursos.index');
    }

    public function edit(Request $request, Curso $curso): Response
    {
        $this->delCicloActual($request, $curso);

        return Inertia::render('escuela/cursos/Form', [
            'curso' => [
                ...$curso->only(['id', 'materia_id', 'profesor_id', 'vacante', 'sincronia_id', 'alumnos', 'clave']),
                'grupos' => $curso->grupos()->pluck('grupos.id')->all(),
            ],
            ...$this->opciones($request),
        ]);
    }

    /**
     * Con la clave vacía, el curso conserva la que tenía.
     */
    public function update(CursoRequest $request, Curso $curso): RedirectResponse
    {
        $this->delCicloActual($request, $curso);

        DB::transaction(function () use ($request, $curso): void {
            $curso->fill($request->datos());

            if ($request->filled('clave')) {
                $curso->clave = $request->string('clave')->trim()->value();
            }

            $curso->save();
            $curso->grupos()->sync($request->grupos());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Curso guardado.']);

        return to_route('cursos.index');
    }

    public function destroy(Request $request, Curso $curso): RedirectResponse
    {
        $this->delCicloActual($request, $curso);

        $curso->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Curso borrado.']);

        return to_route('cursos.index');
    }

    private function delCicloActual(Request $request, Curso $curso): void
    {
        abort_unless($curso->ciclo_id === $this->cicloActual($request)->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function opciones(Request $request): array
    {
        $ciclo = $this->cicloActual($request);

        return [
            'ciclo' => $ciclo->clave,
            'materias' => Materia::query()->orderBy('semestre')->orderBy('clave')->get(['id', 'clave', 'nombre', 'semestre']),
            'grupos' => $ciclo->grupos()->orderBy('semestre')->orderBy('clave')->get(['id', 'clave', 'semestre']),
            'profesores' => Profesor::query()->orderBy('nombre')->get(['id', 'nombre']),
            'sincronias' => $ciclo->sincronias()->orderBy('clave')->get(['id', 'clave', 'nombre']),
        ];
    }
}
