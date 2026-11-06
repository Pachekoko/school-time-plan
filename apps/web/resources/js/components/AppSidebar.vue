<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    Building2,
    CalendarCheck,
    CalendarClock,
    CalendarRange,
    ClipboardList,
    Clock,
    DoorOpen,
    FileSpreadsheet,
    FolderGit2,
    GraduationCap,
    LayoutGrid,
    Link2,
    UserRound,
    Users,
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
import { index as corridas } from '@/routes/corridas';
import { index as cursos } from '@/routes/cursos';
import { index as espacios } from '@/routes/espacios';
import { inicio } from '@/routes/escuela';
import { index as excel } from '@/routes/importar';
import { index as grupos } from '@/routes/grupos';
import { index as materias } from '@/routes/materias';
import { index as planes } from '@/routes/planes';
import { index as planteles } from '@/routes/planteles';
import { index as plantillas } from '@/routes/plantillas';
import { index as profesores } from '@/routes/profesores';
import { index as sincronias } from '@/routes/sincronias';
import type { NavItem } from '@/types';

const page = usePage();

const secciones = computed<{ label: string; items: NavItem[] }[]>(() =>
    page.props.rol === 'admin'
        ? [
              {
                  label: 'Escuela',
                  items: [
                      { title: 'Inicio', href: inicio(), icon: LayoutGrid },
                      { title: 'Ciclos', href: ciclos(), icon: CalendarRange },
                      {
                          title: 'Planteles',
                          href: planteles(),
                          icon: Building2,
                      },
                      { title: 'Espacios', href: espacios(), icon: DoorOpen },
                      { title: 'Plantillas', href: plantillas(), icon: Clock },
                      { title: 'Excel', href: excel(), icon: FileSpreadsheet },
                  ],
              },
              {
                  label: 'Académico',
                  items: [
                      { title: 'Planes', href: planes(), icon: GraduationCap },
                      { title: 'Materias', href: materias(), icon: BookOpen },
                      {
                          title: 'Profesores',
                          href: profesores(),
                          icon: UserRound,
                      },
                  ],
              },
              {
                  label: 'Ciclo actual',
                  items: [
                      { title: 'Grupos', href: grupos(), icon: Users },
                      {
                          title: 'Carga académica',
                          href: cursos(),
                          icon: ClipboardList,
                      },
                      { title: 'Sincronías', href: sincronias(), icon: Link2 },
                      {
                          title: 'Horarios',
                          href: corridas(),
                          icon: CalendarClock,
                      },
                  ],
              },
          ]
        : [
              {
                  label: 'Profesor',
                  items: [
                      {
                          title: 'Mi disponibilidad',
                          href: miDisponibilidad(),
                          icon: CalendarCheck,
                      },
                  ],
              },
          ],
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
            <NavMain
                v-for="seccion in secciones"
                :key="seccion.label"
                :label="seccion.label"
                :items="seccion.items"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
