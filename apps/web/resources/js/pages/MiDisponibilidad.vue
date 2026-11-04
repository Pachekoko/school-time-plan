<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { miDisponibilidad } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Mi disponibilidad',
                href: miDisponibilidad(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Mi disponibilidad" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <Heading
            title="Mi disponibilidad"
            description="Pronto podrás capturar tu disponibilidad."
        />
    </div>
</template>
