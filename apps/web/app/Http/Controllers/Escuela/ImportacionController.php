<?php

namespace App\Http\Controllers\Escuela;

use App\Excel\AplicadorImportacion;
use App\Excel\ImportadorCargaUteg;
use App\Excel\ImportadorPlantilla;
use App\Excel\LectorLibro;
use App\Excel\PlanImportacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmarImportacionRequest;
use App\Http\Requests\ImportacionRequest;
use App\Models\PlanEstudios;
use App\Models\Plantel;
use App\Models\PlantillaHoraria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Subir un Excel, ver qué cambiaría y aplicarlo. El archivo se guarda bajo la carpeta de la
 * escuela, así que el uuid de otra escuela no se encuentra.
 */
class ImportacionController extends Controller
{
    public function __construct(
        private LectorLibro $lector,
        private ImportadorPlantilla $importador,
        private ImportadorCargaUteg $uteg,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('escuela/importar/Index', [
            'ciclo' => $this->cicloActual($request)->clave,
            'formatos' => [
                ['valor' => 'plantilla', 'etiqueta' => 'Plantilla de Horarios'],
                ['valor' => 'uteg', 'etiqueta' => 'Carga horaria de UTEG (hoja HORARIOS)'],
            ],
            'planteles' => Plantel::query()->orderBy('nombre')->get(['id', 'nombre']),
            'plantillas' => PlantillaHoraria::query()->orderBy('nombre')->get(['id', 'nombre']),
            'planes' => PlanEstudios::query()->orderBy('clave')->get(['id', 'clave', 'nombre']),
        ]);
    }

    public function store(ImportacionRequest $request): Response|RedirectResponse
    {
        $uuid = (string) Str::uuid();
        $ruta = $this->ruta($request, $uuid);
        $request->file('archivo')?->storeAs(dirname($ruta), basename($ruta));

        try {
            $plan = $this->revisar($request, $ruta);
        } catch (Throwable $e) {
            report($e);
            Storage::delete($ruta);

            return back()->withErrors(['archivo' => 'No se pudo leer el archivo como Excel.']);
        }

        return Inertia::render('escuela/importar/VistaPrevia', [
            'uuid' => $uuid,
            'archivo' => $request->file('archivo')?->getClientOriginalName(),
            'formato' => $request->string('formato')->value(),
            'opciones' => $request->esUteg() ? $request->opciones() : null,
            'conteos' => $plan->conteos(),
            'errores' => $plan->errores(),
            'avisos' => $plan->avisos(),
            'tieneErrores' => $plan->tieneErrores(),
        ]);
    }

    public function confirmar(ConfirmarImportacionRequest $request, string $uuid, AplicadorImportacion $aplicador): RedirectResponse
    {
        $ruta = $this->ruta($request, $uuid);

        abort_unless(Storage::exists($ruta), 404);

        $plan = $this->revisar($request, $ruta);

        if ($plan->tieneErrores()) {
            return back()->withErrors(['importacion' => 'El archivo tiene errores: corrígelos y vuelve a subirlo.']);
        }

        $aplicador->aplicar($plan);
        Storage::delete($ruta);

        ['crear' => $nuevos, 'actualizar' => $actualizados] = $plan->totales();
        Inertia::flash('toast', ['type' => 'success', 'message' => "Importación aplicada: {$nuevos} registros nuevos y {$actualizados} actualizados."]);

        return to_route('importar.index');
    }

    private function revisar(ImportacionRequest $request, string $ruta): PlanImportacion
    {
        $ciclo = $this->cicloActual($request);

        return $request->esUteg()
            ? $this->uteg->revisar(Storage::path($ruta), $request->opciones(), $ciclo)
            : $this->importador->revisar($this->lector->leer(Storage::path($ruta)), $ciclo);
    }

    private function ruta(Request $request, string $uuid): string
    {
        return 'importaciones/'.$request->user()?->escuela_id."/{$uuid}.xlsx";
    }
}
