<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarCheck, Pencil, Plus } from '@lucide/vue';
import DisponibilidadController from '@/actions/App/Http/Controllers/Escuela/DisponibilidadController';
import ProfesorController from '@/actions/App/Http/Controllers/Escuela/ProfesorController';
import BotonBorrar from '@/components/BotonBorrar.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/profesores';

type Profesor = {
    id: number;
    clave: string;
    nombre: string;
    contrato: string;
    horas: string;
    planteles: number;
    materias: number;
    cuenta: string | null;
};

defineProps<{
    profesores: Profesor[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Profesores', href: index() }],
    },
});
</script>

<template>
    <Head title="Profesores" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Profesores"
                description="Su carga (horas mínimas y máximas), dónde dan clase, qué materias pueden dar y su disponibilidad."
            />
            <Button as-child data-guia="profesores">
                <Link :href="ProfesorController.create()">
                    <Plus />
                    Nuevo profesor
                </Link>
            </Button>
        </div>

        <p
            v-if="profesores.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Aún no hay profesores.
        </p>

        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 font-medium">Clave</th>
                        <th class="px-4 py-2 font-medium">Nombre</th>
                        <th class="px-4 py-2 font-medium">Contrato</th>
                        <th class="px-4 py-2 text-right font-medium">Horas</th>
                        <th class="px-4 py-2 text-right font-medium">
                            Planteles
                        </th>
                        <th class="px-4 py-2 text-right font-medium">
                            Materias
                        </th>
                        <th class="px-4 py-2 font-medium">Cuenta</th>
                        <th class="px-4 py-2">
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="profesor in profesores"
                        :key="profesor.id"
                        class="border-t hover:bg-muted/30"
                    >
                        <td class="px-4 py-2 font-mono">
                            {{ profesor.clave }}
                        </td>
                        <td class="px-4 py-2">{{ profesor.nombre }}</td>
                        <td class="px-4 py-2">{{ profesor.contrato }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ profesor.horas }}
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ profesor.planteles }}
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ profesor.materias }}
                        </td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ profesor.cuenta ?? '—' }}
                        </td>
                        <td class="px-4 py-1">
                            <div class="flex items-center justify-end gap-1">
                                <Button variant="outline" size="sm" as-child>
                                    <Link
                                        :href="
                                            DisponibilidadController.edit(
                                                profesor.id,
                                            )
                                        "
                                    >
                                        <CalendarCheck />
                                        Disponibilidad
                                    </Link>
                                </Button>
                                <Button variant="ghost" size="icon-sm" as-child>
                                    <Link
                                        :href="
                                            ProfesorController.edit(profesor.id)
                                        "
                                        :aria-label="`Editar ${profesor.nombre}`"
                                    >
                                        <Pencil />
                                    </Link>
                                </Button>
                                <BotonBorrar
                                    :nombre="profesor.nombre"
                                    :accion="
                                        ProfesorController.destroy(profesor.id)
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
