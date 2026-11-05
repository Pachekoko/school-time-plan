<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Building2,
    CalendarCheck,
    CalendarRange,
    Clock,
    DoorOpen,
    FolderGit2,
    LayoutGrid,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard, miDisponibilidad } from '@/routes';
import { index as ciclos } from '@/routes/ciclos';
import { index as espacios } from '@/routes/espacios';
import { inicio } from '@/routes/escuela';
import { index as planteles } from '@/routes/planteles';
import { index as plantillas } from '@/routes/plantillas';
import type { NavItem } from '@/types';

const page = usePage();

const menu = computed<{ label: string; items: NavItem[] }>(() =>
    page.props.rol === 'admin'
        ? {
              label: 'Escuela',
              items: [
                  { title: 'Inicio', href: inicio(), icon: LayoutGrid },
                  { title: 'Ciclos', href: ciclos(), icon: CalendarRange },
                  { title: 'Planteles', href: planteles(), icon: Building2 },
                  { title: 'Espacios', href: espacios(), icon: DoorOpen },
                  { title: 'Plantillas', href: plantillas(), icon: Clock },
              ],
          }
        : {
              label: 'Profesor',
              items: [
                  {
                      title: 'Mi disponibilidad',
                      href: miDisponibilidad(),
                      icon: CalendarCheck,
                  },
              ],
          },
);

const footerNavItems: NavItem[] = [
    {
        title: 'Repositorio',
        href: 'https://github.com/Pachekoko/school-time-plan',
        icon: FolderGit2,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :label="menu.label" :items="menu.items" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
