// D: disponible (sin fila en la base); P: preferente; N: no disponible.
export type EstadoCelda = 'D' | 'P' | 'N';

export type FranjaMatriz = {
    id: number;
    dia: number;
    orden: number;
    inicio: string;
    fin: string;
    tipo: 'clase' | 'receso';
};

export type FilaMatriz = {
    inicio: string;
    fin: string;
    tipo: 'clase' | 'receso';
    // Una por día de `dias`; null si ese día no tiene franja en ese horario.
    celdas: (FranjaMatriz | null)[];
};

/** D → P → N → D. */
export function siguienteEstado(estado: EstadoCelda): EstadoCelda {
    if (estado === 'D') {
        return 'P';
    }

    return estado === 'P' ? 'N' : 'D';
}

/**
 * Filas por horario distinto (inicio, fin y tipo), ordenadas por inicio; columnas: los
 * días que tienen franjas.
 */
export function armarMatriz(franjas: FranjaMatriz[]): {
    dias: number[];
    filas: FilaMatriz[];
} {
    const dias = [...new Set(franjas.map((f) => f.dia))].sort((a, b) => a - b);
    const filas = new Map<string, FilaMatriz>();

    for (const franja of franjas) {
        const clave = `${franja.inicio}|${franja.fin}|${franja.tipo}`;
        let fila = filas.get(clave);

        if (!fila) {
            fila = {
                inicio: franja.inicio,
                fin: franja.fin,
                tipo: franja.tipo,
                celdas: dias.map(() => null),
            };
            filas.set(clave, fila);
        }

        fila.celdas[dias.indexOf(franja.dia)] = franja;
    }

    return {
        dias,
        filas: [...filas.values()].sort(
            (a, b) =>
                a.inicio.localeCompare(b.inicio) || a.fin.localeCompare(b.fin),
        ),
    };
}
