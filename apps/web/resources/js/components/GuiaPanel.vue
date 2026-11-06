<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check, ListChecks, Minus, X } from '@lucide/vue';
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { resaltar, useGuia } from '@/composables/useGuia';
import { avance, PASOS } from '@/lib/guia';
import { index as importar } from '@/routes/importar';

const { guia, actual, abierto, minimizado, cerrar } = useGuia();

const conteo = computed(() =>
    guia.value ? avance(guia.value.hechos) : { hechos: 0, total: PASOS.length },
);

// El borde va en el botón del paso actual solo mientras la guía está abierta.
const claveResaltada = computed(() =>
    abierto.value ? (actual.value?.clave ?? null) : null,
);

watch(claveResaltada, resaltar, { immediate: true });

// En el celular el panel va abajo a lo ancho: la página gana ese alto como espacio al
// final, para que el panel no tape los últimos botones (como «Guardar»).
const caja = ref<HTMLElement | null>(null);
let observador: ResizeObserver | null = null;

function medir(): void {
    const raiz = document.documentElement;
    raiz.classList.toggle('guia-con-panel', caja.value !== null);

    if (caja.value === null) {
        raiz.style.removeProperty('--guia-alto');
    } else {
        raiz.style.setProperty(
            '--guia-alto',
            `${caja.value.offsetHeight + 12}px`,
        );
    }
}

// Se mide en el momento y al cambiar lo que el panel muestra; el observador cubre lo demás.
watch(caja, (elemento) => {
    observador?.disconnect();
    medir();

    if (elemento !== null) {
        observador = new ResizeObserver(medir);
        observador.observe(elemento);
    }
});

watch([minimizado, actual], () => nextTick(medir));

onUnmounted(() => {
    resaltar(null);
    observador?.disconnect();
    document.documentElement.classList.remove('guia-con-panel');
});
</script>

<template>
    <div
        v-if="guia && abierto"
        ref="caja"
        class="fixed inset-x-3 bottom-3 z-50 sm:inset-x-auto sm:right-4 sm:bottom-4 sm:w-80"
    >
        <button
            v-if="minimizado"
            type="button"
            class="ml-auto flex items-center gap-2 rounded-full border bg-background px-4 py-2 text-sm font-medium shadow-lg hover:bg-accent"
            @click="minimizado = false"
        >
            <ListChecks class="size-4" />
            Guía {{ conteo.hechos }}/{{ conteo.total }}
        </button>

        <section
            v-else
            role="region"
            aria-label="Guía de primeros pasos"
            class="space-y-3 rounded-xl border bg-background p-4 shadow-lg"
        >
            <div class="flex items-center justify-between gap-2">
                <p class="text-sm font-semibold">
                    Primeros pasos · {{ conteo.hechos }} de {{ conteo.total }}
                </p>
                <div class="flex">
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label="Minimizar la guía"
                        @click="minimizado = true"
                    >
                        <Minus />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label="Cerrar la guía"
                        @click="cerrar"
                    >
                        <X />
                    </Button>
                </div>
            </div>

            <div class="h-1.5 rounded-full bg-muted">
                <div
                    class="h-1.5 rounded-full bg-primary transition-[width]"
                    :style="{
                        width: `${(conteo.hechos / conteo.total) * 100}%`,
                    }"
                />
            </div>

            <div v-if="actual" class="space-y-2 rounded-lg bg-muted/50 p-3">
                <p class="font-medium">{{ actual.titulo }}</p>
                <p class="text-sm">{{ actual.queHacer }}</p>
                <p class="text-xs text-muted-foreground">
                    {{ actual.paraQue }}
                </p>
                <Button size="sm" as-child>
                    <Link :href="actual.ir()">Ir a {{ actual.destino }}</Link>
                </Button>
            </div>
            <p v-else class="rounded-lg bg-muted/50 p-3 text-sm font-medium">
                Listo: ya generaste tu primer horario.
            </p>

            <ol class="hidden space-y-1 sm:block">
                <li v-for="paso in PASOS" :key="paso.clave">
                    <Link
                        :href="paso.ir()"
                        class="flex items-center gap-2 rounded px-1 py-0.5 text-sm hover:bg-accent"
                        :class="
                            guia.hechos[paso.clave]
                                ? 'text-muted-foreground'
                                : paso.clave === actual?.clave
                                  ? 'font-medium'
                                  : ''
                        "
                    >
                        <Check
                            v-if="guia.hechos[paso.clave]"
                            class="size-4 text-green-600 dark:text-green-500"
                        />
                        <span
                            v-else
                            class="size-4 rounded-full border border-muted-foreground/50"
                        />
                        {{ paso.titulo }}
                    </Link>
                </li>
            </ol>

            <p v-if="guia.hechos.ciclo && actual" class="text-xs">
                ¿Ya tienes tus datos en Excel?
                <Link :href="importar()" class="font-medium underline"
                    >Importar todo</Link
                >
            </p>
        </section>
    </div>
</template>
