<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Clock, Pencil, Plus } from '@lucide/vue';
import FranjaController from '@/actions/App/Http/Controllers/Escuela/FranjaController';
import PlantillaController from '@/actions/App/Http/Controllers/Escuela/PlantillaController';
import BotonBorrar from '@/components/BotonBorrar.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { rangoDias } from '@/lib/dias';
import { index } from '@/routes/plantillas';

type Plantilla = {
    id: number;
    clave: string;
    nombre: string;
    dias: number[];
    clases: number;
};

defineProps<{
    plantillas: Plantilla[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Plantillas', href: index() }],
    },
});
</script>

<template>
    <Head title="Plantillas horarias" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Plantillas horarias"
                description="Las franjas de clase y receso de cada turno. Cada grupo usa una plantilla."
            />
            <Button as-child>
                <Link :href="PlantillaController.create()">
                    <Plus />
                    Nueva plantilla
                </Link>
            </Button>
        </div>

        <p
            v-if="plantillas.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Aún no hay plantillas. Crea una por turno, por ejemplo «Matutino».
        </p>

        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 font-medium">Clave</th>
                        <th class="px-4 py-2 font-medium">Nombre</th>
                        <th class="px-4 py-2 font-medium">Días</th>
                        <th class="px-4 py-2 text-right font-medium">
                            Clases por semana
                        </th>
                        <th class="px-4 py-2">
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="plantilla in plantillas"
                        :key="plantilla.id"
                        class="border-t hover:bg-muted/30"
                    >
                        <td class="px-4 py-2 font-mono">
                            {{ plantilla.clave }}
                        </td>
                        <td class="px-4 py-2">{{ plantilla.nombre }}</td>
                        <td class="px-4 py-2">
                            {{ rangoDias(plantilla.dias) }}
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ plantilla.clases }}
                        </td>
                        <td class="px-4 py-1">
                            <div class="flex items-center justify-end gap-1">
                                <Button variant="outline" size="sm" as-child>
                                    <Link
                                        :href="
                                            FranjaController.index(plantilla.id)
                                        "
                                    >
                                        <Clock />
                                        Franjas
                                    </Link>
                                </Button>
                                <Button variant="ghost" size="icon-sm" as-child>
                                    <Link
                                        :href="
                                            PlantillaController.edit(
                                                plantilla.id,
                                            )
                                        "
                                        :aria-label="`Editar ${plantilla.nombre}`"
                                    >
                                        <Pencil />
                                    </Link>
                                </Button>
                                <BotonBorrar
                                    :nombre="plantilla.nombre"
                                    :accion="
                                        PlantillaController.destroy(
                                            plantilla.id,
                                        )
                                    "
                                />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
