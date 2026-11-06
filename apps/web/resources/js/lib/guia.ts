import { index as ciclos } from '@/routes/ciclos';
import { create as generarHorario } from '@/routes/corridas';
import { index as cursos } from '@/routes/cursos';
import { index as espacios } from '@/routes/espacios';
import { index as grupos } from '@/routes/grupos';
import { index as planes } from '@/routes/planes';
import { index as planteles } from '@/routes/planteles';
import { index as plantillas } from '@/routes/plantillas';
import { index as profesores } from '@/routes/profesores';
import type { ClavePaso, Guia } from '@/types/guia';
import type { RouteDefinition } from '@/wayfinder';

export type Paso = {
    clave: ClavePaso;
    titulo: string;
    queHacer: string;
    paraQue: string;
    /** El texto del botón «Ir a …». */
    destino: string;
    ir: () => RouteDefinition<'get'>;
};

/** Los pasos de la guía, en el mismo orden que PrimerosPasos::PASOS del servidor. */
export const PASOS: readonly Paso[] = [
    {
        clave: 'ciclo',
        titulo: 'Crea tu ciclo escolar',
        queHacer:
            'Crea el periodo que vas a programar, por ejemplo 2027-A. El primero queda como actual.',
        paraQue: 'Los grupos, la carga y los horarios son de un ciclo.',
        destino: 'Ciclos',
        ir: ciclos,
    },
    {
        clave: 'planteles',
        titulo: 'Agrega tus planteles',
        queHacer:
            'Agrega cada sede de tu escuela; si solo tienes una, crea esa.',
        paraQue: 'Los espacios y los grupos pertenecen a un plantel.',
        destino: 'Planteles',
        ir: planteles,
    },
    {
        clave: 'espacios',
        titulo: 'Registra tus espacios',
        queHacer:
            'Aulas, laboratorios y canchas de cada plantel, con su capacidad.',
        paraQue: 'El horario asigna un espacio a cada clase.',
        destino: 'Espacios',
        ir: espacios,
    },
    {
        clave: 'plantilla',
        titulo: 'Arma tu plantilla horaria',
        queHacer:
            'Crea una plantilla y genera sus franjas: días, horas de clase y recesos.',
        paraQue: 'Las clases solo se acomodan en esas franjas.',
        destino: 'Plantillas',
        ir: plantillas,
    },
    {
        clave: 'materias',
        titulo: 'Captura tu plan y sus materias',
        queHacer:
            'Crea tu plan de estudios y después sus materias, con sus horas por semana.',
        paraQue: 'La carga académica se arma con estas materias.',
        destino: 'Planes',
        ir: planes,
    },
    {
        clave: 'grupos',
        titulo: 'Crea tus grupos',
        queHacer:
            'Crea los grupos del ciclo con su plantel, plantilla, semestre y alumnos.',
        paraQue: 'Cada grupo recibe su propio horario.',
        destino: 'Grupos',
        ir: grupos,
    },
    {
        clave: 'profesores',
        titulo: 'Agrega a tus profesores',
        queHacer:
            'Agrega a tus profesores con las materias que pueden dar. Su disponibilidad es opcional.',
        paraQue: 'El motor solo les asigna lo que pueden dar y cuando pueden.',
        destino: 'Profesores',
        ir: profesores,
    },
    {
        clave: 'carga',
        titulo: 'Asigna la carga académica',
        queHacer: 'Indica qué materia lleva cada grupo y quién la da.',
        paraQue: 'Es lo que el motor acomoda en el horario.',
        destino: 'Carga académica',
        ir: cursos,
    },
    {
        clave: 'generar',
        titulo: 'Genera tu horario',
        queHacer: 'Revisa las reglas y genera; tarda unos minutos.',
        paraQue: 'El motor acomoda todo sin choques y te muestra el resultado.',
        destino: 'Generar horario',
        ir: generarHorario,
    },
];

/** El primer paso pendiente, aunque haya pasos hechos después; null si ya están todos. */
export function pasoActual(hechos: Record<ClavePaso, boolean>): Paso | null {
    return PASOS.find((paso) => !hechos[paso.clave]) ?? null;
}

export function avance(hechos: Record<ClavePaso, boolean>): {
    hechos: number;
    total: number;
} {
    return {
        hechos: PASOS.filter((paso) => hechos[paso.clave]).length,
        total: PASOS.length,
    };
}

/** Se abre sola mientras falten pasos y el admin no la haya cerrado. */
export function abreSola(guia: Guia): boolean {
    return !guia.cerrada && pasoActual(guia.hechos) !== null;
}

/** Con los 9 pasos hechos cuenta como cerrada: así no se abre sola al empezar otro ciclo. */
export function cierraSola(guia: Guia): boolean {
    return !guia.cerrada && pasoActual(guia.hechos) === null;
}
