<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import DisponibilidadController from '@/actions/App/Http/Controllers/Escuela/DisponibilidadController';
import Heading from '@/components/Heading.vue';
import MatrizDisponibilidad from '@/components/MatrizDisponibilidad.vue';
import type { EstadoCelda, FranjaMatriz } from '@/lib/disponibilidad';
import { index } from '@/routes/profesores';

const props = defineProps<{
    profesor: { id: number; clave: string; nombre: string };
    plantillas: { id: number; nombre: string; franjas: FranjaMatriz[] }[];
    estados: Record<string, 'P' | 'N'>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Profesores', href: index() }],
    },
});

const guardando = ref(false);

function guardar(celdas: { franja_id: number; estado: EstadoCelda }[]): void {
    router.put(
        DisponibilidadController.update.url(props.profesor.id),
        { celdas },
        {
            preserveScroll: true,
            onStart: () => {
                guardando.value = true;
            },
            onFinish: () => {
                guardando.value = false;
            },
        },
    );
}
</script>

<template>
    <Head :title="`Disponibilidad de ${profesor.nombre}`" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            :title="`Disponibilidad de «${profesor.nombre}»`"
            description="El motor nunca pone clase en una franja no disponible y procura usar las preferentes."
        />

        <MatrizDisponibilidad
            :plantillas="plantillas"
            :estados="estados"
            :guardando="guardando"
            @guardar="guardar"
        />
    </div>
</template>
