<?php

namespace App\Http\Controllers\Escuela;

use App\Actions\GenerarCorrida;
use App\Enums\EstadoCorrida;
use App\Http\Controllers\Controller;
use App\Http\Requests\CorridaRequest;
use App\Models\Corrida;
use App\Models\Escuela;
use App\Solver\ParametrosCorrida;
use App\Solver\PresentadorCorrida;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Generar el horario del ciclo actual y ver cómo va cada corrida.
 */
class CorridaController extends Controller
{
    public function index(Request $request): Response
    {
        $ciclo = $this->cicloActual($request);

        return Inertia::render('escuela/corridas/Index', [
            'ciclo' => $ciclo->clave,
            'corridas' => $ciclo->corridas()
                ->withCount('sesiones')
                ->latest('id')
                ->get()
                ->map(fn (Corrida $corrida) => [
                    'id' => $corrida->id,
                    'estado' => $corrida->estado->value,
                    'estado_etiqueta' => $corrida->estado->etiqueta(),
                    'activa' => $corrida->estado->activa(),
                    'puntaje' => $corrida->puntaje,
                    'sesiones' => $corrida->sesiones_count,
                    'creada' => $corrida->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function create(Request $request): Response
    {
        $ciclo = $this->cicloActual($request);

        $parametros = ParametrosCorrida::normalizar([], Escuela::query()->findOrFail($ciclo->escuela_id));

        return Inertia::render('escuela/corridas/Create', [
            'ciclo' => $ciclo->clave,
            'parametros' => ['tiempo_limite_s' => $parametros['tiempo_limite_s'], 'permitir_parcial' => $parametros['permitir_parcial']],
            'reglas' => ParametrosCorrida::paraFormulario($parametros['reglas']),
            'hayActiva' => $ciclo->corridas()->activas()->exists(),
        ]);
    }

    public function store(CorridaRequest $request, GenerarCorrida $generar): RedirectResponse
    {
        $corrida = $generar($this->cicloActual($request), $request->entrada());

        Inertia::flash('toast', ['type' => 'info', 'message' => 'Generando el horario…']);

        return to_route('corridas.show', $corrida);
    }

    public function show(Corrida $corrida, PresentadorCorrida $presentador): Response
    {
        return Inertia::render('escuela/corridas/Show', $presentador->props($corrida));
    }

    public function reintentar(Corrida $corrida, GenerarCorrida $generar): RedirectResponse
    {
        if ($corrida->estado !== EstadoCorrida::Error) {
            return back()->withErrors(['corrida' => 'Solo se puede reintentar una corrida con error.']);
        }

        $generar->reintentar($corrida);

        Inertia::flash('toast', ['type' => 'info', 'message' => 'Reintentando…']);

        return to_route('corridas.show', $corrida);
    }
}
