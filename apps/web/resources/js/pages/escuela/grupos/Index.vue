<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Pencil, Plus } from '@lucide/vue';
import GrupoController from '@/actions/App/Http/Controllers/Escuela/GrupoController';
import BotonBorrar from '@/components/BotonBorrar.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/grupos';

type Grupo = {
    id: number;
    clave: string;
    semestre: number;
    alumnos: number;
    plantel: string;
    plantilla: string;
    plan: string;
};

defineProps<{
    ciclo: string;
    grupos: Grupo[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Grupos', href: index() }],
    },
});
</script>

<template>
    <Head title="Grupos" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Grupos"
                :description="`Los grupos del ciclo ${ciclo}. Cada uno toma clase en un plantel con una plantilla horaria.`"
            />
            <Button as-child>
                <Link :href="GrupoController.create()">
                    <Plus />
                    Nuevo grupo
                </Link>
            </Button>
        </div>

        <p
            v-if="grupos.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Aún no hay grupos en el ciclo {{ ciclo }}.
        </p>

        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 font-medium">Clave</th>
                        <th class="px-4 py-2 text-right font-medium">Sem.</th>
                        <th class="px-4 py-2 font-medium">Plantel</th>
                        <th class="px-4 py-2 font-medium">Plantilla</th>
                        <th class="px-4 py-2 font-medium">Plan</th>
                        <th class="px-4 py-2 text-right font-medium">
                            Alumnos
                        </th>
                        <th class="px-4 py-2">
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="grupo in grupos"
                        :key="grupo.id"
                        class="border-t hover:bg-muted/30"
                    >
                        <td class="px-4 py-2 font-mono">{{ grupo.clave }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ grupo.semestre }}
                        </td>
                        <td class="px-4 py-2">{{ grupo.plantel }}</td>
                        <td class="px-4 py-2">{{ grupo.plantilla }}</td>
                        <td class="px-4 py-2 font-mono text-muted-foreground">
                            {{ grupo.plan }}
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ grupo.alumnos }}
                        </td>
                        <td class="px-4 py-1">
                            <div class="flex justify-end gap-1">
                                <Button variant="ghost" size="icon-sm" as-child>
                                    <Link
                                        :href="GrupoController.edit(grupo.id)"
                                        :aria-label="`Editar ${grupo.clave}`"
                                    >
                                        <Pencil />
                                    </Link>
                                </Button>
                                <BotonBorrar
                                    :nombre="grupo.clave"
                                    :accion="GrupoController.destroy(grupo.id)"
                                />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
