<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Lock } from '@lucide/vue';
import { ref } from 'vue';
import MiDisponibilidadController from '@/actions/App/Http/Controllers/MiDisponibilidadController';
import Heading from '@/components/Heading.vue';
import MatrizDisponibilidad from '@/components/MatrizDisponibilidad.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import type { EstadoCelda, FranjaMatriz } from '@/lib/disponibilidad';
import { miDisponibilidad } from '@/routes';

defineProps<{
    profesor: { nombre: string } | null;
    cerrada: boolean;
    plantillas: { id: number; nombre: string; franjas: FranjaMatriz[] }[];
    estados: Record<string, 'P' | 'N'>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mi disponibilidad', href: miDisponibilidad() }],
    },
});

const guardando = ref(false);

function guardar(celdas: { franja_id: number; estado: EstadoCelda }[]): void {
    router.put(
        MiDisponibilidadController.update.url(),
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
    <Head title="Mi disponibilidad" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            title="Mi disponibilidad"
            description="Marca las horas en que no puedes dar clase y las que prefieres."
        />

        <p
            v-if="!profesor"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Tu cuenta todavía no está vinculada a un profesor. Pide a la
            coordinación que la vincule con tu correo.
        </p>

        <template v-else>
            <Alert v-if="cerrada">
                <Lock class="size-4" />
                <AlertTitle
                    >La captura de disponibilidad está cerrada.</AlertTitle
                >
                <AlertDescription>
                    Puedes consultarla; para cambiarla, habla con la
                    coordinación.
                </AlertDescription>
            </Alert>

            <MatrizDisponibilidad
                :plantillas="plantillas"
                :estados="estados"
                :solo-lectura="cerrada"
                :guardando="guardando"
                @guardar="guardar"
            />
        </template>
    </div>
</template>
