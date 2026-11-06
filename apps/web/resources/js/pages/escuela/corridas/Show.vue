<script setup lang="ts">
import { Form, Head, Link, router, usePoll } from '@inertiajs/vue3';
import { LoaderCircle, Play } from '@lucide/vue';
import { computed, onMounted, watch } from 'vue';
import CorridaController from '@/actions/App/Http/Controllers/Escuela/CorridaController';
import AlertError from '@/components/AlertError.vue';
import CuadriculaHorario from '@/components/CuadriculaHorario.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ListaDiagnostico from '@/components/ListaDiagnostico.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { FranjaUI, SesionUI } from '@/lib/cuadricula';
import { index } from '@/routes/corridas';

type Corrida = {
    id: number;
    estado: string;
    estado_etiqueta: string;
    activa: boolean;
    puntaje: number | null;
    desglose: Record<
        string,
        { crudo: number; peso: number; penalizacion: number }
    >;
    diagnostico: {
        codigo: string;
        regla: string;
        entidades: { tipo: string; id: string }[];
        mensaje: string;
    }[];
    estadisticas: { tiempo_s: number } | null;
    mensaje_error: string | null;
    tiempo_limite_s: number;
    permitir_parcial: boolean;
    creada: string | null;
    terminada: string | null;
};

type Entidad = { id: number; nombre: string; plantilla_ids: number[] };

const props = defineProps<{
    corrida: Corrida;
    sesiones: SesionUI[];
    franjas: FranjaUI[];
    catalogos: Record<'grupos' | 'profesores' | 'espacios', Entidad[]>;
    entidades: Record<string, { nombre: string | null; url: string | null }>;
    reglas: Record<string, string>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Horarios', href: index() }],
    },
});

// Mientras el motor trabaja, pregunta cada 3 s solo por el estado y las sesiones.
const { start, stop } = usePoll(
    3000,
    { only: ['corrida', 'sesiones'] },
    { autoStart: false },
);

onMounted(() => {
    if (props.corrida.activa) {
        start();
    }
});

watch(
    () => props.corrida.activa,
    (activa, antes) => {
        if (!activa) {
            stop();

            // Al terminar llegan catálogos y entidades que el sondeo no trae.
            if (antes) {
                router.reload();
            }
        }
    },
);

const VARIANTES: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    ok: 'default',
    factible_parcial: 'secondary',
    error: 'destructive',
    sin_solucion: 'destructive',
};

const penalizaciones = computed(() =>
    Object.entries(props.corrida.desglose)
        .filter(([, d]) => d.penalizacion > 0)
        .sort(([, a], [, b]) => b.penalizacion - a.penalizacion),
);

const formato = new Intl.DateTimeFormat('es-MX', {
    dateStyle: 'medium',
    timeStyle: 'short',
});
</script>

<template>
    <Head :title="`Horario #${corrida.id}`" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="`Horario #${corrida.id}`"
                :description="
                    corrida.creada
                        ? `Generado el ${formato.format(new Date(corrida.creada))}`
                        : undefined
                "
            />
            <Button v-if="!corrida.activa" variant="outline" as-child>
                <Link :href="CorridaController.create()">
                    <Play />
                    Generar otro
                </Link>
            </Button>
        </div>

        <div class="flex flex-wrap items-center gap-3 text-sm">
            <Badge :variant="VARIANTES[corrida.estado] ?? 'outline'">{{
                corrida.estado_etiqueta
            }}</Badge>
            <span v-if="corrida.puntaje !== null" class="text-muted-foreground">
                Penalización {{ corrida.puntaje }}
            </span>
            <span v-if="corrida.estadisticas" class="text-muted-foreground">
                · {{ corrida.estadisticas.tiempo_s.toFixed(1) }} s de
                {{ corrida.tiempo_limite_s }} s
            </span>
        </div>

        <div
            v-if="corrida.activa"
            class="flex items-center gap-3 rounded-lg border p-4 text-sm"
            role="status"
            aria-live="polite"
        >
            <LoaderCircle class="size-4 animate-spin" aria-hidden="true" />
            El motor está trabajando. Esta página se actualiza sola.
        </div>

        <Form
            v-if="corrida.estado === 'error'"
            v-bind="CorridaController.reintentar.form(corrida.id)"
            class="flex flex-col gap-3"
            v-slot="{ errors, processing }"
        >
            <AlertError
                :errors="[corrida.mensaje_error ?? 'La corrida falló.']"
                title="No se generó el horario"
            />
            <InputError :message="errors.corrida" />
            <div>
                <Button type="submit" :disabled="processing">Reintentar</Button>
            </div>
        </Form>

        <section
            v-if="corrida.diagnostico.length > 0"
            class="flex flex-col gap-3"
        >
            <h3 class="font-medium">
                {{
                    corrida.estado === 'sin_solucion'
                        ? 'Por qué no hay horario'
                        : 'Avisos'
                }}
            </h3>
            <ListaDiagnostico
                :diagnostico="corrida.diagnostico"
                :entidades="entidades"
            />
        </section>

        <section v-if="sesiones.length > 0" class="flex flex-col gap-3">
            <h3 class="font-medium">Horario</h3>
            <CuadriculaHorario
                :franjas="franjas"
                :sesiones="sesiones"
                :catalogos="catalogos"
            />
        </section>

        <section v-if="penalizaciones.length > 0" class="flex flex-col gap-2">
            <h3 class="font-medium">Qué se sacrificó</h3>
            <table class="w-full max-w-xl text-sm">
                <thead class="text-left text-muted-foreground">
                    <tr>
                        <th class="py-1 font-medium">Regla</th>
                        <th class="py-1 text-right font-medium">Veces</th>
                        <th class="py-1 text-right font-medium">
                            Penalización
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="[clave, detalle] in penalizaciones"
                        :key="clave"
                        class="border-t"
                    >
                        <td class="py-1">{{ reglas[clave] ?? clave }}</td>
                        <td class="py-1 text-right tabular-nums">
                            {{ detalle.crudo }}
                        </td>
                        <td class="py-1 text-right tabular-nums">
                            {{ detalle.penalizacion }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>
</template>
