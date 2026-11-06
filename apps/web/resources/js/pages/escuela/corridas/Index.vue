<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Play } from '@lucide/vue';
import CorridaController from '@/actions/App/Http/Controllers/Escuela/CorridaController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/corridas';

type Corrida = {
    id: number;
    estado: string;
    estado_etiqueta: string;
    activa: boolean;
    puntaje: number | null;
    sesiones: number;
    creada: string | null;
};

defineProps<{
    ciclo: string;
    corridas: Corrida[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Horarios', href: index() }],
    },
});

const formato = new Intl.DateTimeFormat('es-MX', {
    dateStyle: 'medium',
    timeStyle: 'short',
});
</script>

<template>
    <Head title="Horarios" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Horarios"
                :description="`Cada corrida del motor genera un horario para el ciclo ${ciclo}.`"
            />
            <Button as-child>
                <Link :href="CorridaController.create()">
                    <Play />
                    Generar horario
                </Link>
            </Button>
        </div>

        <p
            v-if="corridas.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Aún no has generado horarios en este ciclo.
        </p>

        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 font-medium">Corrida</th>
                        <th class="px-4 py-2 font-medium">Fecha</th>
                        <th class="px-4 py-2 font-medium">Estado</th>
                        <th class="px-4 py-2 text-right font-medium">
                            Sesiones
                        </th>
                        <th class="px-4 py-2 text-right font-medium">
                            Penalización
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="corrida in corridas"
                        :key="corrida.id"
                        class="border-t hover:bg-muted/30"
                    >
                        <td class="px-4 py-2">
                            <Link
                                :href="CorridaController.show(corrida.id)"
                                class="font-medium underline-offset-4 hover:underline"
                            >
                                #{{ corrida.id }}
                            </Link>
                        </td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{
                                corrida.creada
                                    ? formato.format(new Date(corrida.creada))
                                    : '—'
                            }}
                        </td>
                        <td class="px-4 py-2">
                            <Badge variant="secondary">{{
                                corrida.estado_etiqueta
                            }}</Badge>
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ corrida.sesiones }}
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ corrida.sesiones }}
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ corrida.puntaje ?? '—' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
