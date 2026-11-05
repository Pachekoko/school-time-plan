<?php

namespace App\Http\Controllers\Escuela;

use App\Enums\EstadoCiclo;
use App\Http\Controllers\Controller;
use App\Http\Requests\CicloRequest;
use App\Models\Ciclo;
use App\Models\Escuela;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CicloController extends Controller
{
    public function index(Request $request): Response
    {
        $actual = $this->escuela($request)->ciclo_actual_id;

        return Inertia::render('escuela/ciclos/Index', [
            'ciclos' => Ciclo::query()
                ->orderByDesc('clave')
                ->get()
                ->map(fn (Ciclo $ciclo) => [
                    ...$this->datos($ciclo),
                    'estado_etiqueta' => $ciclo->estado->etiqueta(),
                    'actual' => $ciclo->id === $actual,
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('escuela/ciclos/Form', ['ciclo' => null, 'estados' => EstadoCiclo::opciones()]);
    }

    /**
     * Si la escuela todavía no tiene ciclo actual, el nuevo queda como actual.
     */
    public function store(CicloRequest $request): RedirectResponse
    {
        $ciclo = Ciclo::create($request->validated());
        $escuela = $this->escuela($request);

        if ($escuela->ciclo_actual_id === null) {
            $escuela->ciclo_actual_id = $ciclo->id;
            $escuela->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ciclo creado.']);

        return to_route('ciclos.index');
    }

    public function edit(Ciclo $ciclo): Response
    {
        return Inertia::render('escuela/ciclos/Form', ['ciclo' => $this->datos($ciclo), 'estados' => EstadoCiclo::opciones()]);
    }

    public function update(CicloRequest $request, Ciclo $ciclo): RedirectResponse
    {
        $ciclo->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ciclo guardado.']);

        return to_route('ciclos.index');
    }

    public function destroy(Ciclo $ciclo): RedirectResponse
    {
        $ciclo->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ciclo borrado.']);

        return to_route('ciclos.index');
    }

    public function activar(Request $request, Ciclo $ciclo): RedirectResponse
    {
        $escuela = $this->escuela($request);
        $escuela->ciclo_actual_id = $ciclo->id;
        $escuela->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Ahora trabajas en el ciclo «{$ciclo->clave}»."]);

        return to_route('ciclos.index');
    }

    private function escuela(Request $request): Escuela
    {
        return $request->user()->escuela ?? abort(403);
    }

    /**
     * @return array{id: int, clave: string, inicio: string|null, fin: string|null, estado: string}
     */
    private function datos(Ciclo $ciclo): array
    {
        return [
            'id' => $ciclo->id,
            'clave' => $ciclo->clave,
            'inicio' => $ciclo->inicio?->toDateString(),
            'fin' => $ciclo->fin?->toDateString(),
            'estado' => $ciclo->estado->value,
        ];
    }
}
