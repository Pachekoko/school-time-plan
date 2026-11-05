export type FranjaUI = {
    plantilla_id: number;
    dia: number;
    orden: number;
    inicio: string;
    fin: string;
    tipo: 'clase' | 'receso';
};

export type Referencia = { id: number; nombre: string };

// La forma de cada sesión en App\Solver\PresentadorCorrida.
export type SesionUI = {
    id: number;
    curso: string;
    materia: string;
    grupos: Referencia[];
    profesor: Referencia | null;
    espacio: Referencia | null;
    dia: number | null;
    inicio: string | null;
    fin: string | null;
    plantilla_id: number | null;
    sin_acomodar: boolean;
};

export type FilaCuadricula = {
    inicio: string;
    fin: string;
    tipo: 'clase' | 'receso';
};

export type Cuadricula = {
    dias: number[];
    filas: FilaCuadricula[];
    // Llave «{dia}-{indiceFila}».
    celdas: Record<string, SesionUI[]>;
};

/**
 * Filas por hora real: cada tramo entre dos bordes de franja que alguna franja cubre (así
 * dos plantillas que se traslapan no se enciman); es receso si solo lo cubren recesos.
 * Columnas: los días con franjas. Cada sesión va en todas las filas que toca.
 */
export function armarCuadricula(
    franjas: FranjaUI[],
    sesiones: SesionUI[],
): Cuadricula {
    const dias = [...new Set(franjas.map((f) => f.dia))].sort((a, b) => a - b);
    const bordes = [
        ...new Set(franjas.flatMap((f) => [f.inicio, f.fin])),
    ].sort();
    const filas: FilaCuadricula[] = [];

    for (let i = 0; i + 1 < bordes.length; i++) {
        const [inicio, fin] = [bordes[i], bordes[i + 1]];
        const cubren = franjas.filter(
            (f) => f.inicio <= inicio && f.fin >= fin,
        );

        if (cubren.length > 0) {
            filas.push({
                inicio,
                fin,
                tipo: cubren.every((f) => f.tipo === 'receso')
                    ? 'receso'
                    : 'clase',
            });
        }
    }

    const celdas: Record<string, SesionUI[]> = {};

    for (const sesion of sesiones) {
        const { dia, inicio, fin } = sesion;

        if (
            sesion.sin_acomodar ||
            dia === null ||
            inicio === null ||
            fin === null
        ) {
            continue;
        }

        filas.forEach((fila, i) => {
            if (inicio < fila.fin && fin > fila.inicio) {
                (celdas[`${dia}-${i}`] ??= []).push(sesion);
            }
        });
    }

    return { dias, filas, celdas };
}
