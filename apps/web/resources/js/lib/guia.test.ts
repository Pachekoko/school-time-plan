/// <reference types="node" />
import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import type { ClavePaso } from '@/types/guia';
import { abreSola, avance, PASOS, pasoActual } from './guia';

const estilos = readFileSync(
    new URL('../../css/app.css', import.meta.url),
    'utf8',
);

const todos = (valor: boolean) =>
    Object.fromEntries(PASOS.map((p) => [p.clave, valor])) as Record<
        ClavePaso,
        boolean
    >;

describe('guía de primeros pasos', () => {
    it('lleva los pasos en el orden del servidor', () => {
        expect(PASOS.map((p) => p.clave)).toEqual([
            'ciclo',
            'planteles',
            'espacios',
            'plantilla',
            'materias',
            'grupos',
            'profesores',
            'carga',
            'generar',
        ]);
    });

    it('el paso actual es el primer pendiente, aunque haya hechos después', () => {
        expect(pasoActual(todos(false))?.clave).toBe('ciclo');
        expect(
            pasoActual({ ...todos(false), ciclo: true, espacios: true })?.clave,
        ).toBe('planteles');
        expect(pasoActual(todos(true))).toBeNull();
    });

    it('cuenta los pasos hechos', () => {
        expect(
            avance({ ...todos(false), ciclo: true, planteles: true }),
        ).toEqual({ hechos: 2, total: 9 });
    });

    it('se abre sola solo si faltan pasos y no se cerró', () => {
        expect(abreSola({ hechos: todos(false), cerrada: false })).toBe(true);
        expect(abreSola({ hechos: todos(false), cerrada: true })).toBe(false);
        expect(abreSola({ hechos: todos(true), cerrada: false })).toBe(false);
    });

    // El borde lo pone el CSS según <html data-guia-paso>, así alcanza también a los
    // botones que se montan después (como al volver a la misma página).
    it.each(PASOS.map((p) => p.clave))(
        'el paso %s tiene su regla de resaltado',
        (clave) => {
            expect(estilos).toMatch(
                new RegExp(
                    `\\[data-guia-paso=['"]${clave}['"]\\]\\s+\\[data-guia=['"]${clave}['"]\\]`,
                ),
            );
        },
    );
});
