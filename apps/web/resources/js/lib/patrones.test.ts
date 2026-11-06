import { describe, expect, it } from 'vitest';
import { derivarPatrones, formatearPatrones } from './patrones';

describe('derivarPatrones', () => {
    it('reparte como Patrones::derivar del servidor', () => {
        expect(derivarPatrones(1)).toEqual([[1]]);
        expect(derivarPatrones(2)).toEqual([[2], [1, 1]]);
        expect(derivarPatrones(3)).toEqual([[2, 1]]);
        expect(derivarPatrones(4)).toEqual([[2, 2]]);
        expect(derivarPatrones(5)).toEqual([[2, 2, 1]]);
        expect(derivarPatrones(6)).toEqual([[2, 2, 2]]);
        expect(derivarPatrones(7)).toEqual([[2, 2, 2, 1]]);
    });
});

describe('formatearPatrones', () => {
    it('separa bloques con + y alternativas con |', () => {
        expect(formatearPatrones([[2], [1, 1]])).toBe('2 | 1+1');
        expect(formatearPatrones([[2, 2, 1]])).toBe('2+2+1');
    });
});
