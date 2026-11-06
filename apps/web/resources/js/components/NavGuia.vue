<script setup lang="ts">
import { ListChecks } from '@lucide/vue';
import { computed } from 'vue';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useGuia } from '@/composables/useGuia';
import { avance } from '@/lib/guia';

const { guia, abierto, alternar } = useGuia();
const { isMobile, setOpenMobile } = useSidebar();

const conteo = computed(() => (guia.value ? avance(guia.value.hechos) : null));

// En el celular este botón vive en el menú lateral: se cierra para que el panel quede a la vista.
function alPulsar(): void {
    alternar();

    if (isMobile.value) {
        setOpenMobile(false);
    }
}
</script>

<template>
    <SidebarGroup v-if="conteo" class="group-data-[collapsible=icon]:p-0">
        <SidebarGroupContent>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        id="guia-boton"
                        :is-active="abierto"
                        :aria-expanded="abierto"
                        aria-controls="guia-panel"
                        :tooltip="`Guía ${conteo.hechos}/${conteo.total}`"
                        @click="alPulsar"
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
