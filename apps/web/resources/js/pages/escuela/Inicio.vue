<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
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
    </div>
</template>
