<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { index as ciclos } from '@/routes/ciclos';
import { inicio } from '@/routes/escuela';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Escuela',
                href: inicio(),
            },
        ],
    },
});

const page = usePage();
</script>

<template>
    <Head title="Escuela" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <Heading
            :title="page.props.escuela?.nombre ?? 'Escuela'"
            description="Captura los datos de tu escuela y genera su horario."
        />

        <div
            v-if="page.props.cicloActual"
            class="flex flex-wrap items-center gap-3 rounded-lg border p-4 text-sm"
        >
            <span class="text-muted-foreground">Ciclo actual</span>
            <span class="font-mono font-medium">
                {{ page.props.cicloActual.clave }}
            </span>
            <Badge
                :variant="
                    page.props.cicloActual.estado === 'captura'
                        ? 'secondary'
                        : 'outline'
                "
            >
                {{
                    page.props.cicloActual.estado === 'captura'
                        ? 'En captura'
                        : 'Cerrado'
                }}
            </Badge>
            <Link :href="ciclos()" class="ml-auto underline underline-offset-4">
                Cambiar de ciclo
            </Link>
        </div>

        <p
            v-else
            class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground"
        >
            Aún no hay ciclo actual: los grupos y la carga académica se capturan
            por ciclo.
            <Link
                :href="ciclos()"
                class="text-foreground underline underline-offset-4"
            >
                Crea el primero
            </Link>
        </p>
    </div>
</template>
