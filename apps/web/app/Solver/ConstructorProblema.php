<?php

namespace App\Solver;

use App\Enums\EstadoDisponibilidad;
use App\Models\Ciclo;
use App\Models\Curso;
use App\Models\Espacio;
use App\Models\Franja;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Plantel;
use App\Models\PlantillaHoraria;
use App\Models\Profesor;
use App\Support\EscuelaActual;

/**
 * Arma el `SolveRequest` del contrato (`contracts/solve-request.schema.json`) desde la base:
 * toda la escuela del ciclo (plantillas, planteles, espacios, materias y profesores) y solo
 * los grupos y cursos del ciclo. Los ids del problema son los de la base como cadenas.
 */
final class ConstructorProblema
{
    /**
     * @param  array<string, mixed>  $parametros  el de ParametrosCorrida::normalizar
     * @return array<string, mixed>
     */
    public function construir(Ciclo $ciclo, array $parametros): array
    {
        return app(EscuelaActual::class)->usar($ciclo->escuela_id, fn (): array => [
            'schema_version' => '1',
            'parametros' => $parametros,
            'plantillas' => $this->plantillas(),
            'planteles' => $this->planteles(),
            'espacios' => $this->espacios(),
            'materias' => $this->materias(),
            'grupos' => $this->grupos($ciclo),
            'profesores' => $this->profesores(),
            'cursos' => $this->cursos($ciclo),
            'fijadas' => [],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function plantillas(): array
    {
        return PlantillaHoraria::query()
            ->with(['franjas' => fn ($consulta) => $consulta->orderBy('dia')->orderBy('orden')])
            ->orderBy('id')
            ->get()
            ->map(fn (PlantillaHoraria $plantilla) => [
                'id' => (string) $plantilla->id,
                'nombre' => $plantilla->nombre,
                'franjas' => $plantilla->franjas->map(fn (Franja $franja) => [
                    'dia' => $franja->dia,
                    'orden' => $franja->orden,
                    'inicio' => $franja->inicio,
                    'fin' => $franja->fin,
                    'tipo' => $franja->tipo->value,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function planteles(): array
    {
        return Plantel::query()
            ->orderBy('id')
            ->get(['id', 'nombre'])
            ->map(fn (Plantel $plantel) => ['id' => (string) $plantel->id, 'nombre' => $plantel->nombre])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function espacios(): array
    {
        return Espacio::query()
            ->orderBy('id')
            ->get()
            ->map(fn (Espacio $espacio) => [
                'id' => (string) $espacio->id,
                'plantel' => (string) $espacio->plantel_id,
                'nombre' => $espacio->nombre,
                'tipo' => $espacio->tipo->value,
                'capacidad' => $espacio->capacidad,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function materias(): array
    {
        return Materia::query()
            ->orderBy('id')
            ->get()
            ->map(fn (Materia $materia) => [
                'id' => (string) $materia->id,
                'nombre' => $materia->nombre,
                'horas_semana' => $materia->horas_semana,
                'patrones' => $materia->patronesEfectivos(),
                'tipo_espacio' => $materia->tipo_espacio->value,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function grupos(Ciclo $ciclo): array
    {
        return $ciclo->grupos()
            ->orderBy('id')
            ->get()
            ->map(fn (Grupo $grupo) => [
                'id' => (string) $grupo->id,
                'nombre' => $grupo->clave,
                'plantel' => (string) $grupo->plantel_id,
                'plantilla' => (string) $grupo->plantilla_id,
                'alumnos' => $grupo->alumnos,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function profesores(): array
    {
        return Profesor::query()
            ->with(['planteles:id', 'materias:id', 'disponibilidades.franja:id,plantilla_id,dia,orden'])
            ->orderBy('id')
            ->get()
            ->map(fn (Profesor $profesor) => [
                'id' => (string) $profesor->id,
                'nombre' => $profesor->nombre,
                'planteles' => $this->ids($profesor->planteles->modelKeys()),
                'materias' => $this->ids($profesor->materias->modelKeys()),
                'horas_min' => $profesor->horas_min,
                'horas_max' => $profesor->horas_max,
                'max_dia' => $profesor->max_dia,
                'no_disponible' => $this->refFranjas($profesor, EstadoDisponibilidad::NoDisponible),
                'preferente' => $this->refFranjas($profesor, EstadoDisponibilidad::Preferente),
                'sin_huecos' => $profesor->sin_huecos,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function cursos(Ciclo $ciclo): array
    {
        return $ciclo->cursos()
            ->with('grupos:id')
            ->orderBy('id')
            ->get()
            ->map(fn (Curso $curso) => [
                'id' => (string) $curso->id,
                'materia' => (string) $curso->materia_id,
                'grupos' => $this->ids($curso->grupos->modelKeys()),
                'profesor' => $curso->profesor_id === null ? null : (string) $curso->profesor_id,
                'sincronia' => $curso->sincronia_id === null ? null : (string) $curso->sincronia_id,
                'alumnos' => $curso->alumnos,
                'vacante' => $curso->vacante,
            ])
            ->values()
            ->all();
    }

    /**
     * Las franjas del profesor con ese estado, como `{plantilla, dia, orden}` en orden.
     *
     * @return list<array{plantilla: string, dia: int, orden: int}>
     */
    private function refFranjas(Profesor $profesor, EstadoDisponibilidad $estado): array
    {
        $refs = [];

        foreach ($profesor->disponibilidades as $disponibilidad) {
            $franja = $disponibilidad->franja;

            if ($disponibilidad->estado === $estado && $franja !== null) {
                $refs[] = ['plantilla' => (string) $franja->plantilla_id, 'dia' => $franja->dia, 'orden' => $franja->orden];
            }
        }

        usort($refs, fn (array $a, array $b) => [(int) $a['plantilla'], $a['dia'], $a['orden']] <=> [(int) $b['plantilla'], $b['dia'], $b['orden']]);

        return $refs;
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return list<string>
     */
    private function ids(array $ids): array
    {
        sort($ids);

        return array_map(fn (mixed $id) => (string) $id, $ids);
    }
}
