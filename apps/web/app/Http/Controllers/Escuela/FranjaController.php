<?php

namespace App\Http\Controllers\Escuela;

use App\Domain\GeneradorFranjas;
use App\Http\Controllers\Controller;
use App\Http\Requests\FranjaRequest;
use App\Http\Requests\GenerarFranjasRequest;
use App\Models\Franja;
use App\Models\PlantillaHoraria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FranjaController extends Controller
{
    public function index(PlantillaHoraria $plantilla): Response
    {
        return Inertia::render('escuela/plantillas/Franjas', [
            'plantilla' => $plantilla->only(['id', 'clave', 'nombre']),
            'franjas' => $plantilla->franjas()
                ->orderBy('dia')
                ->orderBy('orden')
                ->get(['id', 'dia', 'orden', 'inicio', 'fin', 'tipo']),
        ]);
    }

    /**
     * Reemplaza todas las franjas de la plantilla por las generadas.
     */
    public function generar(GenerarFranjasRequest $request, PlantillaHoraria $plantilla, GeneradorFranjas $generador): RedirectResponse
    {
        $filas = $generador->generar(...$request->parametros());

        DB::transaction(function () use ($plantilla, $filas): void {
            $plantilla->franjas()->delete();
            $plantilla->franjas()->createMany($filas);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => count($filas).' franjas generadas.']);

        return to_route('plantillas.franjas', $plantilla);
    }

    public function update(FranjaRequest $request, Franja $franja): RedirectResponse
    {
        $franja->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Franja guardada.']);

        return to_route('plantillas.franjas', $franja->plantilla_id);
    }

    public function destroy(Franja $franja): RedirectResponse
    {
        $franja->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Franja borrada.']);

        return to_route('plantillas.franjas', $franja->plantilla_id);
    }
}
