import { router, useHttp, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { abreSola, pasoActual } from '@/lib/guia';
import type { Paso } from '@/lib/guia';
import { update } from '@/routes/guia';
import type { Guia } from '@/types/guia';

// Estado a nivel de módulo: el panel y el botón de la barra lateral comparten el mismo.
const abierto = ref(false);
const minimizado = ref(false);
let usuarioIniciado: number | null = null;
let escuchaHistorial = false;

/** Marca en <html> el paso a resaltar; el CSS le pone el borde a cada [data-guia] de ese paso. */
export function resaltar(clave: string | null): void {
    if (typeof document === 'undefined') {
        return;
    }

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
    darPorTerminada: () => void;
} {
    const page = usePage();
    const http = useHttp({ abierta: true });
    const guia = computed(() => page.props.guia);
    const actual = computed(() =>
        guia.value ? pasoActual(guia.value.hechos) : null,
    );

    // Se decide una vez por usuario y carga de página; después manda lo que haga el admin.
    // Por usuario: si otro admin entra en la misma pestaña, no hereda cómo la dejó el anterior.
    const usuario = page.props.auth.user?.id ?? null;

    if (guia.value && usuarioIniciado !== usuario) {
        usuarioIniciado = usuario;
        abierto.value = abreSola(guia.value);
        minimizado.value = false;
    }

    // Atrás y Adelante reponen la página guardada en el historial, con su avance viejo.
    if (!escuchaHistorial) {
        escuchaHistorial = true;
        router.on('navigate', (evento) => {
            if (
                evento.detail.type === 'history' &&
                evento.detail.page.props.guia
            ) {
                router.reload({ only: ['guia'] });
            }
        });
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
        // Se guarda cerrada pero el panel sigue a la vista, con su «Listo».
        darPorTerminada: () => guardar(false),
    };
}
