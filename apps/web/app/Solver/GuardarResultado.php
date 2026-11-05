<?php

namespace App\Solver;

use App\Models\Corrida;
use App\Models\Curso;
use Illuminate\Support\Facades\DB;

/**
 * Guarda la respuesta del motor en la corrida. Borra y vuelve a crear las sesiones, así
 * que guardar dos veces (un reintento) da lo mismo.
 */
final class GuardarResultado
{
    /**
     * @param  array<string, mixed>  $respuesta
     */
    public function guardar(Corrida $corrida, array $respuesta): void
    {
        DB::transaction(function () use ($corrida, $respuesta): void {
            $corrida->fill([
                'estado' => $respuesta['estado'],
                'puntaje' => $respuesta['puntaje'],
                'desglose' => $respuesta['desglose'],
                'diagnostico' => $respuesta['diagnostico'],
                'estadisticas' => $respuesta['estadisticas'],
                'terminada_en' => now(),
            ])->save();

            $corrida->sesiones()->delete();

            /** @var list<array<string, mixed>> $sesiones */
            $sesiones = $respuesta['sesiones'];
            $cursos = Curso::query()
                ->with('materia')
                ->whereKey(array_unique(array_map(fn (array $sesion) => (int) $sesion['curso'], $sesiones)))
                ->get()
                ->keyBy('id');

            foreach ($sesiones as $sesion) {
                $curso = $cursos->get((int) $sesion['curso']);

                // Un curso que se borró mientras el motor resolvía ya no tiene dónde ir.
                if (! $curso instanceof Curso) {
                    continue;
                }

                $patron = (int) ($sesion['patron'] ?? 0);
                $bloque = (int) $sesion['bloque'];

                $corrida->sesiones()->create([
                    'curso_id' => $curso->id,
                    'patron' => $patron,
                    'bloque' => $bloque,
                    'dia' => $sesion['dia'] ?? null,
                    'orden_inicio' => $sesion['orden_inicio'] ?? null,
                    'duracion' => $curso->materia->patronesEfectivos()[$patron][$bloque] ?? 1,
                    'espacio_id' => isset($sesion['espacio']) ? (int) $sesion['espacio'] : null,
                    'profesor_id' => isset($sesion['profesor']) ? (int) $sesion['profesor'] : null,
                    'fijada' => (bool) ($sesion['fijada'] ?? false),
                    'sin_acomodar' => (bool) ($sesion['sin_acomodar'] ?? false),
                ]);
            }
        });
    }
}
