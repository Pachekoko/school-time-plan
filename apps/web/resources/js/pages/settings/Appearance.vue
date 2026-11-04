<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Ajustes de apariencia',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Ajustes de apariencia" />

    <h1 class="sr-only">Ajustes de apariencia</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Ajustes de apariencia"
            description="Ajusta la apariencia de tu cuenta"
        />
        <AppearanceTabs />
    </div>
</template>
