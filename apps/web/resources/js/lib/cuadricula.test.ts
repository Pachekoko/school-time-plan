import { describe, expect, it } from 'vitest';
import type { FranjaUI, SesionUI } from './cuadricula';
import { armarCuadricula } from './cuadricula';

const franja = (
    plantilla_id: number,
    dia: number,
    orden: number,
    inicio: string,
    fin: string,
    tipo: 'clase' | 'receso' = 'clase',
): FranjaUI => ({ plantilla_id, dia, orden, inicio, fin, tipo });

const sesion = (
    id: number,
    dia: number | null,
    inicio: string | null,
    fin: string | null,
    sinAcomodar = false,
): SesionUI => ({
    id,
    curso: `C${id}`,
    materia: 'Matemáticas I',
    grupos: [{ id: 1, nombre: '1A' }],
    profesor: null,
    espacio: null,
    dia,
    inicio,
    fin,
    plantilla_id: 1,
    sin_acomodar: sinAcomodar,
});

describe('armarCuadricula', () => {
    it('junta plantillas que se traslapan en filas por hora real', () => {
        const { filas } = armarCuadricula(
            [
                franja(1, 0, 0, '12:30', '13:20'),
                franja(2, 0, 0, '13:00', '13:50'),
            ],
            [],
        );

        expect(filas.map((f) => `${f.inicio}-${f.fin}`)).toEqual([
            '12:30-13:00',
            '13:00-13:20',
            '13:20-13:50',
        ]);
    });

    it('un bloque de 2 franjas ocupa 2 filas', () => {
        const { celdas } = armarCuadricula(
            [
                franja(1, 0, 0, '07:00', '07:50'),
                franja(1, 0, 1, '07:50', '08:40'),
                franja(1, 0, 2, '08:40', '09:30'),
            ],
            [sesion(7, 0, '07:00', '08:40')],
        );

        expect(celdas['0-0']?.map((s) => s.id)).toEqual([7]);
        expect(celdas['0-1']?.map((s) => s.id)).toEqual([7]);
        expect(celdas['0-2']).toBeUndefined();
    });

    it('los recesos son filas de tipo receso', () => {
        const { filas } = armarCuadricula(
            [
                franja(1, 0, 0, '07:00', '07:50'),
                franja(1, 0, 1, '07:50', '08:20', 'receso'),
                franja(1, 0, 2, '08:20', '09:10'),
            ],
            [],
        );

        expect(filas.map((f) => f.tipo)).toEqual(['clase', 'receso', 'clase']);
    });

    it('las columnas son los días con franjas y deja fuera lo que no tiene hora', () => {
        const { dias, celdas } = armarCuadricula(
            [
                franja(1, 2, 0, '07:00', '07:50'),
                franja(1, 0, 0, '07:00', '07:50'),
            ],
            [sesion(1, null, null, null, true), sesion(2, 2, '07:00', '07:50')],
        );

        expect(dias).toEqual([0, 2]);
        expect(Object.keys(celdas)).toEqual(['2-0']);
    });

    it('no inventa filas para los huecos entre franjas', () => {
        const { filas } = armarCuadricula(
            [
                franja(1, 0, 0, '07:00', '07:50'),
                franja(1, 0, 1, '08:00', '08:50'),
            ],
            [],
        );

        expect(filas.map((f) => `${f.inicio}-${f.fin}`)).toEqual([
            '07:00-07:50',
            '08:00-08:50',
        ]);
    });
});
