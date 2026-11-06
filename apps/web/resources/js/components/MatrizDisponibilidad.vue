<script setup lang="ts">
import { computed, ref, useTemplateRef, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { DIAS_CORTOS, DIAS_LARGOS } from '@/lib/dias';
import { armarMatriz, siguienteEstado } from '@/lib/disponibilidad';
import type { EstadoCelda, FranjaMatriz } from '@/lib/disponibilidad';

type Plantilla = { id: number; nombre: string; franjas: FranjaMatriz[] };

const props = defineProps<{
    plantillas: Plantilla[];
    estados: Record<string, 'P' | 'N'>;
    soloLectura?: boolean;
    guardando?: boolean;
}>();

const emit = defineEmits<{
    guardar: [celdas: { franja_id: number; estado: EstadoCelda }[]];
}>();

const ETIQUETAS: Record<EstadoCelda, string> = {
    D: 'Disponible',
    P: 'Preferente',
    N: 'No disponible',
};

const ESTILOS: Record<EstadoCelda, string> = {
    D: 'border-dashed text-muted-foreground hover:bg-muted/50',
    P: 'border-emerald-600/40 bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100',
    N: 'border-rose-600/40 bg-rose-100 text-rose-900 dark:bg-rose-950 dark:text-rose-100',
};

const matrices = computed(() =>
    props.plantillas.map((plantilla) => ({
        plantilla,
        ...armarMatriz(plantilla.franjas),
    })),
);

// Solo las franjas de clase se capturan: en un receso no hay clases.
const clases = computed(() =>
    props.plantillas.flatMap((p) =>
        p.franjas.filter((f) => f.tipo === 'clase'),
    ),
);

const guardado = (id: number): EstadoCelda => props.estados[id] ?? 'D';

const actuales = ref<Record<number, EstadoCelda>>({});

function reiniciar(): void {
    actuales.value = Object.fromEntries(
        clases.value.map((f) => [f.id, guardado(f.id)]),
    );
}

watch(() => [props.plantillas, props.estados], reiniciar, { immediate: true });

const cambios = computed(
    () =>
        clases.value.filter((f) => actuales.value[f.id] !== guardado(f.id))
            .length,
);

function rotar(id: number): void {
    if (!props.soloLectura) {
        actuales.value[id] = siguienteEstado(actuales.value[id] ?? 'D');
    }
}

function guardar(): void {
    emit(
        'guardar',
        clases.value.map((f) => ({
            franja_id: f.id,
            estado: actuales.value[f.id] ?? 'D',
        })),
    );
}

// Flechas: mueven el foco a la celda vecina con franja de clase.
const raiz = useTemplateRef<HTMLElement>('raiz');
const PASOS: Record<string, [number, number]> = {
    ArrowUp: [-1, 0],
    ArrowDown: [1, 0],
    ArrowLeft: [0, -1],
    ArrowRight: [0, 1],
};

function mover(
    evento: KeyboardEvent,
    matriz: number,
    fila: number,
    columna: number,
): void {
    const paso = PASOS[evento.key];

    if (!paso) {
        return;
    }

    evento.preventDefault();
    let [f, c] = [fila + paso[0], columna + paso[1]];

    while (f >= 0 && c >= 0 && f < 100 && c < 7) {
        const destino = raiz.value?.querySelector<HTMLButtonElement>(
            `[data-celda="${matriz}-${f}-${c}"]`,
        );

        if (destino) {
            destino.focus();

            return;
        }

        [f, c] = [f + paso[0], c + paso[1]];
    }
}
</script>

<template>
    <div ref="raiz" class="flex flex-col gap-6">
        <p class="text-sm text-muted-foreground">
            <template v-if="soloLectura">Solo lectura.</template>
            <template v-else>
                Toca una franja (o usa las flechas y Enter) para cambiarla:
                Disponible → Preferente → No disponible.
            </template>
        </p>

        <p
            v-if="plantillas.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Todavía no hay plantillas con franjas.
        </p>

        <section
            v-for="(matriz, m) in matrices"
            :key="matriz.plantilla.id"
            class="overflow-x-auto rounded-lg border"
        >
            <table class="w-full border-separate border-spacing-1 text-sm">
                <caption class="px-3 pt-2 text-left font-medium">
                    {{
                        matriz.plantilla.nombre
                    }}
                </caption>
                <thead>
                    <tr>
                        <th
                            class="w-24 px-2 py-1 text-left font-medium text-muted-foreground"
                        >
                            Hora
                        </th>
                        <th
                            v-for="dia in matriz.dias"
                            :key="dia"
                            class="px-2 py-1 text-left font-medium text-muted-foreground"
                        >
                            <abbr
                                :title="DIAS_LARGOS[dia]"
                                class="no-underline"
                            >
                                {{ DIAS_CORTOS[dia] }}
                            </abbr>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(fila, f) in matriz.filas"
                        :key="`${fila.inicio}-${fila.tipo}`"
                    >
                        <th
                            scope="row"
                            class="px-2 py-1 text-left font-mono text-xs font-normal whitespace-nowrap text-muted-foreground"
                        >
                            {{ fila.inicio }}–{{ fila.fin }}
                        </th>
                        <td
                            v-if="fila.tipo === 'receso'"
                            :colspan="matriz.dias.length"
                            class="rounded-md bg-muted/40 px-2 py-1 text-center text-xs text-muted-foreground"
                        >
                            Receso
                        </td>
                        <template v-else>
                            <td
                                v-for="(celda, c) in fila.celdas"
                                :key="c"
                                class="p-0"
                            >
                                <button
                                    v-if="celda"
                                    type="button"
                                    :data-celda="`${m}-${f}-${c}`"
                                    :disabled="soloLectura"
                                    :aria-label="`${DIAS_LARGOS[celda.dia]} ${celda.inicio}: ${ETIQUETAS[actuales[celda.id] ?? 'D']}`"
                                    class="flex h-10 w-full min-w-24 items-center justify-center rounded-md border px-2 text-xs transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-default"
                                    :class="[
                                        ESTILOS[actuales[celda.id] ?? 'D'],
                                        {
                                            'ring-2 ring-amber-500':
                                                actuales[celda.id] !==
                                                guardado(celda.id),
                                        },
                                    ]"
                                    @click="rotar(celda.id)"
                                    @keydown="mover($event, m, f, c)"
                                >
                                    {{ ETIQUETAS[actuales[celda.id] ?? 'D'] }}
                                </button>
                                <span
                                    v-else
                                    class="block text-center text-muted-foreground"
                                    >—</span
                                >
                            </td>
                        </template>
                    </tr>
                </tbody>
            </table>
        </section>

        <div
            v-if="!soloLectura && plantillas.length > 0"
            class="flex flex-wrap items-center gap-3"
        >
            <Button :disabled="cambios === 0 || guardando" @click="guardar">
                Guardar disponibilidad
            </Button>
            <Button
                v-if="cambios > 0"
                variant="ghost"
                :disabled="guardando"
                @click="reiniciar"
            >
                Deshacer cambios
            </Button>
            <span class="text-sm text-muted-foreground" aria-live="polite">
                {{
                    cambios === 0
                        ? 'Sin cambios.'
                        : cambios === 1
                          ? '1 cambio sin guardar.'
                          : `${cambios} cambios sin guardar.`
                }}
            </span>
        </div>
    </div>
</template>
