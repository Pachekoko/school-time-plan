<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import GuiaPanel from '@/components/GuiaPanel.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="min-w-0 overflow-x-clip">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <!-- Los avisos salen arriba del panel de la guía; GuiaPanel pone --guia-alto. -->
        <Toaster
            :offset="{ bottom: 'calc(var(--guia-alto, 0px) + 24px)' }"
            :mobile-offset="{ bottom: 'calc(var(--guia-alto, 0px) + 16px)' }"
        />
        <GuiaPanel />
    </AppShell>
</template>
