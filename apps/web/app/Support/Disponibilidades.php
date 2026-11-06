<?php

namespace App\Support;

use App\Enums\EstadoDisponibilidad;
use App\Models\Disponibilidad;
use App\Models\Franja;
use App\Models\PlantillaHoraria;
use App\Models\Profesor;
use Illuminate\Support\Facades\DB;

/**
 * Lo que comparten la captura del admin y la del propio profesor.
 */
final class Disponibilidades
{
    /**
     * Las plantillas de la escuela con sus franjas, y la letra capturada por franja
     * (P o N; las que no aparecen están disponibles).
     *
     * @return array{plantillas: array<int, array<string, mixed>>, estados: object}
     */
    public static function props(Profesor $profesor): array
    {
        $plantillas = PlantillaHoraria::query()
            ->with(['franjas' => fn ($consulta) => $consulta->orderBy('dia')->orderBy('orden')])
            ->orderBy('nombre')
            ->get()
            ->map(fn (PlantillaHoraria $plantilla) => [
                'id' => $plantilla->id,
                'nombre' => $plantilla->nombre,
                'franjas' => $plantilla->franjas
                    ->map(fn (Franja $franja) => $franja->only(['id', 'dia', 'orden', 'inicio', 'fin', 'tipo']))
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        $estados = $profesor->disponibilidades()
            ->get(['franja_id', 'estado'])
            ->mapWithKeys(fn (Disponibilidad $disponibilidad) => [$disponibilidad->franja_id => $disponibilidad->estado->letra()])
            ->all();

        return ['plantillas' => $plantillas, 'estados' => (object) $estados];
    }

    /**
     * D borra la fila de la franja; P o N la crean o la actualizan.
     *
     * @param  list<array{franja_id: int, estado: string}>  $celdas
     */
    public static function guardar(Profesor $profesor, array $celdas): void
    {
        DB::transaction(function () use ($profesor, $celdas): void {
            foreach ($celdas as $celda) {
                $estado = EstadoDisponibilidad::deLetra($celda['estado']);

                if ($estado === null) {
                    $profesor->disponibilidades()->where('franja_id', $celda['franja_id'])->delete();
                } else {
                    $profesor->disponibilidades()->updateOrCreate(['franja_id' => $celda['franja_id']], ['estado' => $estado]);
                }
            }
        });
    }
}
