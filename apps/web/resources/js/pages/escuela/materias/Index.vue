<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Pencil, Plus } from '@lucide/vue';
import MateriaController from '@/actions/App/Http/Controllers/Escuela/MateriaController';
import BotonBorrar from '@/components/BotonBorrar.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/materias';

type Materia = {
    id: number;
    clave: string;
    nombre: string;
    plan: string;
    semestre: number;
    horas_semana: number;
    patron: string;
    patron_capturado: boolean;
    tipo_espacio: string;
};

defineProps<{
    materias: Materia[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Materias', href: index() }],
    },
});
</script>

<template>
    <Head title="Materias" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Materias"
                description="Horas por semana y cómo se reparten en bloques: 2+1 es un día de 2 h y otro de 1 h."
            />
            <Button as-child data-guia="materias">
                <Link :href="MateriaController.create()">
                    <Plus />
                    Nueva materia
                </Link>
            </Button>
        </div>

        <p
            v-if="materias.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Aún no hay materias. Primero crea un plan de estudios.
        </p>

        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 font-medium">Clave</th>
                        <th class="px-4 py-2 font-medium">Nombre</th>
                        <th class="px-4 py-2 font-medium">Plan</th>
                        <th class="px-4 py-2 text-right font-medium">Sem.</th>
                        <th class="px-4 py-2 text-right font-medium">Horas</th>
                        <th class="px-4 py-2 font-medium">Bloques</th>
                        <th class="px-4 py-2 font-medium">Espacio</th>
                        <th class="px-4 py-2">
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="materia in materias"
                        :key="materia.id"
                        class="border-t hover:bg-muted/30"
                    >
                        <td class="px-4 py-2 font-mono">{{ materia.clave }}</td>
                        <td class="px-4 py-2">{{ materia.nombre }}</td>
                        <td class="px-4 py-2 font-mono text-muted-foreground">
                            {{ materia.plan }}
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ materia.semestre }}
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ materia.horas_semana }}
                        </td>
                        <td
                            class="px-4 py-2 font-mono"
                            :class="{
                                'text-muted-foreground':
                                    !materia.patron_capturado,
                            }"
                            :title="
                                materia.patron_capturado
                                    ? 'Capturado'
                                    : 'Derivado de las horas'
                            "
                        >
                            {{ materia.patron }}
                        </td>
                        <td class="px-4 py-2">{{ materia.tipo_espacio }}</td>
                        <td class="px-4 py-1">
                            <div class="flex justify-end gap-1">
                                <Button variant="ghost" size="icon-sm" as-child>
                                    <Link
                                        :href="
                                            MateriaController.edit(materia.id)
                                        "
                                        :aria-label="`Editar ${materia.nombre}`"
                                    >
                                        <Pencil />
                                    </Link>
                                </Button>
                                <BotonBorrar
                                    :nombre="materia.nombre"
                                    :accion="
                                        MateriaController.destroy(materia.id)
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
