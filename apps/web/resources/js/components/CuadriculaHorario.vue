<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import SelectNativo from '@/components/SelectNativo.vue';
import { Button } from '@/components/ui/button';
import { armarCuadricula } from '@/lib/cuadricula';
import type { FranjaUI, SesionUI } from '@/lib/cuadricula';
import { DIAS_CORTOS, DIAS_LARGOS } from '@/lib/dias';

type Entidad = { id: number; nombre: string; plantilla_ids: number[] };
type Vista = 'grupos' | 'profesores' | 'espacios';

const props = defineProps<{
    franjas: FranjaUI[];
    sesiones: SesionUI[];
    catalogos: Record<Vista, Entidad[]>;
}>();

const VISTAS: { valor: Vista; etiqueta: string }[] = [
    { valor: 'grupos', etiqueta: 'Por grupo' },
    { valor: 'profesores', etiqueta: 'Por profesor' },
    { valor: 'espacios', etiqueta: 'Por espacio' },
];

const vista = ref<Vista>('grupos');
const entidadId = ref<number | null>(null);

const entidades = computed(() => props.catalogos[vista.value] ?? []);

watch(
    entidades,
    (lista) => {
        if (!lista.some((e) => e.id === entidadId.value)) {
            entidadId.value = lista[0]?.id ?? null;
        }
    },
    { immediate: true },
);

const entidad = computed(
    () => entidades.value.find((e) => e.id === entidadId.value) ?? null,
);

const delaEntidad = computed(() =>
    props.sesiones.filter((s) => {
        const id = entidadId.value;

        if (vista.value === 'grupos') {
            return s.grupos.some((g) => g.id === id);
        }

        return (
            (vista.value === 'profesores' ? s.profesor?.id : s.espacio?.id) ===
            id
        );
    }),
);

const cuadricula = computed(() =>
    armarCuadricula(
        props.franjas.filter((f) =>
            entidad.value?.plantilla_ids.includes(f.plantilla_id),
        ),
        delaEntidad.value,
    ),
);

const sinHora = computed(() =>
    delaEntidad.value.filter((s) => s.inicio === null),
);

// El mismo color para la misma materia en todas las vistas.
const COLORES = [
    'border-sky-500/40 bg-sky-100 dark:bg-sky-950',
    'border-violet-500/40 bg-violet-100 dark:bg-violet-950',
    'border-emerald-500/40 bg-emerald-100 dark:bg-emerald-950',
    'border-amber-500/40 bg-amber-100 dark:bg-amber-950',
    'border-rose-500/40 bg-rose-100 dark:bg-rose-950',
    'border-teal-500/40 bg-teal-100 dark:bg-teal-950',
    'border-fuchsia-500/40 bg-fuchsia-100 dark:bg-fuchsia-950',
    'border-lime-500/40 bg-lime-100 dark:bg-lime-950',
];

function colorDe(materia: string): string {
    let suma = 0;

    for (const letra of materia) {
        suma = (suma * 31 + letra.charCodeAt(0)) >>> 0;
    }

    return COLORES[suma % COLORES.length];
}

const detalle = (s: SesionUI): string[] =>
    [
        vista.value !== 'grupos'
            ? s.grupos.map((g) => g.nombre).join(', ')
            : null,
        vista.value !== 'profesores'
            ? (s.profesor?.nombre ?? 'Sin profesor')
            : null,
        vista.value !== 'espacios'
            ? (s.espacio?.nombre ?? 'Sin espacio')
            : null,
    ].filter((t): t is string => t !== null);
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center gap-3">
            <div
                class="inline-flex rounded-md border p-0.5"
                role="group"
                aria-label="Ver horario"
            >
                <Button
                    v-for="opcion in VISTAS"
                    :key="opcion.valor"
                    size="sm"
                    :variant="vista === opcion.valor ? 'secondary' : 'ghost'"
                    :aria-pressed="vista === opcion.valor"
                    @click="vista = opcion.valor"
                >
                    {{ opcion.etiqueta }}
                </Button>
            </div>
            <SelectNativo
                v-if="entidades.length > 0"
                class="w-64"
                aria-label="Elegir cuál"
                @change="
                    entidadId = Number(
                        ($event.target as HTMLSelectElement).value,
                    )
                "
            >
                <option
                    v-for="opcion in entidades"
                    :key="opcion.id"
                    :value="opcion.id"
                    :selected="opcion.id === entidadId"
                >
                    {{ opcion.nombre }}
                </option>
            </SelectNativo>
        </div>

        <p
            v-if="entidad === null"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            No hay nada que mostrar en esta vista.
        </p>

        <div v-else class="overflow-x-auto rounded-lg border">
            <table
                class="w-full table-fixed border-separate border-spacing-1 text-sm"
            >
                <caption class="sr-only">
                    Horario de
                    {{
                        entidad.nombre
                    }}
                </caption>
                <thead>
                    <tr>
                        <th
                            class="w-28 px-2 py-1 text-left font-medium text-muted-foreground"
                        >
                            Hora
                        </th>
                        <th
                            v-for="dia in cuadricula.dias"
                            :key="dia"
                            class="min-w-36 px-2 py-1 text-left font-medium text-muted-foreground"
                        >
                            <abbr
                                :title="DIAS_LARGOS[dia]"
                                class="no-underline"
                                >{{ DIAS_CORTOS[dia] }}</abbr
                            >
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(fila, f) in cuadricula.filas"
                        :key="`${fila.inicio}-${fila.fin}`"
                    >
                        <th
                            scope="row"
                            class="px-2 py-1 text-left align-top font-mono text-xs font-normal whitespace-nowrap text-muted-foreground"
                        >
                            {{ fila.inicio }}–{{ fila.fin }}
                        </th>
                        <td
                            v-if="
                                fila.tipo === 'receso' &&
                                cuadricula.dias.every(
                                    (d) => !cuadricula.celdas[`${d}-${f}`],
                                )
                            "
                            :colspan="cuadricula.dias.length"
                            class="rounded-md bg-muted/50 px-2 py-1 text-center text-xs text-muted-foreground"
                        >
                            Receso
                        </td>
                        <template v-else>
                            <td
                                v-for="dia in cuadricula.dias"
                                :key="dia"
                                class="align-top"
                            >
                                <div
                                    v-for="sesion in cuadricula.celdas[
                                        `${dia}-${f}`
                                    ] ?? []"
                                    :key="sesion.id"
                                    class="mb-1 rounded-md border px-2 py-1 last:mb-0"
                                    :class="colorDe(sesion.materia)"
                                >
                                    <p class="leading-tight font-medium">
                                        {{ sesion.materia }}
                                    </p>
                                    <p
                                        v-for="linea in detalle(sesion)"
                                        :key="linea"
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{ linea }}
                                    </p>
                                </div>
                            </td>
                        </template>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="sinHora.length > 0"
            class="rounded-lg border border-dashed p-3 text-sm"
        >
            <p class="mb-2 font-medium">Sin acomodar</p>
            <ul class="flex flex-wrap gap-2">
                <li
                    v-for="sesion in sinHora"
                    :key="sesion.id"
                    class="rounded-md border px-2 py-1"
                >
                    {{ sesion.materia }}
                    <span class="text-xs text-muted-foreground">
                        · {{ sesion.grupos.map((g) => g.nombre).join(', ') }}
                    </span>
                </li>
            </ul>
        </div>
    </div>
</template>
