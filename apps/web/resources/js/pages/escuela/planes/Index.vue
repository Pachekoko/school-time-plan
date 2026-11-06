<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Pencil, Plus } from '@lucide/vue';
import PlanController from '@/actions/App/Http/Controllers/Escuela/PlanController';
import BotonBorrar from '@/components/BotonBorrar.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/planes';

type Plan = {
    id: number;
    clave: string;
    nombre: string;
    materias_count: number;
};

defineProps<{
    planes: Plan[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Planes de estudio', href: index() }],
    },
});
</script>

<template>
    <Head title="Planes de estudio" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Planes de estudio"
                description="Cada materia y cada grupo siguen un plan."
            />
            <Button as-child data-guia="materias">
                <Link :href="PlanController.create()">
                    <Plus />
                    Nuevo plan
                </Link>
            </Button>
        </div>

        <p
            v-if="planes.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Aún no hay planes. Crea el plan vigente para dar de alta sus
            materias.
        </p>

        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 font-medium">Clave</th>
                        <th class="px-4 py-2 font-medium">Nombre</th>
                        <th class="px-4 py-2 text-right font-medium">
                            Materias
                        </th>
                        <th class="px-4 py-2">
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="plan in planes"
                        :key="plan.id"
                        class="border-t hover:bg-muted/30"
                    >
                        <td class="px-4 py-2 font-mono">{{ plan.clave }}</td>
                        <td class="px-4 py-2">{{ plan.nombre }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ plan.materias_count }}
                        </td>
                        <td class="px-4 py-1">
                            <div class="flex justify-end gap-1">
                                <Button variant="ghost" size="icon-sm" as-child>
                                    <Link
                                        :href="PlanController.edit(plan.id)"
                                        :aria-label="`Editar ${plan.nombre}`"
                                    >
                                        <Pencil />
                                    </Link>
                                </Button>
                                <BotonBorrar
                                    :nombre="plan.nombre"
                                    :accion="PlanController.destroy(plan.id)"
                                />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
