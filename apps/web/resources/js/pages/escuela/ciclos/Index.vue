<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Pencil, Plus } from '@lucide/vue';
import CicloController from '@/actions/App/Http/Controllers/Escuela/CicloController';
import BotonBorrar from '@/components/BotonBorrar.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { fechaCorta } from '@/lib/fechas';
import { index } from '@/routes/ciclos';

type Ciclo = {
    id: number;
    clave: string;
    inicio: string | null;
    fin: string | null;
    estado: 'captura' | 'cerrado';
    estado_etiqueta: string;
    actual: boolean;
};

defineProps<{
    ciclos: Ciclo[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Ciclos', href: index() }],
    },
});
</script>

<template>
    <Head title="Ciclos" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Ciclos"
                description="Los grupos y la carga académica se capturan por ciclo. La interfaz trabaja sobre el ciclo actual."
            />
            <Button as-child data-guia="ciclo">
                <Link :href="CicloController.create()">
                    <Plus />
                    Nuevo ciclo
                </Link>
            </Button>
        </div>

        <p
            v-if="ciclos.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Aún no hay ciclos. El primero que crees quedará como ciclo actual.
        </p>

        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 font-medium">Clave</th>
                        <th class="px-4 py-2 font-medium">Inicio</th>
                        <th class="px-4 py-2 font-medium">Fin</th>
                        <th class="px-4 py-2 font-medium">Estado</th>
                        <th class="px-4 py-2">
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="ciclo in ciclos"
                        :key="ciclo.id"
                        class="border-t hover:bg-muted/30"
                    >
                        <td class="px-4 py-2 font-mono">
                            {{ ciclo.clave }}
                            <Badge v-if="ciclo.actual" class="ml-2"
                                >Actual</Badge
                            >
                        </td>
                        <td class="px-4 py-2">
                            {{ fechaCorta(ciclo.inicio) }}
                        </td>
                        <td class="px-4 py-2">{{ fechaCorta(ciclo.fin) }}</td>
                        <td class="px-4 py-2">
                            <Badge
                                :variant="
                                    ciclo.estado === 'captura'
                                        ? 'secondary'
                                        : 'outline'
                                "
                            >
                                {{ ciclo.estado_etiqueta }}
                            </Badge>
                        </td>
                        <td class="px-4 py-1">
                            <div class="flex items-center justify-end gap-1">
                                <Button
                                    v-if="!ciclo.actual"
                                    variant="outline"
                                    size="sm"
                                    as-child
                                >
                                    <Link
                                        :href="
                                            CicloController.activar(ciclo.id)
                                        "
                                        as="button"
                                    >
                                        Usar como actual
                                    </Link>
                                </Button>
                                <Button variant="ghost" size="icon-sm" as-child>
                                    <Link
                                        :href="CicloController.edit(ciclo.id)"
                                        :aria-label="`Editar ${ciclo.clave}`"
                                    >
                                        <Pencil />
                                    </Link>
                                </Button>
                                <BotonBorrar
                                    :nombre="ciclo.clave"
                                    :accion="CicloController.destroy(ciclo.id)"
                                />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
