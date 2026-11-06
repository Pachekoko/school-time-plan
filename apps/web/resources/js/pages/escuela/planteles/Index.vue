<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Pencil, Plus } from '@lucide/vue';
import PlantelController from '@/actions/App/Http/Controllers/Escuela/PlantelController';
import BotonBorrar from '@/components/BotonBorrar.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/planteles';

type Plantel = {
    id: number;
    clave: string;
    nombre: string;
    direccion: string | null;
    espacios_count: number;
};

defineProps<{
    planteles: Plantel[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Planteles', href: index() }],
    },
});
</script>

<template>
    <Head title="Planteles" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Planteles"
                description="Las sedes de tu escuela. Cada grupo y cada espacio pertenecen a un plantel."
            />
            <Button as-child data-guia="planteles">
                <Link :href="PlantelController.create()">
                    <Plus />
                    Nuevo plantel
                </Link>
            </Button>
        </div>

        <p
            v-if="planteles.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Aún no hay planteles. Crea el primero para empezar.
        </p>

        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 font-medium">Clave</th>
                        <th class="px-4 py-2 font-medium">Nombre</th>
                        <th class="px-4 py-2 font-medium">Dirección</th>
                        <th class="px-4 py-2 text-right font-medium">
                            Espacios
                        </th>
                        <th class="px-4 py-2">
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="plantel in planteles"
                        :key="plantel.id"
                        class="border-t hover:bg-muted/30"
                    >
                        <td class="px-4 py-2 font-mono">{{ plantel.clave }}</td>
                        <td class="px-4 py-2">{{ plantel.nombre }}</td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ plantel.direccion ?? '—' }}
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ plantel.espacios_count }}
                        </td>
                        <td class="px-4 py-1">
                            <div class="flex justify-end gap-1">
                                <Button variant="ghost" size="icon-sm" as-child>
                                    <Link
                                        :href="
                                            PlantelController.edit(plantel.id)
                                        "
                                        :aria-label="`Editar ${plantel.nombre}`"
                                    >
                                        <Pencil />
                                    </Link>
                                </Button>
                                <BotonBorrar
                                    :nombre="plantel.nombre"
                                    :accion="
                                        PlantelController.destroy(plantel.id)
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
