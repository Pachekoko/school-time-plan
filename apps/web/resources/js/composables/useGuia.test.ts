import { reactive } from 'vue';
import { describe, expect, it, vi } from 'vitest';
import { PASOS } from '@/lib/guia';
import type { ClavePaso, Guia } from '@/types/guia';

const enrutador = vi.hoisted(() => ({ on: vi.fn(), reload: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    usePage: vi.fn(),
    useHttp: vi.fn(),
    router: enrutador,
}));

const todos = (valor: boolean) =>
    Object.fromEntries(PASOS.map((p) => [p.clave, valor])) as Record<
        ClavePaso,
        boolean
    >;

type Props = { guia: Guia | null; auth: { user: { id: number } } };

const props = (usuario: number, guia: Guia | null): Props => ({
    guia,
    auth: { user: { id: usuario } },
});

/** Un useGuia recién cargado (su estado es del módulo), con la página y el envío simulados. */
async function cargar(inicial: Props) {
    vi.resetModules();
    enrutador.on.mockClear();
    enrutador.reload.mockClear();
    const inertia = await import('@inertiajs/vue3');
    const pagina = reactive({ props: inicial });
    const enviados: boolean[] = [];
    const http = { abierta: true, submit: vi.fn() };
    http.submit.mockImplementation(() => {
        enviados.push(http.abierta);

        return Promise.resolve(null);
    });
    vi.mocked(inertia.usePage).mockReturnValue(pagina as never);
    vi.mocked(inertia.useHttp).mockReturnValue(http as never);
    const modulo = await import('./useGuia');

    return { ...modulo, pagina, enviados };
}

/** Lo que useGuia hace en cada evento `navigate` de Inertia. */
function alNavegar(): (evento: unknown) => void {
    const escuchas = enrutador.on.mock.calls.filter(
        ([evento]) => evento === 'navigate',
    );
    expect(escuchas).toHaveLength(1);

    return escuchas[0][1] as (evento: unknown) => void;
}

describe('useGuia', () => {
    it('vuelve a decidir si se abre cuando entra otro usuario en la misma pestaña', async () => {
        const { useGuia, pagina } = await cargar(
            props(1, { hechos: todos(false), cerrada: true }),
        );
        const primero = useGuia();
        primero.minimizado.value = true;
        expect(primero.abierto.value).toBe(false);

        pagina.props = props(2, { hechos: todos(false), cerrada: false });
        const segundo = useGuia();

        expect(segundo.abierto.value).toBe(true);
        expect(segundo.minimizado.value).toBe(false);
    });

    it('al terminar la da por cerrada sin quitar el panel de la vista', async () => {
        const { useGuia, enviados } = await cargar(
            props(1, { hechos: todos(false), cerrada: false }),
        );
        const guia = useGuia();

        guia.darPorTerminada();

        expect(enviados).toEqual([false]);
        expect(guia.abierto.value).toBe(true);
    });

    it('con Atrás o Adelante vuelve a pedir la guía al servidor', async () => {
        const { useGuia } = await cargar(
            props(1, { hechos: todos(false), cerrada: false }),
        );
        useGuia();
        useGuia();
        const avisar = alNavegar();

        avisar({ detail: { type: 'visit', page: { props: { guia: {} } } } });
        expect(enrutador.reload).not.toHaveBeenCalled();

        avisar({ detail: { type: 'history', page: { props: { guia: {} } } } });
        expect(enrutador.reload).toHaveBeenCalledWith({ only: ['guia'] });
    });

    it('sin guía en la página no la pide al volver', async () => {
        const { useGuia } = await cargar(
            props(1, { hechos: todos(false), cerrada: false }),
        );
        useGuia();

        alNavegar()({
            detail: { type: 'history', page: { props: { guia: null } } },
        });

        expect(enrutador.reload).not.toHaveBeenCalled();
    });

    it('resaltar no truena sin document (render en el servidor)', async () => {
        const { resaltar } = await cargar(props(1, null));

        expect(() => resaltar('ciclo')).not.toThrow();
    });
});
