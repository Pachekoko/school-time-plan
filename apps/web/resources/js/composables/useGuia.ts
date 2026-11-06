import { useHttp, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { abreSola, pasoActual } from '@/lib/guia';
import type { Paso } from '@/lib/guia';
import { update } from '@/routes/guia';
import type { Guia } from '@/types/guia';

// Estado a nivel de módulo: el panel y el botón de la barra lateral comparten el mismo.
const abierto = ref(false);
const minimizado = ref(false);
let iniciado = false;

/** Marca en <html> el paso a resaltar; el CSS le pone el borde a cada [data-guia] de ese paso. */
export function resaltar(clave: string | null): void {
    const raiz = document.documentElement;

    if (clave === null) {
        raiz.removeAttribute('data-guia-paso');
    } else {
        raiz.setAttribute('data-guia-paso', clave);
    }
}

export function useGuia(): {
    guia: ComputedRef<Guia | null>;
    actual: ComputedRef<Paso | null>;
    abierto: Ref<boolean>;
    minimizado: Ref<boolean>;
    abrir: () => void;
    cerrar: () => void;
    alternar: () => void;
} {
    const page = usePage();
    const http = useHttp({ abierta: true });
    const guia = computed(() => page.props.guia);
    const actual = computed(() =>
        guia.value ? pasoActual(guia.value.hechos) : null,
    );

    // Se decide una sola vez por carga de página; después manda lo que haga el admin.
    if (!iniciado && guia.value) {
        iniciado = true;
        abierto.value = abreSola(guia.value);
    }

    // Sin visita de Inertia: no cambia la página ni corta otra petición. Si falla, la guía
    // queda como el admin la dejó en esta visita y en la siguiente manda el servidor.
    function guardar(abierta: boolean): void {
        http.abierta = abierta;
        http.submit(update()).catch(() => {});
    }

    function abrir(): void {
        abierto.value = true;
        minimizado.value = false;
        guardar(true);
    }

    function cerrar(): void {
        abierto.value = false;
        guardar(false);
    }

    return {
        guia,
        actual,
        abierto,
        minimizado,
        abrir,
        cerrar,
        alternar: () => (abierto.value ? cerrar() : abrir()),
    };
}
