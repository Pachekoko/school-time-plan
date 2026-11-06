<?php

namespace App\Solver;

use App\Models\Corrida;
use App\Models\Curso;
use App\Models\Espacio;
use App\Models\Franja;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Plantel;
use App\Models\PlantillaHoraria;
use App\Models\Profesor;
use App\Models\Sesion;
use App\Models\Sincronia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Las props de la página de una corrida. Cada una es perezosa: el sondeo pide solo
 * `corrida` y `sesiones`.
 */
final class PresentadorCorrida
{
    /**
     * Cómo se nombra y a dónde lleva cada tipo de entidad del diagnóstico.
     *
     * @var array<string, array{class-string<Model>, string, string}>
     */
    private const array ENTIDADES = [
        'grupo' => [Grupo::class, 'clave', 'grupos.edit'],
        'profesor' => [Profesor::class, 'nombre', 'profesores.edit'],
        'curso' => [Curso::class, 'clave', 'cursos.edit'],
        'plantel' => [Plantel::class, 'nombre', 'planteles.edit'],
        'espacio' => [Espacio::class, 'nombre', 'espacios.edit'],
        'sincronia' => [Sincronia::class, 'nombre', 'sincronias.edit'],
        'materia' => [Materia::class, 'nombre', 'materias.edit'],
        'plantilla' => [PlantillaHoraria::class, 'nombre', 'plantillas.franjas'],
    ];

    /** @var array<int, array<int, array<string, mixed>>> */
    private array $sesiones = [];

    /**
     * @return array<string, mixed>
     */
    public function props(Corrida $corrida): array
    {
        return [
            'corrida' => fn () => $this->corrida($corrida),
            'sesiones' => fn () => $this->sesiones($corrida),
            'franjas' => fn () => $this->franjas(),
            'catalogos' => fn () => $this->catalogos($corrida),
            'entidades' => fn () => $this->entidades($corrida),
            'reglas' => ParametrosCorrida::NOMBRES,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function corrida(Corrida $corrida): array
    {
        return [
            'id' => $corrida->id,
            'estado' => $corrida->estado->value,
            'estado_etiqueta' => $corrida->estado->etiqueta(),
            'activa' => $corrida->estado->activa(),
            'puntaje' => $corrida->puntaje,
            'desglose' => (object) ($corrida->desglose ?? []),
            'diagnostico' => $corrida->diagnostico ?? [],
            'estadisticas' => $corrida->estadisticas,
            'mensaje_error' => $corrida->mensaje_error,
            'tiempo_limite_s' => $corrida->tiempo_limite_s,
            'permitir_parcial' => (bool) ($corrida->parametros['permitir_parcial'] ?? false),
            'creada' => $corrida->created_at?->toIso8601String(),
            'terminada' => $corrida->terminada_en?->toIso8601String(),
        ];
    }

    /**
     * Cada sesión con su hora, calculada desde las franjas de la plantilla de sus grupos.
     *
     * @return array<int, array<string, mixed>>
     */
    private function sesiones(Corrida $corrida): array
    {
        if (isset($this->sesiones[$corrida->id])) {
            return $this->sesiones[$corrida->id];
        }

        $franjas = Franja::query()
            ->get(['plantilla_id', 'dia', 'orden', 'inicio', 'fin'])
            ->keyBy(fn (Franja $franja) => "{$franja->plantilla_id}-{$franja->dia}-{$franja->orden}");

        return $this->sesiones[$corrida->id] = $corrida->sesiones()
            ->with([
                'curso:id,clave,materia_id',
                'curso.materia:id,nombre',
                'curso.grupos:id,clave,plantilla_id',
                'profesor:id,nombre',
                'espacio:id,nombre',
            ])
            ->orderBy('id')
            ->get()
            ->map(function (Sesion $sesion) use ($franjas): array {
                $curso = $sesion->curso;
                $plantilla = $curso->grupos->first()?->plantilla_id;
                [$inicio, $fin] = $this->horas($franjas, $plantilla, $sesion);

                return [
                    'id' => $sesion->id,
                    'curso' => $curso->clave,
                    'materia' => $curso->materia->nombre,
                    'grupos' => $curso->grupos
                        ->sortBy('clave', SORT_NATURAL)
                        ->map(fn (Grupo $grupo) => ['id' => $grupo->id, 'nombre' => $grupo->clave])
                        ->values()
                        ->all(),
                    'profesor' => $sesion->profesor === null ? null : ['id' => $sesion->profesor->id, 'nombre' => $sesion->profesor->nombre],
                    'espacio' => $sesion->espacio === null ? null : ['id' => $sesion->espacio->id, 'nombre' => $sesion->espacio->nombre],
                    'dia' => $inicio === null ? null : $sesion->dia,
                    'inicio' => $inicio,
                    'fin' => $fin,
                    'plantilla_id' => $plantilla,
                    'sin_acomodar' => $sesion->sin_acomodar,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * El inicio de su primera franja y el fin de la última; null si no está acomodada o si
     * sus franjas ya no existen.
     *
     * @param  Collection<string, Franja>  $franjas
     * @return array{0: string|null, 1: string|null}
     */
    private function horas(Collection $franjas, ?int $plantilla, Sesion $sesion): array
    {
        if ($sesion->sin_acomodar || $plantilla === null || $sesion->dia === null || $sesion->orden_inicio === null) {
            return [null, null];
        }

        $primera = $franjas->get("{$plantilla}-{$sesion->dia}-{$sesion->orden_inicio}");
        $ultima = $franjas->get("{$plantilla}-{$sesion->dia}-".($sesion->orden_inicio + $sesion->duracion - 1));

        return $primera === null || $ultima === null ? [null, null] : [$primera->inicio, $ultima->fin];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function franjas(): array
    {
        return Franja::query()
            ->orderBy('plantilla_id')
            ->orderBy('dia')
            ->orderBy('orden')
            ->get(['plantilla_id', 'dia', 'orden', 'inicio', 'fin', 'tipo'])
            ->map(fn (Franja $franja) => [
                'plantilla_id' => $franja->plantilla_id,
                'dia' => $franja->dia,
                'orden' => $franja->orden,
                'inicio' => $franja->inicio,
                'fin' => $franja->fin,
                'tipo' => $franja->tipo->value,
            ])
            ->values()
            ->all();
    }

    /**
     * Los grupos del ciclo y los profesores y espacios que aparecen en la corrida, cada uno
     * con las plantillas de sus franjas.
     *
     * @return array<string, list<array{id: int, nombre: string, plantilla_ids: list<int>}>>
     */
    private function catalogos(Corrida $corrida): array
    {
        $grupos = $corrida->ciclo()->firstOrFail()->grupos()
            ->get(['id', 'clave', 'plantilla_id'])
            ->map(fn (Grupo $grupo) => ['id' => $grupo->id, 'nombre' => $grupo->clave, 'plantilla_ids' => [$grupo->plantilla_id]])
            ->all();

        $sesiones = $this->sesiones($corrida);
        $profesores = $this->apariciones($sesiones, 'profesor');
        $espacios = $this->apariciones($sesiones, 'espacio');

        return [
            'grupos' => $this->porNombre($grupos),
            'profesores' => $this->porNombre($profesores),
            'espacios' => $this->porNombre($espacios),
        ];
    }

    /**
     * Los profesores (o espacios) que aparecen en las sesiones, con sus plantillas.
     *
     * @param  array<int, array<string, mixed>>  $sesiones
     * @return array<int, array{id: int, nombre: string, plantilla_ids: list<int>}>
     */
    private function apariciones(array $sesiones, string $campo): array
    {
        $lista = [];

        foreach ($sesiones as $sesion) {
            /** @var array{id: int, nombre: string}|null $referencia */
            $referencia = $sesion[$campo];

            if ($referencia === null) {
                continue;
            }

            $id = $referencia['id'];
            $lista[$id] ??= ['id' => $id, 'nombre' => $referencia['nombre'], 'plantilla_ids' => []];
            $plantilla = $sesion['plantilla_id'];

            if (is_int($plantilla) && ! in_array($plantilla, $lista[$id]['plantilla_ids'], true)) {
                $lista[$id]['plantilla_ids'][] = $plantilla;
            }
        }

        return $lista;
    }

    /**
     * Para cada entidad del diagnóstico, su nombre y su enlace; null y null si ya no existe.
     */
    private function entidades(Corrida $corrida): object
    {
        $porTipo = [];

        foreach ($corrida->diagnostico ?? [] as $diagnostico) {
            foreach ($diagnostico['entidades'] ?? [] as $entidad) {
                $porTipo[(string) $entidad['tipo']][] = (string) $entidad['id'];
            }
        }

        $mapa = [];

        foreach ($porTipo as $tipo => $ids) {
            $ids = array_values(array_unique($ids));
            [$clase, $atributo, $ruta] = self::ENTIDADES[$tipo] ?? [null, null, null];
            $existentes = $clase === null
                ? collect()
                : $clase::query()->whereKey(array_filter($ids, ctype_digit(...)))->get()->keyBy(fn (Model $modelo) => (string) $modelo->getKey());

            foreach ($ids as $id) {
                $modelo = $existentes->get($id);
                $mapa["{$tipo}:{$id}"] = $modelo === null || $atributo === null || $ruta === null
                    ? ['nombre' => null, 'url' => null]
                    : ['nombre' => (string) $modelo->getAttribute($atributo), 'url' => route($ruta, $modelo)];
            }
        }

        return (object) $mapa;
    }

    /**
     * @param  array<int|string, array{id: int, nombre: string, plantilla_ids: list<int>}>  $lista
     * @return list<array{id: int, nombre: string, plantilla_ids: list<int>}>
     */
    private function porNombre(array $lista): array
    {
        $lista = array_values($lista);
        usort($lista, fn (array $a, array $b) => strnatcasecmp($a['nombre'], $b['nombre']));

        return $lista;
    }
}
