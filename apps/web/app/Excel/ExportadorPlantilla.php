<?php

namespace App\Excel;

use App\Domain\Patrones;
use App\Enums\Contrato;
use App\Enums\TipoEspacio;
use App\Enums\TipoFranja;
use App\Models\Ciclo;
use App\Models\Curso;
use App\Models\Disponibilidad;
use App\Models\Escuela;
use App\Models\Espacio;
use App\Models\Franja;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Plantel;
use App\Models\PlantillaHoraria;
use App\Models\Profesor;
use App\Support\EscuelaActual;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * La plantilla de Excel de la escuela actual, vacía o con sus datos (ida y vuelta: lo que
 * exporta se puede volver a importar). Grupos y Carga son del ciclo actual.
 */
final class ExportadorPlantilla
{
    private const string GRIS = 'D9D9D9';

    public function libro(bool $conDatos): Spreadsheet
    {
        $escuela = Escuela::query()->findOrFail(app(EscuelaActual::class)->id());
        $ciclo = $escuela->cicloActual()->first();
        $franjas = $this->columnasDisponibilidad();

        $libro = new Spreadsheet;
        $libro->removeSheetByIndex(0);
        $this->instrucciones($libro->createSheet());

        foreach (Hojas::COLUMNAS as $nombre => $columnas) {
            $hoja = $libro->createSheet()->setTitle($nombre);
            $encabezados = $nombre === 'Disponibilidad'
                ? [...$columnas, ...array_map(fn (array $f) => Hojas::DIAS[$f[0]].' '.$f[1], $franjas)]
                : $columnas;

            $this->escribir($hoja, 1, $encabezados);
            $hoja->getStyle('1:1')->getFont()->setBold(true);
            $hoja->freezePane('A2');

            if ($conDatos) {
                match ($nombre) {
                    'Disponibilidad' => $this->disponibilidad($hoja, $franjas),
                    default => $this->filas($hoja, $this->datos($nombre, $ciclo)),
                };
            }

            $this->listas($hoja, $nombre, count($encabezados));

            foreach (range(1, count($encabezados)) as $columna) {
                $hoja->getColumnDimensionByColumn($columna)->setAutoSize(true);
            }
        }

        $libro->setActiveSheetIndex(0);

        return $libro;
    }

    private function instrucciones(Worksheet $hoja): void
    {
        $hoja->setTitle('Instrucciones');
        $this->escribir($hoja, 1, ['Hoja', 'Qué capturar']);
        $hoja->getStyle('1:1')->getFont()->setBold(true);

        $fila = 2;
        foreach (Hojas::INSTRUCCIONES as $nombre => $texto) {
            $this->escribir($hoja, $fila++, [$nombre, $texto]);
        }

        $this->escribir($hoja, $fila + 1, ['', 'Importar nunca borra: lo que no venga en el archivo se queda como está.']);
        $hoja->getColumnDimension('A')->setWidth(16);
        $hoja->getColumnDimension('B')->setWidth(120);
    }

    /**
     * Las franjas de clase de todas las plantillas como [día, inicio], sin repetir y en orden.
     *
     * @return list<array{0: int, 1: string}>
     */
    private function columnasDisponibilidad(): array
    {
        return array_values(Franja::query()
            ->where('tipo', TipoFranja::Clase)
            ->distinct()
            ->orderBy('dia')
            ->orderBy('inicio')
            ->get(['dia', 'inicio'])
            ->map(fn (Franja $franja) => [$franja->dia, $franja->inicio])
            ->all());
    }

    /**
     * @return array<int, array<int, string|int|null>>
     */
    private function datos(string $hoja, ?Ciclo $ciclo): array
    {
        return match ($hoja) {
            'Planteles' => Plantel::query()->orderBy('clave')->get()
                ->map(fn (Plantel $p) => [$p->clave, $p->nombre, $p->direccion])->values()->all(),
            'Espacios' => Espacio::query()->with('plantel:id,clave')->orderBy('clave')->get()
                ->map(fn (Espacio $e) => [$e->clave, $e->plantel->clave, $e->nombre, $e->tipo->value, $e->capacidad])->values()->all(),
            'Plantillas' => PlantillaHoraria::query()->with(['franjas' => fn ($q) => $q->orderBy('dia')->orderBy('orden')])->orderBy('clave')->get()
                ->flatMap(fn (PlantillaHoraria $p) => $p->franjas->map(fn (Franja $f) => [
                    $p->clave, Hojas::DIAS[$f->dia], $f->orden, $f->inicio, $f->fin, $f->tipo->value,
                ]))->values()->all(),
            'Materias' => Materia::query()->with('plan:id,clave')->orderBy('semestre')->orderBy('clave')->get()
                ->map(fn (Materia $m) => [
                    $m->clave, $m->plan->clave, $m->nombre, $m->semestre, $m->horas_semana,
                    $m->patrones === null ? null : Patrones::formatear($m->patrones), $m->tipo_espacio->value,
                ])->values()->all(),
            'Grupos' => $ciclo === null ? [] : $ciclo->grupos()->with(['plantel:id,clave', 'plantilla:id,clave', 'plan:id,clave'])->orderBy('clave')->get()
                ->map(fn (Grupo $g) => [$g->clave, $g->plantel->clave, $g->plantilla->clave, $g->plan->clave, $g->semestre, $g->alumnos])->values()->all(),
            'Profesores' => Profesor::query()->with(['planteles:id,clave', 'materias:id,clave'])->orderBy('clave')->get()
                ->map(fn (Profesor $p) => [
                    $p->clave, $p->nombre, $p->contrato->value, $p->horas_min, $p->horas_max, $p->max_dia, $p->sin_huecos ? 'S' : 'N',
                    $this->claves($p->planteles->pluck('clave')->all()), $this->claves($p->materias->pluck('clave')->all()),
                ])->values()->all(),
            'Carga' => $ciclo === null ? [] : $ciclo->cursos()->with(['grupos:id,clave', 'materia:id,clave', 'profesor:id,clave', 'sincronia:id,clave'])->orderBy('clave')->get()
                ->map(fn (Curso $c) => [
                    $c->clave, $this->claves($c->grupos->pluck('clave')->all()), $c->materia->clave, $c->profesor?->clave,
                    $c->vacante ? 'S' : 'N', $c->sincronia?->clave, $c->alumnos,
                ])->values()->all(),
            default => [],
        };
    }

    /**
     * Una fila por profesor y plantilla. D va vacío; P y N con su letra; gris donde la
     * plantilla no tiene esa franja.
     *
     * @param  list<array{0: int, 1: string}>  $columnas
     */
    private function disponibilidad(Worksheet $hoja, array $columnas): void
    {
        $plantillas = PlantillaHoraria::query()->with(['franjas' => fn ($q) => $q->where('tipo', TipoFranja::Clase)])->orderBy('clave')->get();
        $estados = Disponibilidad::query()->get(['profesor_id', 'franja_id', 'estado'])
            ->mapWithKeys(fn (Disponibilidad $d) => ["{$d->profesor_id}-{$d->franja_id}" => $d->estado->letra()]);
        $fila = 2;

        foreach (Profesor::query()->orderBy('clave')->get(['id', 'clave']) as $profesor) {
            foreach ($plantillas as $plantilla) {
                $franjas = $plantilla->franjas->keyBy(fn (Franja $f) => "{$f->dia} {$f->inicio}");
                $valores = [$profesor->clave, $plantilla->clave];

                foreach ($columnas as $i => [$dia, $inicio]) {
                    $franja = $franjas->get("{$dia} {$inicio}");

                    if ($franja === null) {
                        $celda = Coordinate::stringFromColumnIndex($i + 3).$fila;
                        $hoja->getStyle($celda)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::GRIS);
                    }

                    $valores[] = $franja === null ? null : ($estados->get("{$profesor->id}-{$franja->id}"));
                }

                $this->escribir($hoja, $fila++, $valores);
            }
        }
    }

    /**
     * Las listas desplegables de cada hoja.
     */
    private function listas(Worksheet $hoja, string $nombre, int $columnas): void
    {
        $tipos = array_map(fn (TipoEspacio $t) => $t->value, TipoEspacio::cases());

        switch ($nombre) {
            case 'Espacios':
                $this->lista($hoja, 'D', $tipos);
                break;
            case 'Plantillas':
                $this->lista($hoja, 'B', Hojas::DIAS);
                $this->lista($hoja, 'F', ['clase', 'receso']);
                break;
            case 'Materias':
                $this->lista($hoja, 'G', $tipos);
                break;
            case 'Profesores':
                $this->lista($hoja, 'C', array_map(fn (Contrato $c) => $c->value, Contrato::cases()));
                $this->lista($hoja, 'G', ['S', 'N']);
                break;
            case 'Carga':
                $this->lista($hoja, 'E', ['S', 'N']);
                break;
            case 'Disponibilidad':
                if ($columnas > 2) {
                    $this->lista($hoja, 'C', ['D', 'P', 'N'], Coordinate::stringFromColumnIndex($columnas));
                }
                break;
        }
    }

    /**
     * @param  list<string>  $valores
     */
    private function lista(Worksheet $hoja, string $desde, array $valores, ?string $hasta = null): void
    {
        $validacion = (new DataValidation)
            ->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)
            ->setShowDropDown(true)
            ->setShowErrorMessage(true)
            ->setErrorTitle('Valor no válido')
            ->setError('Elige un valor de la lista.')
            ->setFormula1('"'.implode(',', $valores).'"');

        $hoja->setDataValidation($desde.'2:'.($hasta ?? $desde).Hojas::FILAS_CON_LISTA, $validacion);
    }

    /**
     * @param  array<int, array<int, string|int|null>>  $filas
     */
    private function filas(Worksheet $hoja, array $filas): void
    {
        foreach ($filas as $i => $valores) {
            $this->escribir($hoja, $i + 2, $valores);
        }
    }

    /**
     * Los textos van como texto (para que «07:00» o «001» no cambien) y los números como número.
     *
     * @param  array<int, string|int|null>  $valores
     */
    private function escribir(Worksheet $hoja, int $fila, array $valores): void
    {
        foreach ($valores as $i => $valor) {
            if ($valor === null || $valor === '') {
                continue;
            }

            $celda = Coordinate::stringFromColumnIndex($i + 1).$fila;

            is_int($valor)
                ? $hoja->setCellValue($celda, $valor)
                : $hoja->setCellValueExplicit($celda, $valor, DataType::TYPE_STRING);
        }
    }

    /**
     * @param  array<int, string>  $claves
     */
    private function claves(array $claves): string
    {
        natsort($claves);

        return implode(', ', $claves);
    }
}
