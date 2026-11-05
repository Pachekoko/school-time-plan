<?php

namespace App\Http\Controllers\Escuela;

use App\Excel\ExportadorPlantilla;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportacionController extends Controller
{
    public function plantilla(ExportadorPlantilla $exportador): StreamedResponse
    {
        return $this->descargar($exportador->libro(false), 'plantilla-horarios.xlsx');
    }

    public function datos(Request $request, ExportadorPlantilla $exportador): StreamedResponse
    {
        $escuela = Str::slug($request->user()?->escuela()->value('nombre') ?? 'escuela');

        return $this->descargar($exportador->libro(true), "datos-{$escuela}-".now()->toDateString().'.xlsx');
    }

    private function descargar(Spreadsheet $libro, string $nombre): StreamedResponse
    {
        return response()->streamDownload(
            fn () => (new Xlsx($libro))->save('php://output'),
            $nombre,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
