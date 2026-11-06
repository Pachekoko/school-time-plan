<script setup lang="ts">
import { ListChecks } from '@lucide/vue';
import { computed } from 'vue';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useGuia } from '@/composables/useGuia';
import { avance } from '@/lib/guia';

const { guia, abierto, alternar } = useGuia();

const conteo = computed(() => (guia.value ? avance(guia.value.hechos) : null));
</script>

<template>
    <SidebarGroup v-if="conteo" class="group-data-[collapsible=icon]:p-0">
        <SidebarGroupContent>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        :is-active="abierto"
                        :tooltip="`Guía ${conteo.hechos}/${conteo.total}`"
                        @click="alternar"
                    >
                        <ListChecks />
                        <span>Guía</span>
                        <span class="ml-auto text-xs text-muted-foreground"
                            >{{ conteo.hechos }}/{{ conteo.total }}</span
                        >
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroupContent>
    </SidebarGroup>
</template>
