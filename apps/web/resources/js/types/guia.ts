export type ClavePaso =
    | 'ciclo'
    | 'planteles'
    | 'espacios'
    | 'plantilla'
    | 'materias'
    | 'grupos'
    | 'profesores'
    | 'carga'
    | 'generar';

/** La prop compartida `guia`: solo la recibe el admin. */
export type Guia = {
    hechos: Record<ClavePaso, boolean>;
    cerrada: boolean;
};
