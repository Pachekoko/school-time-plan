<?php

namespace App\Excel;

use App\Domain\Patrones;
use App\Models\Ciclo;
use App\Models\Curso;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\PlanEstudios;
use App\Models\Plantel;
use App\Models\PlantillaHoraria;
use App\Models\Profesor;
use App\Support\EscuelaActual;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * La carga horaria de UTEG (hoja HORARIOS: una fila por grupo y materia) con las reglas de
 * apps/solver/bench/from_carga_xlsx.py. Las filas se pasan a las hojas de la plantilla
 * (Materias, Grupos, Profesores y Carga) y las revisa ImportadorPlantilla, así que conteos,
 * errores y reimportar funcionan igual. Lo que ya existe conserva lo que el archivo no trae.
 */
final class ImportadorCargaUteg
{
    public const string VACANTE = 'GENERICO';

    private const string HOJA = 'HORARIOS';

    /** El largo de las claves en la base. */
    private const int CLAVE_MAXIMA = 30;

    public function __construct(private ImportadorPlantilla $importador) {}

    /**
     * @param  array{plantel_id: int, plantilla_1y2_id: int, plantilla_3a6_id: int, plan_id: int}  $opciones
     */
    public function revisar(string $ruta, array $opciones, Ciclo $ciclo): PlanImportacion
    {
        return app(EscuelaActual::class)->usar($ciclo->escuela_id, function () use ($ruta, $opciones, $ciclo): PlanImportacion {
            $previo = new PlanImportacion($ciclo->escuela_id, $ciclo->id);
            $filas = $this->leer($ruta, $previo);
            $plan = $this->importador->revisar($filas === [] ? [] : $this->libro($filas, $opciones, $ciclo), $ciclo);

            foreach ($previo->errores() as $error) {
                $plan->error($error['hoja'], $error['fila'], $error['columna'], $error['mensaje']);
            }

            foreach ($previo->avisos() as $aviso) {
                $plan->aviso($aviso['hoja'], $aviso['fila'], $aviso['mensaje']);
            }

            return $plan;
        });
    }

    /**
     * Las filas de HORARIOS con algo de grupo y materia. Los encabezados se buscan en las filas
     * 1 a 5; las columnas se encuentran por nombre (GRUPO puede venir dos veces: A y B).
     *
     * @return list<array{fila: int, grupo: string, materia: string, horas: int, semestre: int, docente: string}>
     */
    private function leer(string $ruta, PlanImportacion $previo): array
    {
        $lector = IOFactory::createReader('Xlsx');
        $lector->setReadDataOnly(true);
        $hoja = null;

        foreach ($lector->load($ruta)->getWorksheetIterator() as $candidata) {
            if (Normalizador::encabezado($candidata->getTitle()) === 'horarios') {
                $hoja = $candidata;

                break;
            }
        }

        if ($hoja === null) {
            $previo->error(self::HOJA, 1, '', 'El archivo no tiene la hoja HORARIOS.');

            return [];
        }

        [$columnas, $filaEncabezados] = $this->encabezados($hoja);

        if ($columnas === null) {
            $previo->error(self::HOJA, 1, '', 'La hoja HORARIOS no tiene las columnas GRUPO, MATERIA, HORAS y Docente.');

            return [];
        }

        $filas = [];
        $nombrePorNumero = [];
        $avisados = [];

        for ($n = $filaEncabezados + 1; $n <= $hoja->getHighestDataRow(); $n++) {
            $valores = $this->fila($hoja, $n);
            $grupo = Normalizador::texto($valores[$columnas['grupo']] ?? null);
            $materia = Normalizador::texto($valores[$columnas['materia']] ?? null);

            if ($grupo === null || $materia === null) {
                continue;
            }

            $a = $columnas['a'] === null ? null : Normalizador::texto($valores[$columnas['a']] ?? null);

            if ($a !== null && $a !== $grupo) {
                $previo->aviso(self::HOJA, null, "Fila {$n}: la columna A ({$a}) no coincide con la B ({$grupo}); se usa la B.");
            }

            try {
                $horas = Normalizador::entero($valores[$columnas['horas']] ?? null);
            } catch (ValorInvalido $e) {
                $previo->error(self::HOJA, $n, 'HORAS', $e->getMessage());

                continue;
            }

            if ($horas === null || $horas < 1 || $horas > 40) {
                $previo->error(self::HOJA, $n, 'HORAS', 'las horas deben ir de 1 a 40');

                continue;
            }

            $semestre = $this->semestre($grupo);

            if ($semestre === null) {
                $previo->error(self::HOJA, $n, 'GRUPO', "el grupo {$grupo} no empieza con su semestre");

                continue;
            }

            $docente = Normalizador::texto($valores[$columnas['docente']] ?? null) ?? self::VACANTE;
            $numero = $columnas['numero'] === null ? null : Normalizador::texto($valores[$columnas['numero']] ?? null);

            if (! $this->esVacante($docente)) {
                if ($numero === null) {
                    $previo->aviso(self::HOJA, null, "Fila {$n}: sin N° docente.");
                } elseif (isset($nombrePorNumero[$numero]) && $nombrePorNumero[$numero] !== $docente) {
                    if (! isset($avisados["{$numero}|{$docente}"])) {
                        $avisados["{$numero}|{$docente}"] = true;
                        $previo->aviso(self::HOJA, null, "El N° docente {$numero} aparece con dos nombres: {$nombrePorNumero[$numero]} y {$docente}.");
                    }
                } else {
                    $nombrePorNumero[$numero] = $docente;
                }
            }

            $filas[] = [
                'fila' => $n, 'grupo' => $grupo, 'materia' => $this->nombreMateria($materia), 'horas' => $horas,
                'semestre' => $semestre, 'docente' => $this->esVacante($docente) ? self::VACANTE : $docente,
            ];
        }

        return $filas;
    }

    /**
     * @return array{0: array{a: int|null, grupo: int, materia: int, horas: int, numero: int|null, docente: int}|null, 1: int}
     */
    private function encabezados(Worksheet $hoja): array
    {
        for ($n = 1; $n <= min(5, $hoja->getHighestDataRow()); $n++) {
            $encabezados = array_map(fn (mixed $valor) => Normalizador::encabezado((string) Normalizador::texto($valor)), $this->fila($hoja, $n));
            $grupos = array_keys($encabezados, 'grupo', true);
            $materia = array_search('materia', $encabezados, true);
            $horas = array_search('horas', $encabezados, true);
            $docente = array_search('docente', $encabezados, true);

            if ($grupos === [] || $materia === false || $horas === false || $docente === false) {
                continue;
            }

            $numero = null;

            foreach ($encabezados as $i => $encabezado) {
                if ($encabezado !== 'docente' && str_contains($encabezado, 'docente')) {
                    $numero = $i;

                    break;
                }
            }

            return [[
                'a' => count($grupos) > 1 ? $grupos[0] : null,
                'grupo' => $grupos[count($grupos) - 1],
                'materia' => $materia, 'horas' => $horas, 'numero' => $numero, 'docente' => $docente,
            ], $n];
        }

        return [null, 0];
    }

    /**
     * @return array<int, mixed>
     */
    private function fila(Worksheet $hoja, int $n): array
    {
        return $hoja->rangeToArray("A{$n}:{$hoja->getHighestDataColumn()}{$n}", null, true, false)[0];
    }

    /**
     * Las hojas de la plantilla que salen de las filas de HORARIOS.
     *
     * @param  list<array{fila: int, grupo: string, materia: string, horas: int, semestre: int, docente: string}>  $filas
     * @param  array{plantel_id: int, plantilla_1y2_id: int, plantilla_3a6_id: int, plan_id: int}  $opciones
     * @return array<string, array{encabezados: list<string>, filas: list<array{fila: int, celdas: array<string, mixed>}>}>
     */
    private function libro(array $filas, array $opciones, Ciclo $ciclo): array
    {
        $plantel = Plantel::query()->findOrFail($opciones['plantel_id'])->clave;
        $de1y2 = PlantillaHoraria::query()->findOrFail($opciones['plantilla_1y2_id'])->clave;
        $de3a6 = PlantillaHoraria::query()->findOrFail($opciones['plantilla_3a6_id'])->clave;
        $planDeEstudios = PlanEstudios::query()->findOrFail($opciones['plan_id'])->clave;

        $materiasBase = Materia::query()->with('plan:id,clave')->get()->keyBy('clave');
        $gruposBase = $ciclo->grupos()->with(['plantel:id,clave', 'plantilla:id,clave', 'plan:id,clave'])->get()->keyBy('clave');
        $profesoresBase = Profesor::query()->with(['planteles:id,clave', 'materias:id,clave'])->get()->keyBy('clave');
        $cursosBase = $ciclo->cursos()->with(['grupos:id,clave', 'materia:id,clave', 'sincronia:id,clave'])->get();

        $materias = [];
        $grupos = [];
        $docentes = [];
        $carga = [];
        $tomadas = [];

        foreach ($filas as $f) {
            $claveMateria = $this->clave($f['materia'], "-{$f['horas']}H");
            $existente = $materiasBase->get($claveMateria);
            $materias[$claveMateria] ??= ['fila' => $f['fila'], 'celdas' => $existente === null
                ? ['clave' => $claveMateria, 'plan' => $planDeEstudios, 'nombre' => $f['materia'], 'semestre' => $f['semestre'], 'horas_semana' => $f['horas'], 'patrones' => null, 'tipo_espacio' => 'aula']
                : [
                    'clave' => $claveMateria, 'plan' => $existente->plan->clave, 'nombre' => $existente->nombre, 'semestre' => $existente->semestre,
                    'horas_semana' => $existente->horas_semana, 'patrones' => $existente->patrones === null ? null : Patrones::formatear($existente->patrones),
                    'tipo_espacio' => $existente->tipo_espacio->value,
                ]];

            $grupo = $gruposBase->get($f['grupo']);
            $grupos[$f['grupo']] ??= ['fila' => $f['fila'], 'celdas' => $grupo === null
                ? ['clave' => $f['grupo'], 'plantel' => $plantel, 'plantilla' => $f['semestre'] <= 2 ? $de1y2 : $de3a6, 'plan' => $planDeEstudios, 'semestre' => $f['semestre'], 'alumnos' => 30]
                : [
                    'clave' => $f['grupo'], 'plantel' => $grupo->plantel->clave, 'plantilla' => $grupo->plantilla->clave,
                    'plan' => $grupo->plan->clave, 'semestre' => $grupo->semestre, 'alumnos' => $grupo->alumnos,
                ]];

            $claveProfesor = null;

            if ($f['docente'] !== self::VACANTE) {
                $claveProfesor = $this->clave($f['docente']);
                $docentes[$claveProfesor] ??= ['fila' => $f['fila'], 'nombre' => $f['docente'], 'horas' => 0, 'materias' => []];
                $docentes[$claveProfesor]['horas'] += $f['horas'];
                $docentes[$claveProfesor]['materias'][] = $claveMateria;
            }

            // El curso que ya existe con esa materia y ese grupo conserva su clave, su sincronía y sus
            // alumnos; con varios iguales (Inglés por nivel), cada fila toma el siguiente en orden de clave.
            $curso = $cursosBase->filter(fn (Curso $c) => $c->materia->clave === $claveMateria
                && $c->grupos->pluck('clave')->all() === [$f['grupo']] && ! in_array($c->clave, $tomadas, true))
                ->sortBy('clave', SORT_NATURAL)->first();

            if ($curso !== null) {
                $tomadas[] = $curso->clave;
            }

            $carga[] = ['fila' => $f['fila'], 'celdas' => [
                'clave' => $curso?->clave, 'grupos' => $f['grupo'], 'materia' => $claveMateria, 'profesor' => $claveProfesor,
                'vacante' => $claveProfesor === null ? 'S' : 'N', 'sincronia' => $curso?->sincronia?->clave, 'alumnos' => $curso?->alumnos,
            ]];
        }

        $profesores = [];

        foreach ($docentes as $clave => $docente) {
            $existente = $profesoresBase->get($clave);
            $profesores[] = ['fila' => $docente['fila'], 'celdas' => $existente === null
                ? [
                    'clave' => $clave, 'nombre' => $docente['nombre'], 'contrato' => 'asignatura', 'horas_min' => 0,
                    'horas_max' => min(168, $docente['horas']), 'max_dia' => 7, 'sin_huecos' => 'N',
                    'planteles' => $plantel, 'materias' => implode(', ', array_unique($docente['materias'])),
                ]
                : [
                    'clave' => $clave, 'nombre' => $existente->nombre, 'contrato' => $existente->contrato->value,
                    'horas_min' => $existente->horas_min, 'horas_max' => $existente->horas_max, 'max_dia' => $existente->max_dia,
                    'sin_huecos' => $existente->sin_huecos ? 'S' : 'N',
                    'planteles' => implode(', ', array_unique([...$existente->planteles->pluck('clave')->all(), $plantel])),
                    'materias' => implode(', ', array_unique([...$existente->materias->pluck('clave')->all(), ...$docente['materias']])),
                ]];
        }

        return [
            'Materias' => $this->hoja('Materias', array_values($materias)),
            'Grupos' => $this->hoja('Grupos', array_values($grupos)),
            'Profesores' => $this->hoja('Profesores', $profesores),
            'Carga' => $this->hoja('Carga', $carga),
        ];
    }

    /**
     * Una hoja de la plantilla con llaves de celda normalizadas, como las deja LectorLibro.
     *
     * @param  list<array{fila: int, celdas: array<string, mixed>}>  $filas
     * @return array{encabezados: list<string>, filas: list<array{fila: int, celdas: array<string, mixed>}>}
     */
    private function hoja(string $nombre, array $filas): array
    {
        return [
            'encabezados' => Hojas::COLUMNAS[$nombre],
            'filas' => array_map(fn (array $fila) => [
                'fila' => $fila['fila'],
                'celdas' => array_combine(array_map(Normalizador::encabezado(...), array_keys($fila['celdas'])), array_values($fila['celdas'])),
            ], $filas),
        ];
    }

    /**
     * «Tutoria de trayectoria||» → «Tutoria de trayectoria II»: el | final es un número romano.
     */
    private function nombreMateria(string $nombre): string
    {
        return preg_match('/^(.*?)\s*(\|+)$/u', $nombre, $partes) === 1
            ? $partes[1].' '.str_repeat('I', strlen($partes[2]))
            : $nombre;
    }

    /**
     * Los dos primeros dígitos del grupo, salvo los 09…, que son de 6.º del plan anterior.
     */
    private function semestre(string $grupo): ?int
    {
        if (str_starts_with($grupo, '09')) {
            return 6;
        }

        $inicio = substr($grupo, 0, 2);

        return ctype_digit($inicio) && (int) $inicio >= 1 && (int) $inicio <= 12 ? (int) $inicio : null;
    }

    private function esVacante(string $docente): bool
    {
        return Normalizador::comparable($docente) === 'generico';
    }

    /**
     * MATEMATICAS-I-3H; si no cabe, se recorta y se le pega un pedazo de hash para que siga
     * siendo único y estable al reimportar.
     */
    private function clave(string $texto, string $sufijo = ''): string
    {
        $base = Str::upper(Str::slug($texto));

        if (strlen($base.$sufijo) <= self::CLAVE_MAXIMA) {
            return $base.$sufijo;
        }

        return rtrim(substr($base, 0, self::CLAVE_MAXIMA - strlen($sufijo) - 5), '-').'-'.strtoupper(substr(md5($texto), 0, 4)).$sufijo;
    }
}
