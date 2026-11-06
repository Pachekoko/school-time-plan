<?php

namespace App\Excel;

use App\Domain\Dias;

/**
 * El formato de la plantilla de Excel: hojas, encabezados exactos (spec §7 más las columnas
 * de la desviación 7) e instrucciones. Lo comparten la exportación y la importación.
 */
final class Hojas
{
    public const array DIAS = Dias::CORTOS;

    /** En Disponibilidad, después de estas dos va una columna «Lun 07:00» por franja de clase. */
    public const array COLUMNAS = [
        'Planteles' => ['clave', 'nombre', 'dirección'],
        'Espacios' => ['clave', 'plantel', 'nombre', 'tipo', 'capacidad'],
        'Plantillas' => ['plantilla', 'día', 'orden', 'inicio', 'fin', 'tipo'],
        'Materias' => ['clave', 'plan', 'nombre', 'semestre', 'horas_semana', 'patrones', 'tipo_espacio'],
        'Grupos' => ['clave', 'plantel', 'plantilla', 'plan', 'semestre', 'alumnos'],
        'Profesores' => ['clave', 'nombre', 'contrato', 'horas_min', 'horas_max', 'max_dia', 'sin_huecos', 'planteles', 'materias'],
        'Disponibilidad' => ['clave_profesor', 'plantilla'],
        'Carga' => ['clave', 'grupos', 'materia', 'profesor', 'vacante', 'sincronía', 'alumnos'],
    ];

    public const array INSTRUCCIONES = [
        'Planteles' => 'Una fila por plantel. La clave es corta y única: las demás hojas se refieren a ella.',
        'Espacios' => 'Aulas, laboratorios y demás. «plantel» es la clave del plantel; «tipo» se elige de la lista; la capacidad es en alumnos.',
        'Plantillas' => 'Una fila por franja. «plantilla» es la clave del turno; el día va de Lun a Dom; el orden empieza en 0; inicio y fin como 07:00; tipo clase o receso.',
        'Materias' => '«plan» es la clave del plan de estudios (si no existe, se crea). «patrones» es opcional: 2+1 es un día de 2 h y otro de 1 h; las alternativas van con |. Vacío: se deriva de las horas.',
        'Grupos' => 'Los grupos del ciclo actual. «plantel», «plantilla» y «plan» son claves.',
        'Profesores' => '«planteles» y «materias» son claves separadas por coma. sin_huecos: S si sus clases de cada día van seguidas.',
        'Disponibilidad' => 'Una fila por profesor y plantilla. En cada franja: vacío o D = disponible, P = preferente, N = no disponible. Las celdas grises no existen en esa plantilla.',
        'Carga' => 'Los cursos del ciclo actual. «grupos» son claves separadas por coma (los grupos toman la clase juntos). profesor es opcional: vacío, lo elige el motor. vacante S = sin profesor contratado. La clave se puede dejar vacía.',
    ];

    /** Hasta qué fila llegan las listas desplegables. */
    public const int FILAS_CON_LISTA = 1000;
}
