import { describe, expect, it } from 'vitest';
import type { FranjaMatriz } from './disponibilidad';
import { armarMatriz, siguienteEstado } from './disponibilidad';

const franja = (
    id: number,
    dia: number,
    inicio: string,
    fin: string,
    tipo: 'clase' | 'receso' = 'clase',
): FranjaMatriz => ({ id, dia, orden: id, inicio, fin, tipo });

describe('siguienteEstado', () => {
    it('rota D → P → N → D', () => {
        expect(siguienteEstado('D')).toBe('P');
        expect(siguienteEstado('P')).toBe('N');
        expect(siguienteEstado('N')).toBe('D');
    });
});

describe('armarMatriz', () => {
    it('junta en una fila las franjas de igual horario y ordena por inicio', () => {
        const { dias, filas } = armarMatriz([
            franja(4, 1, '08:00', '09:00'),
            franja(1, 0, '07:00', '08:00'),
            franja(3, 1, '07:00', '08:00'),
            franja(2, 0, '08:00', '09:00'),
        ]);

        expect(dias).toEqual([0, 1]);
        expect(filas.map((f) => `${f.inicio}-${f.fin}`)).toEqual([
            '07:00-08:00',
            '08:00-09:00',
        ]);
        expect(filas[0].celdas.map((c) => c?.id)).toEqual([1, 3]);
    });

    it('deja hueco si un día no tiene ese horario y separa los recesos', () => {
        const { filas } = armarMatriz([
            franja(1, 0, '07:00', '08:00'),
            franja(2, 0, '08:00', '08:30', 'receso'),
            franja(3, 2, '07:00', '08:00'),
            franja(4, 2, '08:30', '09:30'),
        ]);

        expect(filas.map((f) => f.tipo)).toEqual(['clase', 'receso', 'clase']);
        expect(filas[1].celdas.map((c) => c?.id ?? null)).toEqual([2, null]);
        expect(filas[2].celdas.map((c) => c?.id ?? null)).toEqual([null, 4]);
    });
});
