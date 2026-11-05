<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { FileDown, FileSpreadsheet } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { index as ciclos } from '@/routes/ciclos';
import { inicio } from '@/routes/escuela';
import { datos, plantilla } from '@/routes/exportar';

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

        <section class="flex flex-col gap-3 rounded-lg border p-4">
            <div>
                <h3 class="font-medium">Excel</h3>
                <p class="text-sm text-muted-foreground">
                    Captura en la plantilla o baja tus datos para editarlos en
                    Excel y volver a importarlos.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" as-child>
                    <a :href="plantilla.url()" download>
                        <FileSpreadsheet />
                        Plantilla vacía
                    </a>
                </Button>
                <Button variant="outline" as-child>
                    <a :href="datos.url()" download>
                        <FileDown />
                        Mis datos en Excel
                    </a>
                </Button>
            </div>
        </section>
    </div>
</template>
