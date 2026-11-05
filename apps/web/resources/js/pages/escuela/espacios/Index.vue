<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Pencil, Plus } from '@lucide/vue';
import EspacioController from '@/actions/App/Http/Controllers/Escuela/EspacioController';
import BotonBorrar from '@/components/BotonBorrar.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/espacios';

type Espacio = {
    id: number;
    clave: string;
    nombre: string;
    tipo: string;
    capacidad: number;
    plantel: string;
};

defineProps<{
    espacios: Espacio[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Espacios', href: index() }],
    },
});
</script>

<template>
    <Head title="Espacios" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Espacios"
                description="Aulas, laboratorios y demás lugares donde se da clase, con su capacidad."
            />
            <Button as-child>
                <Link :href="EspacioController.create()">
                    <Plus />
                    Nuevo espacio
                </Link>
            </Button>
        </div>

        <p
            v-if="espacios.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Aún no hay espacios. Primero da de alta un plantel y luego sus
            aulas.
        </p>

        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 font-medium">Clave</th>
                        <th class="px-4 py-2 font-medium">Nombre</th>
                        <th class="px-4 py-2 font-medium">Plantel</th>
                        <th class="px-4 py-2 font-medium">Tipo</th>
                        <th class="px-4 py-2 text-right font-medium">
                            Capacidad
                        </th>
                        <th class="px-4 py-2">
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="espacio in espacios"
                        :key="espacio.id"
                        class="border-t hover:bg-muted/30"
                    >
                        <td class="px-4 py-2 font-mono">{{ espacio.clave }}</td>
                        <td class="px-4 py-2">{{ espacio.nombre }}</td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ espacio.plantel }}
                        </td>
                        <td class="px-4 py-2">{{ espacio.tipo }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ espacio.capacidad }}
                        </td>
                        <td class="px-4 py-1">
                            <div class="flex justify-end gap-1">
                                <Button variant="ghost" size="icon-sm" as-child>
                                    <Link
                                        :href="
                                            EspacioController.edit(espacio.id)
                                        "
                                        :aria-label="`Editar ${espacio.nombre}`"
                                    >
                                        <Pencil />
                                    </Link>
                                </Button>
                                <BotonBorrar
                                    :nombre="espacio.nombre"
                                    :accion="
                                        EspacioController.destroy(espacio.id)
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
