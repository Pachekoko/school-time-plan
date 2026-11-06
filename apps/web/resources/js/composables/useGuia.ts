import { router, usePage } from '@inertiajs/vue3';
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

function guardar(abierta: boolean): void {
    router.put(
        update().url,
        { abierta },
        { preserveState: true, preserveScroll: true, only: ['guia'] },
    );
}

/** Quita el borde de la guía de toda la página y se lo pone a los botones del paso dado. */
export function resaltar(clave: string | null): void {
    document
        .querySelectorAll('.guia-resaltado')
        .forEach((elemento) => elemento.classList.remove('guia-resaltado'));

    if (clave !== null) {
        document
            .querySelectorAll(`[data-guia="${clave}"]`)
            .forEach((elemento) => elemento.classList.add('guia-resaltado'));
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
    const guia = computed(() => page.props.guia);
    const actual = computed(() =>
        guia.value ? pasoActual(guia.value.hechos) : null,
    );

    // Se decide una sola vez por carga de página; después manda lo que haga el admin.
    if (!iniciado && guia.value) {
        iniciado = true;
        abierto.value = abreSola(guia.value);
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
