<?php

namespace App\Excel;

use App\Domain\ClaveCurso;
use App\Domain\CursosCompartidos;
use App\Domain\Patrones;
use App\Domain\ReglasFranjas;
use App\Enums\Contrato;
use App\Enums\TipoEspacio;
use App\Enums\TipoFranja;
use App\Models\Ciclo;
use App\Models\Curso;
use App\Models\Espacio;
use App\Models\Franja;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\PlanEstudios;
use App\Models\Plantel;
use App\Models\PlantillaHoraria;
use App\Models\Profesor;
use App\Models\Sincronia;
use App\Support\EscuelaActual;
use Closure;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Revisa un libro con el formato de la plantilla y arma el plan de importación. Las hojas
 * se leen en orden de dependencias y cada referencia se busca en la base de la escuela o en
 * lo que crea el mismo archivo. Nunca borra.
 *
 * @phpstan-type Fila array{fila: int, celdas: array<string, mixed>}
 */
final class ImportadorPlantilla
{
    /** Columnas sin las que no se lee una hoja. */
    private const array REQUERIDAS = [
        'Planteles' => ['clave', 'nombre'],
        'Espacios' => ['clave', 'plantel', 'nombre', 'tipo', 'capacidad'],
        'Plantillas' => ['plantilla', 'día', 'orden', 'inicio', 'fin'],
        'Materias' => ['clave', 'plan', 'nombre', 'semestre', 'horas_semana'],
        'Grupos' => ['clave', 'plantel', 'plantilla', 'plan', 'semestre', 'alumnos'],
        'Profesores' => ['clave', 'nombre', 'horas_max', 'max_dia'],
        'Disponibilidad' => ['clave_profesor', 'plantilla'],
        'Carga' => ['grupos', 'materia'],
    ];

    private PlanImportacion $plan;

    private Ciclo $ciclo;

    /** @var Collection<string, Plantel> */
    private Collection $planteles;

    /** @var Collection<string, Espacio> */
    private Collection $espacios;

    /** @var Collection<string, PlantillaHoraria> */
    private Collection $plantillas;

    /** @var Collection<string, PlanEstudios> */
    private Collection $planes;

    /** @var Collection<string, Materia> */
    private Collection $materias;

    /** @var Collection<string, Grupo> */
    private Collection $grupos;

    /** @var Collection<string, Profesor> */
    private Collection $profesores;

    /** @var Collection<string, Sincronia> */
    private Collection $sincronias;

    /** @var Collection<string, Curso> */
    private Collection $cursos;

    /** @var array<string, string> «profesor|plantilla|día|orden» => P o N, lo que hay en la base */
    private array $estados = [];

    // Lo que trae el archivo, por clave.

    /** @var array<string, true> */
    private array $plantelesArchivo = [];

    /** @var array<string, true> */
    private array $plantillasArchivo = [];

    /** @var array<string, true> */
    private array $planesNuevos = [];

    /** @var array<string, true> */
    private array $materiasArchivo = [];

    /** @var array<string, array{plantel: string, plantilla: string}> */
    private array $gruposArchivo = [];

    /** @var array<string, int> grupo => su fila en la hoja Grupos */
    private array $filaDeGrupo = [];

    /** @var array<string, true> los cursos que trae la hoja Carga */
    private array $cursosArchivo = [];

    /** @var array<string, true> */
    private array $profesoresArchivo = [];

    /** @var array<string, true> */
    private array $sincroniasNuevas = [];

    /** @var array<string, array<string, int>> plantilla => «día inicio» => orden, de las franjas de clase finales */
    private array $franjasDeClase = [];

    /** @var list<string> los encabezados (normalizados) de la hoja que se está revisando */
    private array $presentes = [];

    /**
     * @param  array<string, array{encabezados: list<string>, filas: list<Fila>}>  $libro  el de LectorLibro::leer
     */
    public function revisar(array $libro, Ciclo $ciclo): PlanImportacion
    {
        return app(EscuelaActual::class)->usar($ciclo->escuela_id, function () use ($libro, $ciclo): PlanImportacion {
            $this->plan = new PlanImportacion($ciclo->escuela_id, $ciclo->id);
            $this->ciclo = $ciclo;
            $this->plantelesArchivo = $this->plantillasArchivo = $this->planesNuevos = [];
            $this->materiasArchivo = $this->profesoresArchivo = $this->sincroniasNuevas = [];
            $this->gruposArchivo = $this->franjasDeClase = $this->estados = [];
            $this->filaDeGrupo = $this->cursosArchivo = [];
            $this->cargarBase();
            $hojas = $this->hojasConocidas($libro);

            foreach (array_keys(Hojas::COLUMNAS) as $nombre) {
                $hoja = $hojas[$nombre] ?? null;

                if ($hoja !== null) {
                    $this->plan->hoja($nombre);
                }

                $filas = $hoja !== null && $this->columnas($nombre, $hoja['encabezados']) ? $hoja['filas'] : [];
                $this->presentes = array_map(Normalizador::encabezado(...), $hoja['encabezados'] ?? []);

                switch ($nombre) {
                    case 'Planteles':
                        $this->revisarPlanteles($filas);
                        break;
                    case 'Espacios':
                        $this->revisarEspacios($filas);
                        break;
                    case 'Plantillas':
                        $this->revisarPlantillas($filas);
                        break;
                    case 'Materias':
                        $this->revisarMaterias($filas);
                        break;
                    case 'Grupos':
                        $this->revisarGrupos($filas);
                        break;
                    case 'Profesores':
                        $this->revisarProfesores($filas);
                        break;
                    case 'Disponibilidad':
                        $this->revisarDisponibilidad($hoja['encabezados'] ?? [], $filas);
                        break;
                    case 'Carga':
                        $this->revisarCarga($filas);
                        break;
                }
            }

            $this->revisarGruposMovidos();

            return $this->plan;
        });
    }

    private function cargarBase(): void
    {
        $this->planteles = Plantel::query()->get()->keyBy('clave');
        $this->espacios = Espacio::query()->with('plantel:id,clave')->get()->keyBy('clave');
        $this->plantillas = PlantillaHoraria::query()->with('franjas')->get()->keyBy('clave');
        $this->planes = PlanEstudios::query()->get()->keyBy('clave');
        $this->materias = Materia::query()->with('plan:id,clave')->get()->keyBy('clave');
        $this->grupos = $this->ciclo->grupos()->with(['plantel:id,clave', 'plantilla:id,clave', 'plan:id,clave'])->get()->keyBy('clave');
        $this->profesores = Profesor::query()
            ->with(['planteles:id,clave', 'materias:id,clave', 'disponibilidades.franja.plantilla:id,clave'])
            ->get()
            ->keyBy('clave');
        $this->sincronias = $this->ciclo->sincronias()->get()->keyBy('clave');
        $this->cursos = $this->ciclo->cursos()
            ->with(['grupos:id,clave', 'materia:id,clave', 'profesor:id,clave', 'sincronia:id,clave'])
            ->get()
            ->keyBy('clave');

        foreach ($this->profesores as $profesor) {
            foreach ($profesor->disponibilidades as $disponibilidad) {
                $franja = $disponibilidad->franja;

                if ($franja !== null && $franja->plantilla !== null) {
                    $this->estados["{$profesor->clave}|{$franja->plantilla->clave}|{$franja->dia}|{$franja->orden}"] = $disponibilidad->estado->letra();
                }
            }
        }
    }

    /**
     * Las hojas del libro con su nombre de la plantilla; las demás se avisan e ignoran.
     *
     * @param  array<string, array{encabezados: list<string>, filas: list<Fila>}>  $libro
     * @return array<string, array{encabezados: list<string>, filas: list<Fila>}>
     */
    private function hojasConocidas(array $libro): array
    {
        $porLlave = [];

        foreach (array_keys(Hojas::COLUMNAS) as $nombre) {
            $porLlave[Normalizador::encabezado($nombre)] = $nombre;
        }

        $hojas = [];

        foreach ($libro as $titulo => $hoja) {
            $nombre = $porLlave[Normalizador::encabezado($titulo)] ?? null;

            if ($nombre !== null) {
                $hojas[$nombre] = $hoja;
            } elseif (Normalizador::encabezado($titulo) !== 'instrucciones') {
                $this->plan->aviso($titulo, null, "hoja «{$titulo}» ignorada");
            }
        }

        return $hojas;
    }

    /**
     * Avisa las columnas que no son de la hoja; sin alguna requerida, la hoja no se lee.
     *
     * @param  list<string>  $encabezados
     */
    private function columnas(string $hoja, array $encabezados): bool
    {
        $esperadas = array_map(Normalizador::encabezado(...), Hojas::COLUMNAS[$hoja]);
        $presentes = array_map(Normalizador::encabezado(...), $encabezados);

        foreach ($encabezados as $encabezado) {
            $llave = Normalizador::encabezado($encabezado);

            if (! in_array($llave, $esperadas, true) && ! ($hoja === 'Disponibilidad' && $this->columnaDeFranja($llave) !== null)) {
                $this->plan->aviso($hoja, 1, "columna «{$encabezado}» ignorada");
            }
        }

        $completa = true;

        foreach (self::REQUERIDAS[$hoja] as $columna) {
            if (! in_array(Normalizador::encabezado($columna), $presentes, true)) {
                $this->plan->error($hoja, 1, $columna, "falta la columna «{$columna}»");
                $completa = false;
            }
        }

        return $completa;
    }

    /**
     * «lun_07:00» → [0, '07:00']; null si no es una columna de franja.
     *
     * @return array{0: int, 1: string}|null
     */
    private function columnaDeFranja(string $llave): ?array
    {
        if (preg_match('/^([a-z]+)_(\d{1,2}:\d{2})$/', $llave, $partes) !== 1) {
            return null;
        }

        try {
            return [Normalizador::dia($partes[1]), (string) Normalizador::hora($partes[2])];
        } catch (ValorInvalido) {
            return null;
        }
    }

    /**
     * @param  list<Fila>  $filas
     */
    private function revisarPlanteles(array $filas): void
    {
        $vistas = [];

        foreach ($filas as $fila) {
            $antes = count($this->plan->errores());
            $clave = $this->celda('Planteles', $fila, 'clave', $this->texto(30), true);
            $nombre = $this->celda('Planteles', $fila, 'nombre', $this->texto(120), true);
            $direccion = $this->celda('Planteles', $fila, 'dirección', $this->texto(255));

            if ($clave === null || $this->repetida('Planteles', $fila, $clave, $vistas) || $this->conErrores($antes)) {
                continue;
            }

            $this->plantelesArchivo[$clave] = true;
            $actual = $this->planteles->get($clave);
            $direccion = $actual === null ? $direccion : $this->oGuardado('dirección', $direccion, $actual->direccion);
            $this->resultado(
                'Planteles', 'plantel', ['clave' => $clave, 'nombre' => $nombre, 'direccion' => $direccion],
                $actual === null ? null : ['nombre' => $actual->nombre, 'direccion' => $actual->direccion],
                ['nombre' => $nombre, 'direccion' => $direccion],
            );
        }
    }

    /**
     * @param  list<Fila>  $filas
     */
    private function revisarEspacios(array $filas): void
    {
        $vistas = [];

        foreach ($filas as $fila) {
            $antes = count($this->plan->errores());
            $clave = $this->celda('Espacios', $fila, 'clave', $this->texto(30), true);
            $plantel = $this->celda('Espacios', $fila, 'plantel', $this->referencia(fn (string $c) => $this->existePlantel($c), 'el plantel %s no existe'), true);
            $nombre = $this->celda('Espacios', $fila, 'nombre', $this->texto(120), true);
            $tipo = $this->celda('Espacios', $fila, 'tipo', $this->tipoEspacio(), true);
            $capacidad = $this->celda('Espacios', $fila, 'capacidad', $this->entero(1, 10000), true);

            if ($clave === null || $this->repetida('Espacios', $fila, $clave, $vistas) || $this->conErrores($antes)) {
                continue;
            }

            $actual = $this->espacios->get($clave);
            $nuevo = ['plantel' => $plantel, 'nombre' => $nombre, 'tipo' => $tipo, 'capacidad' => $capacidad];
            $this->resultado(
                'Espacios', 'espacio', ['clave' => $clave, ...$nuevo],
                $actual === null ? null : ['plantel' => $actual->plantel->clave, 'nombre' => $actual->nombre, 'tipo' => $actual->tipo->value, 'capacidad' => $actual->capacidad],
                $nuevo,
            );
        }
    }

    /**
     * Las franjas del archivo se suman a las de la base (mismo día y orden: la del archivo
     * manda) y el resultado no puede tener traslapes.
     *
     * @param  list<Fila>  $filas
     */
    private function revisarPlantillas(array $filas): void
    {
        /** @var array<string, array<string, array{fila: int, dia: int, orden: int, inicio: string, fin: string, tipo: string}>> $porPlantilla */
        $porPlantilla = [];

        foreach ($filas as $fila) {
            $antes = count($this->plan->errores());
            $plantilla = $this->celda('Plantillas', $fila, 'plantilla', $this->texto(30), true);
            $dia = $this->celda('Plantillas', $fila, 'día', fn (mixed $v) => Normalizador::texto($v) === null ? null : Normalizador::dia($v), true);
            $orden = $this->celda('Plantillas', $fila, 'orden', $this->entero(0, 200), true);
            $inicio = $this->celda('Plantillas', $fila, 'inicio', Normalizador::hora(...), true);
            $fin = $this->celda('Plantillas', $fila, 'fin', Normalizador::hora(...), true);
            $tipo = $this->celda('Plantillas', $fila, 'tipo', $this->tipoFranja());

            if (is_string($inicio) && is_string($fin) && $fin <= $inicio) {
                $this->fallo('Plantillas', $fila['fila'], 'fin', 'el fin debe ser posterior al inicio');
            }

            if ($this->conErrores($antes) || $plantilla === null || $dia === null || $orden === null || $inicio === null || $fin === null) {
                continue;
            }

            $llave = "{$dia}-{$orden}";

            if (isset($porPlantilla[$plantilla][$llave])) {
                $this->plan->error('Plantillas', $fila['fila'], 'orden', "la franja {$this->nombreFranja($dia, $orden)} de {$plantilla} está repetida (también en la fila {$porPlantilla[$plantilla][$llave]['fila']})");

                continue;
            }

            $porPlantilla[$plantilla][$llave] = ['fila' => $fila['fila'], 'dia' => $dia, 'orden' => $orden, 'inicio' => $inicio, 'fin' => $fin, 'tipo' => $tipo ?? TipoFranja::Clase->value];
        }

        foreach ($porPlantilla as $clave => $nuevas) {
            $existente = $this->plantillas->get($clave);
            $base = $existente === null ? [] : $this->franjasDeBase($existente);

            if ($existente === null) {
                $this->plan->aviso('Plantillas', array_values($nuevas)[0]['fila'], "se creará la plantilla {$clave}");
                $this->plan->operar('plantilla', ['clave' => $clave]);
            }

            $this->plantillasArchivo[$clave] = true;

            foreach ($nuevas as $llave => $franja) {
                $nuevas[$llave]['tipo'] = $this->oGuardado('tipo', $franja['tipo'], $base[$llave]['tipo'] ?? $franja['tipo']);
            }

            $finales = array_replace($base, $nuevas);

            foreach (ReglasFranjas::traslapes($finales) as $traslape) {
                $fila = $finales["{$traslape['dia']}-{$traslape['despues']}"]['fila'] ?? $finales["{$traslape['dia']}-{$traslape['antes']}"]['fila'] ?? null;

                if ($fila !== null) {
                    $this->plan->error('Plantillas', $fila, 'inicio', $traslape['mensaje']);
                }
            }

            foreach ($nuevas as $llave => $franja) {
                $actual = $base[$llave] ?? null;
                $this->resultado(
                    'Plantillas', 'franja', ['plantilla' => $clave, 'dia' => $franja['dia'], 'orden' => $franja['orden'], 'inicio' => $franja['inicio'], 'fin' => $franja['fin'], 'tipo' => $franja['tipo']],
                    $actual === null ? null : [$actual['inicio'], $actual['fin'], $actual['tipo']],
                    [$franja['inicio'], $franja['fin'], $franja['tipo']],
                );
            }

            $this->franjasDeClase[$clave] = $this->deClase($finales);
        }

        foreach ($this->plantillas as $clave => $plantilla) {
            $this->franjasDeClase[$clave] ??= $this->deClase($this->franjasDeBase($plantilla));
        }
    }

    /**
     * @return array<string, array{fila: int|null, dia: int, orden: int, inicio: string, fin: string, tipo: string}>
     */
    private function franjasDeBase(PlantillaHoraria $plantilla): array
    {
        $franjas = [];

        foreach ($plantilla->franjas as $franja) {
            $franjas["{$franja->dia}-{$franja->orden}"] = [
                'fila' => null, 'dia' => $franja->dia, 'orden' => $franja->orden,
                'inicio' => $franja->inicio, 'fin' => $franja->fin, 'tipo' => $franja->tipo->value,
            ];
        }

        return $franjas;
    }

    /**
     * @param  array<string, array{dia: int, orden: int, inicio: string, tipo: string}>  $franjas
     * @return array<string, int>
     */
    private function deClase(array $franjas): array
    {
        $deClase = [];

        foreach ($franjas as $franja) {
            if ($franja['tipo'] === TipoFranja::Clase->value) {
                $deClase["{$franja['dia']} {$franja['inicio']}"] = $franja['orden'];
            }
        }

        return $deClase;
    }

    /**
     * @param  list<Fila>  $filas
     */
    private function revisarMaterias(array $filas): void
    {
        $vistas = [];
        /** @var array<string, string> $nombres clave => nombre, en orden del archivo */
        $nombres = [];

        foreach ($filas as $fila) {
            $antes = count($this->plan->errores());
            $clave = $this->celda('Materias', $fila, 'clave', $this->texto(30), true);
            $plan = $this->celda('Materias', $fila, 'plan', $this->texto(30), true);
            $nombre = $this->celda('Materias', $fila, 'nombre', $this->texto(120), true);
            $semestre = $this->celda('Materias', $fila, 'semestre', $this->entero(1, 12), true);
            $horas = $this->celda('Materias', $fila, 'horas_semana', $this->entero(1, 40), true);
            $patrones = $this->celda('Materias', $fila, 'patrones', function (mixed $v) use ($horas): ?array {
                $texto = Normalizador::texto($v);

                return $texto === null || $horas === null ? null : Patrones::parsear($texto, $horas);
            });
            $tipo = $this->celda('Materias', $fila, 'tipo_espacio', $this->tipoEspacio()) ?? TipoEspacio::Aula->value;

            if ($clave === null || $this->repetida('Materias', $fila, $clave, $vistas) || $this->conErrores($antes) || $plan === null) {
                continue;
            }

            if (! $this->planes->has($plan) && ! isset($this->planesNuevos[$plan])) {
                $this->planesNuevos[$plan] = true;
                $this->plan->aviso('Materias', $fila['fila'], "se creará el plan {$plan}");
                $this->plan->operar('plan', ['clave' => $plan]);
            }

            $this->materiasArchivo[$clave] = true;
            $nombres[$clave] = (string) $nombre;
            $actual = $this->materias->get($clave);

            if ($actual !== null) {
                $tipo = $this->oGuardado('tipo_espacio', $tipo, $actual->tipo_espacio->value);
                $patrones = $this->oGuardado('patrones', $patrones, $this->patronesQueCuadran($fila, $clave, $actual->patrones, (int) $horas));
            }

            $nuevo = ['plan' => $plan, 'nombre' => $nombre, 'semestre' => $semestre, 'horas_semana' => $horas, 'patrones' => $patrones, 'tipo_espacio' => $tipo];
            $this->resultado(
                'Materias', 'materia', ['clave' => $clave, ...$nuevo],
                $actual === null ? null : [
                    'plan' => $actual->plan->clave, 'nombre' => $actual->nombre, 'semestre' => $actual->semestre,
                    'horas_semana' => $actual->horas_semana, 'patrones' => $actual->patrones, 'tipo_espacio' => $actual->tipo_espacio->value,
                ],
                $nuevo,
            );
        }

        $this->avisarMateriasParecidas($nombres);
    }

    /**
     * Los patrones guardados, si todavía suman las horas de la materia; si no, los de omisión.
     *
     * @param  Fila  $fila
     * @param  list<list<int>>|null  $patrones
     * @return list<list<int>>|null
     */
    private function patronesQueCuadran(array $fila, string $clave, ?array $patrones, int $horas): ?array
    {
        if ($patrones === null || collect($patrones)->every(fn (array $patron) => array_sum($patron) === $horas)) {
            return $patrones;
        }

        $this->plan->aviso('Materias', $fila['fila'], "los patrones guardados de {$clave} no suman {$horas} horas; se usarán los de omisión");

        return null;
    }

    /**
     * Dos materias cuyo nombre solo difiere en mayúsculas o acentos suelen ser la misma.
     *
     * @param  array<string, string>  $delArchivo
     */
    private function avisarMateriasParecidas(array $delArchivo): void
    {
        $nombres = $delArchivo;

        foreach ($this->materias as $clave => $materia) {
            $nombres[$clave] ??= $materia->nombre;
        }

        $grupos = [];

        foreach ($nombres as $clave => $nombre) {
            $grupos[Normalizador::comparable($nombre)][$clave] = $nombre;
        }

        foreach ($grupos as $parecidas) {
            if (count($parecidas) < 2 || count(array_unique($parecidas)) < 2) {
                continue;
            }

            $partes = [];

            foreach ($parecidas as $clave => $nombre) {
                $partes[] = "{$clave} («{$nombre}»)";
            }

            $ultima = array_pop($partes);
            $this->plan->aviso('Materias', null, 'las materias '.implode(', ', $partes)." y {$ultima} solo difieren en mayúsculas o acentos");
        }
    }

    /**
     * @param  list<Fila>  $filas
     */
    private function revisarGrupos(array $filas): void
    {
        $vistas = [];

        foreach ($filas as $fila) {
            $antes = count($this->plan->errores());
            $clave = $this->celda('Grupos', $fila, 'clave', $this->texto(30), true);
            $plantel = $this->celda('Grupos', $fila, 'plantel', $this->referencia(fn (string $c) => $this->existePlantel($c), 'el plantel %s no existe'), true);
            $plantilla = $this->celda('Grupos', $fila, 'plantilla', $this->referencia(fn (string $c) => $this->existePlantilla($c), 'la plantilla %s no existe'), true);
            $plan = $this->celda('Grupos', $fila, 'plan', $this->referencia(fn (string $c) => $this->planes->has($c) || isset($this->planesNuevos[$c]), 'el plan %s no existe'), true);
            $semestre = $this->celda('Grupos', $fila, 'semestre', $this->entero(1, 12), true);
            $alumnos = $this->celda('Grupos', $fila, 'alumnos', $this->entero(1, 10000), true);

            if ($clave === null || $this->repetida('Grupos', $fila, $clave, $vistas) || $this->conErrores($antes) || $plantel === null || $plantilla === null) {
                continue;
            }

            $this->gruposArchivo[$clave] = ['plantel' => $plantel, 'plantilla' => $plantilla];
            $this->filaDeGrupo[$clave] = $fila['fila'];
            $actual = $this->grupos->get($clave);
            $nuevo = ['plantel' => $plantel, 'plantilla' => $plantilla, 'plan' => $plan, 'semestre' => $semestre, 'alumnos' => $alumnos];
            $this->resultado(
                'Grupos', 'grupo', ['clave' => $clave, ...$nuevo],
                $actual === null ? null : [
                    'plantel' => $actual->plantel->clave, 'plantilla' => $actual->plantilla->clave, 'plan' => $actual->plan->clave,
                    'semestre' => $actual->semestre, 'alumnos' => $actual->alumnos,
                ],
                $nuevo,
            );
        }
    }

    /**
     * Los planteles y las materias del archivo reemplazan los del profesor.
     *
     * @param  list<Fila>  $filas
     */
    private function revisarProfesores(array $filas): void
    {
        $vistas = [];

        foreach ($filas as $fila) {
            $antes = count($this->plan->errores());
            $clave = $this->celda('Profesores', $fila, 'clave', $this->texto(30), true);
            $nombre = $this->celda('Profesores', $fila, 'nombre', $this->texto(120), true);
            $contrato = $this->celda('Profesores', $fila, 'contrato', $this->contrato()) ?? Contrato::Asignatura->value;
            $horasMin = $this->celda('Profesores', $fila, 'horas_min', $this->entero(0, 168)) ?? 0;
            $horasMax = $this->celda('Profesores', $fila, 'horas_max', $this->entero(0, 168), true);
            $maxDia = $this->celda('Profesores', $fila, 'max_dia', $this->entero(1, 24), true);
            $sinHuecos = $this->celda('Profesores', $fila, 'sin_huecos', Normalizador::siNo(...)) ?? false;
            $planteles = $this->celda('Profesores', $fila, 'planteles', $this->claves()) ?? [];
            $materias = $this->celda('Profesores', $fila, 'materias', $this->claves()) ?? [];

            foreach ($planteles as $plantel) {
                if (! $this->existePlantel($plantel)) {
                    $this->fallo('Profesores', $fila['fila'], 'planteles', "el plantel {$plantel} no existe");
                }
            }

            foreach ($materias as $materia) {
                if (! $this->existeMateria($materia)) {
                    $this->fallo('Profesores', $fila['fila'], 'materias', "la materia {$materia} no existe");
                }
            }

            $actual = $clave === null ? null : $this->profesores->get($clave);

            if ($actual !== null) {
                $contrato = $this->oGuardado('contrato', $contrato, $actual->contrato->value);
                $horasMin = $this->oGuardado('horas_min', $horasMin, $actual->horas_min);
                $sinHuecos = $this->oGuardado('sin_huecos', $sinHuecos, $actual->sin_huecos);
                $planteles = $this->oGuardado('planteles', $planteles, $actual->planteles->pluck('clave')->all());
                $materias = $this->oGuardado('materias', $materias, $actual->materias->pluck('clave')->all());
            }

            if ($horasMax !== null && $horasMin > $horasMax) {
                $this->fallo('Profesores', $fila['fila'], 'horas_max', 'debe ser mayor o igual que horas_min');
            }

            if ($clave === null || $this->repetida('Profesores', $fila, $clave, $vistas) || $this->conErrores($antes)) {
                continue;
            }

            $this->profesoresArchivo[$clave] = true;
            $nuevo = [
                'nombre' => $nombre, 'contrato' => $contrato, 'horas_min' => $horasMin, 'horas_max' => $horasMax,
                'max_dia' => $maxDia, 'sin_huecos' => $sinHuecos, 'planteles' => $this->ordenadas($planteles), 'materias' => $this->ordenadas($materias),
            ];
            $this->resultado(
                'Profesores', 'profesor', ['clave' => $clave, ...$nuevo],
                $actual === null ? null : [
                    'nombre' => $actual->nombre, 'contrato' => $actual->contrato->value, 'horas_min' => $actual->horas_min,
                    'horas_max' => $actual->horas_max, 'max_dia' => $actual->max_dia, 'sin_huecos' => $actual->sin_huecos,
                    'planteles' => $this->ordenadas($actual->planteles->pluck('clave')->all()),
                    'materias' => $this->ordenadas($actual->materias->pluck('clave')->all()),
                ],
                $nuevo,
            );
        }
    }

    /**
     * Cada fila manda sobre las franjas de su plantilla que trae como columna.
     *
     * @param  list<string>  $encabezados
     * @param  list<Fila>  $filas
     */
    private function revisarDisponibilidad(array $encabezados, array $filas): void
    {
        $columnas = [];

        foreach ($encabezados as $encabezado) {
            $llave = Normalizador::encabezado($encabezado);
            $franja = $this->columnaDeFranja($llave);

            if ($franja !== null) {
                $columnas[$llave] = ['texto' => $encabezado, 'dia' => $franja[0], 'inicio' => $franja[1]];
            }
        }

        $vistas = [];

        foreach ($filas as $fila) {
            $antes = count($this->plan->errores());
            $profesor = $this->celda('Disponibilidad', $fila, 'clave_profesor', $this->referencia(fn (string $c) => $this->profesores->has($c) || isset($this->profesoresArchivo[$c]), 'el profesor %s no existe'), true);
            $plantilla = $this->celda('Disponibilidad', $fila, 'plantilla', $this->referencia(fn (string $c) => $this->existePlantilla($c), 'la plantilla %s no existe'), true);

            if ($profesor === null || $plantilla === null || $this->repetida('Disponibilidad', $fila, "{$profesor} {$plantilla}", $vistas, 'clave_profesor')) {
                continue;
            }

            $celdas = [];
            $cambios = 0;
            $habia = false;

            foreach ($columnas as $llave => $columna) {
                try {
                    $letra = Normalizador::disponibilidad($fila['celdas'][$llave] ?? null);
                } catch (ValorInvalido $e) {
                    $this->fallo('Disponibilidad', $fila['fila'], $columna['texto'], $e->getMessage());

                    continue;
                }

                $orden = $this->franjasDeClase[$plantilla]["{$columna['dia']} {$columna['inicio']}"] ?? null;

                if ($orden === null) {
                    if ($letra !== 'D') {
                        $this->fallo('Disponibilidad', $fila['fila'], $columna['texto'], "la plantilla {$plantilla} no tiene franja {$columna['texto']}");
                    }

                    continue;
                }

                $actual = $this->estados["{$profesor}|{$plantilla}|{$columna['dia']}|{$orden}"] ?? 'D';
                $habia = $habia || $actual !== 'D';
                $cambios += $actual === $letra ? 0 : 1;
                $celdas[] = ['dia' => $columna['dia'], 'orden' => $orden, 'letra' => $letra];
            }

            if ($this->conErrores($antes)) {
                continue;
            }

            if ($cambios === 0) {
                $this->plan->contar('Disponibilidad', 'sin_cambios');

                continue;
            }

            $this->plan->contar('Disponibilidad', $habia ? 'actualizar' : 'crear');
            $this->plan->operar('disponibilidad', ['profesor' => $profesor, 'plantilla' => $plantilla, 'celdas' => $celdas]);
        }
    }

    /**
     * Sin clave, el curso se busca por materia y grupos o se le genera. Las claves escritas en
     * la hoja se apartan antes, para que una fila sin clave no tome la de otra fila.
     *
     * @param  list<Fila>  $filas
     */
    private function revisarCarga(array $filas): void
    {
        $vistas = [];
        $tomadas = [];

        foreach ($filas as $fila) {
            try {
                $escrita = Normalizador::texto($fila['celdas']['clave'] ?? null);
            } catch (ValorInvalido) {
                $escrita = null;
            }

            if ($escrita !== null) {
                $tomadas[] = $escrita;
            }
        }

        foreach ($filas as $fila) {
            $antes = count($this->plan->errores());
            $clave = $this->celda('Carga', $fila, 'clave', $this->texto(120));
            $grupos = $this->celda('Carga', $fila, 'grupos', $this->claves(), true) ?? [];
            $materia = $this->celda('Carga', $fila, 'materia', $this->referencia(fn (string $c) => $this->existeMateria($c), 'la materia %s no existe'), true);
            $profesor = $this->celda('Carga', $fila, 'profesor', $this->referencia(fn (string $c) => $this->profesores->has($c) || isset($this->profesoresArchivo[$c]), 'el profesor %s no existe'));
            $vacante = $this->celda('Carga', $fila, 'vacante', Normalizador::siNo(...)) ?? false;
            $sincronia = $this->celda('Carga', $fila, 'sincronía', $this->texto(30));
            $alumnos = $this->celda('Carga', $fila, 'alumnos', $this->entero(1, 10000));

            $this->revisarGruposDeCurso($fila, $grupos);

            if ($vacante && $profesor !== null) {
                $this->fallo('Carga', $fila['fila'], 'profesor', 'Un curso vacante no lleva profesor.');
            }

            if ($this->conErrores($antes) || $materia === null || $grupos === []) {
                continue;
            }

            $clave ??= $this->claveExistente($materia, $grupos, $tomadas) ?? ClaveCurso::generar(
                array_map(fn (string $c) => $this->grupos->get($c) ?? new Grupo(['clave' => $c]), $grupos),
                $this->materias->get($materia) ?? new Materia(['clave' => $materia]),
                $this->ciclo->id,
                $tomadas,
            );

            if ($this->repetida('Carga', $fila, $clave, $vistas)) {
                continue;
            }

            $tomadas[] = $clave;
            $this->cursosArchivo[$clave] = true;
            $actual = $this->cursos->get($clave);

            if ($actual !== null) {
                $profesor = $this->oGuardado('profesor', $profesor, $actual->profesor?->clave);
                // Un curso vacante deja de serlo si el archivo le pone profesor.
                $vacante = $this->oGuardado('vacante', $vacante, $actual->vacante && $profesor === null);
                $sincronia = $this->oGuardado('sincronía', $sincronia, $actual->sincronia?->clave);
                $alumnos = $this->oGuardado('alumnos', $alumnos, $actual->alumnos);
            }

            if ($sincronia !== null && ! $this->sincronias->has($sincronia) && ! isset($this->sincroniasNuevas[$sincronia])) {
                $this->sincroniasNuevas[$sincronia] = true;
                $this->plan->aviso('Carga', $fila['fila'], "se creará la sincronía {$sincronia}");
                $this->plan->operar('sincronia', ['clave' => $sincronia]);
            }

            $nuevo = [
                'grupos' => $this->ordenadas($grupos), 'materia' => $materia, 'profesor' => $vacante ? null : $profesor,
                'vacante' => $vacante, 'sincronia' => $sincronia, 'alumnos' => $alumnos,
            ];
            $this->resultado(
                'Carga', 'curso', ['clave' => $clave, ...$nuevo],
                $actual === null ? null : [
                    'grupos' => $this->ordenadas($actual->grupos->pluck('clave')->all()), 'materia' => $actual->materia->clave,
                    'profesor' => $actual->profesor?->clave, 'vacante' => $actual->vacante, 'sincronia' => $actual->sincronia?->clave,
                    'alumnos' => $actual->alumnos,
                ],
                $nuevo,
            );
        }
    }

    /**
     * Los grupos de un curso existen en el ciclo, no se repiten y comparten plantel y plantilla.
     *
     * @param  Fila  $fila
     * @param  list<string>  $grupos
     */
    private function revisarGruposDeCurso(array $fila, array $grupos): void
    {
        if (count($grupos) !== count(array_unique($grupos))) {
            $this->fallo('Carga', $fila['fila'], 'grupos', 'Un grupo no puede repetirse en el mismo curso.');

            return;
        }

        $lugares = [];

        foreach ($grupos as $grupo) {
            $lugar = $this->lugarDeGrupo($grupo);

            if ($lugar === null) {
                $this->fallo('Carga', $fila['fila'], 'grupos', "el grupo {$grupo} no existe");

                continue;
            }

            $lugares["{$lugar['plantel']}|{$lugar['plantilla']}"] = true;
        }

        if (count($lugares) > 1) {
            $this->fallo('Carga', $fila['fila'], 'grupos', 'Los grupos de un curso deben ser del mismo plantel y la misma plantilla.');
        }
    }

    /**
     * Un grupo que el archivo cambia de plantel o de plantilla no puede dejar en otro lugar a
     * los grupos con los que comparte un curso que la hoja Carga no trae (esos ya se revisaron).
     */
    private function revisarGruposMovidos(): void
    {
        foreach ($this->gruposArchivo as $clave => $lugar) {
            $grupo = $this->grupos->get($clave);

            if ($grupo === null || ['plantel' => $grupo->plantel->clave, 'plantilla' => $grupo->plantilla->clave] === $lugar) {
                continue;
            }

            $partidos = $this->cursos
                ->filter(fn (Curso $curso) => ! isset($this->cursosArchivo[$curso->clave])
                    && $curso->grupos->contains('clave', $clave)
                    && $curso->grupos->contains(fn (Grupo $otro) => $this->lugarDeGrupo($otro->clave) !== $lugar))
                ->keys()
                ->all();

            if ($partidos !== []) {
                $columna = $grupo->plantel->clave !== $lugar['plantel'] ? 'plantel' : 'plantilla';
                $this->plan->error('Grupos', $this->filaDeGrupo[$clave], $columna, CursosCompartidos::mensaje($clave, $this->ordenadas(array_map(strval(...), $partidos))));
            }
        }
    }

    /**
     * @return array{plantel: string, plantilla: string}|null
     */
    private function lugarDeGrupo(string $clave): ?array
    {
        if (isset($this->gruposArchivo[$clave])) {
            return $this->gruposArchivo[$clave];
        }

        $grupo = $this->grupos->get($clave);

        return $grupo === null ? null : ['plantel' => $grupo->plantel->clave, 'plantilla' => $grupo->plantilla->clave];
    }

    /**
     * La clave del primer curso del ciclo (en orden de clave) con esa materia y esos grupos que
     * ninguna otra fila ya tomó: dos filas iguales sin clave (Inglés por nivel) son dos cursos.
     *
     * @param  list<string>  $grupos
     * @param  list<string>  $tomadas
     */
    private function claveExistente(string $materia, array $grupos, array $tomadas): ?string
    {
        $buscados = $this->ordenadas($grupos);
        $iguales = $this->cursos->filter(fn (Curso $curso) => $curso->materia->clave === $materia
            && $this->ordenadas($curso->grupos->pluck('clave')->all()) === $buscados
            && ! in_array($curso->clave, $tomadas, true));

        return $this->ordenadas(array_map(strval(...), $iguales->keys()->all()))[0] ?? null;
    }

    /**
     * Lo leído, o lo guardado si la hoja no trae la columna: importar nunca borra lo que el
     * archivo no trae.
     *
     * @template T
     *
     * @param  T  $leido
     * @param  T  $guardado
     * @return T
     */
    private function oGuardado(string $columna, mixed $leido, mixed $guardado): mixed
    {
        return in_array(Normalizador::encabezado($columna), $this->presentes, true) ? $leido : $guardado;
    }

    /**
     * Cuenta el registro y, si cambia algo, agrega la operación.
     *
     * @param  array<string, mixed>  $datos
     * @param  array<int|string, mixed>|null  $actual
     * @param  array<int|string, mixed>  $nuevo
     */
    private function resultado(string $hoja, string $tipo, array $datos, ?array $actual, array $nuevo): void
    {
        if ($actual === $nuevo) {
            $this->plan->contar($hoja, 'sin_cambios');

            return;
        }

        $this->plan->contar($hoja, $actual === null ? 'crear' : 'actualizar');
        $this->plan->operar($tipo, $datos);
    }

    /**
     * @param  Fila  $fila
     * @param  array<string, int>  $vistas  clave => fila donde apareció
     */
    private function repetida(string $hoja, array $fila, string $clave, array &$vistas, string $columna = 'clave'): bool
    {
        if (isset($vistas[$clave])) {
            $this->plan->error($hoja, $fila['fila'], $columna, "«{$clave}» está repetida (también en la fila {$vistas[$clave]})");

            return true;
        }

        $vistas[$clave] = $fila['fila'];

        return false;
    }

    /**
     * Lee una celda; si no se puede, anota el error de la fila.
     *
     * @template T
     *
     * @param  Fila  $fila
     * @param  callable(mixed): T  $leer
     * @return T|null
     */
    private function celda(string $hoja, array $fila, string $columna, callable $leer, bool $requerida = false): mixed
    {
        try {
            $valor = $leer($fila['celdas'][Normalizador::encabezado($columna)] ?? null);
        } catch (InvalidArgumentException $e) {
            $this->fallo($hoja, $fila['fila'], $columna, $e->getMessage());

            return null;
        }

        if ($requerida && ($valor === null || $valor === [])) {
            $this->fallo($hoja, $fila['fila'], $columna, "falta «{$columna}»");

            return null;
        }

        return $valor;
    }

    private function fallo(string $hoja, int $fila, string $columna, string $mensaje): void
    {
        $this->plan->error($hoja, $fila, $columna, $mensaje);
    }

    /**
     * Si la fila que empezó con `$antes` errores ya agregó alguno.
     */
    private function conErrores(int $antes): bool
    {
        return count($this->plan->errores()) > $antes;
    }

    /**
     * @return Closure(mixed): ?string
     */
    private function texto(int $maximo): Closure
    {
        return function (mixed $valor) use ($maximo): ?string {
            $texto = Normalizador::texto($valor);

            if ($texto !== null && mb_strlen($texto) > $maximo) {
                throw new ValorInvalido("«{$texto}» es demasiado largo (máximo {$maximo} caracteres)");
            }

            return $texto;
        };
    }

    /**
     * @return Closure(mixed): ?int
     */
    private function entero(int $minimo, int $maximo): Closure
    {
        return function (mixed $valor) use ($minimo, $maximo): ?int {
            $numero = Normalizador::entero($valor);

            if ($numero !== null && ($numero < $minimo || $numero > $maximo)) {
                throw new ValorInvalido("{$numero} debe ir de {$minimo} a {$maximo}");
            }

            return $numero;
        };
    }

    /**
     * Una clave que debe existir, con el mensaje para cuando no.
     *
     * @param  callable(string): bool  $existe
     * @return Closure(mixed): ?string
     */
    private function referencia(callable $existe, string $mensaje): Closure
    {
        return function (mixed $valor) use ($existe, $mensaje): ?string {
            $clave = Normalizador::texto($valor);

            if ($clave !== null && ! $existe($clave)) {
                throw new ValorInvalido(sprintf($mensaje, $clave));
            }

            return $clave;
        };
    }

    /**
     * «CEN, NTE» o «CEN; NTE» → ['CEN', 'NTE'].
     *
     * @return Closure(mixed): list<string>
     */
    private function claves(): Closure
    {
        return fn (mixed $valor): array => array_values(array_filter(
            array_map(Normalizador::texto(...), preg_split('/[,;]/', Normalizador::texto($valor) ?? '') ?: []),
            fn (?string $clave) => $clave !== null,
        ));
    }

    /**
     * @return Closure(mixed): ?string
     */
    private function tipoEspacio(): Closure
    {
        return $this->opcion(fn (string $valor) => TipoEspacio::tryFrom($valor)?->value, 'no es un tipo de espacio');
    }

    /**
     * @return Closure(mixed): ?string
     */
    private function tipoFranja(): Closure
    {
        return $this->opcion(fn (string $valor) => TipoFranja::tryFrom($valor)?->value, 'no es clase ni receso');
    }

    /**
     * @return Closure(mixed): ?string
     */
    private function contrato(): Closure
    {
        return $this->opcion(fn (string $valor) => Contrato::tryFrom($valor)?->value, 'no es un tipo de contrato');
    }

    /**
     * Un valor de una lista, sin importar mayúsculas, acentos ni espacios («Cómputo» → computo).
     *
     * @param  callable(string): ?string  $buscar
     * @return Closure(mixed): ?string
     */
    private function opcion(callable $buscar, string $mensaje): Closure
    {
        return function (mixed $valor) use ($buscar, $mensaje): ?string {
            $texto = Normalizador::texto($valor);

            if ($texto === null) {
                return null;
            }

            return $buscar(str_replace(' ', '_', Normalizador::comparable($texto))) ?? throw new ValorInvalido("«{$texto}» {$mensaje}");
        };
    }

    private function existePlantel(string $clave): bool
    {
        return isset($this->plantelesArchivo[$clave]) || $this->planteles->has($clave);
    }

    private function existePlantilla(string $clave): bool
    {
        return isset($this->plantillasArchivo[$clave]) || $this->plantillas->has($clave);
    }

    private function existeMateria(string $clave): bool
    {
        return isset($this->materiasArchivo[$clave]) || $this->materias->has($clave);
    }

    /**
     * @param  array<int, string>  $claves
     * @return list<string>
     */
    private function ordenadas(array $claves): array
    {
        $claves = array_values(array_unique($claves));
        natsort($claves);

        return array_values($claves);
    }

    private function nombreFranja(int $dia, int $orden): string
    {
        return Hojas::DIAS[$dia]." {$orden}";
    }
}
