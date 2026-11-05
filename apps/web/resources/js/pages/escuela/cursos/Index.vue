<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Pencil, Plus } from '@lucide/vue';
import CursoController from '@/actions/App/Http/Controllers/Escuela/CursoController';
import AlertError from '@/components/AlertError.vue';
import BotonBorrar from '@/components/BotonBorrar.vue';
import Heading from '@/components/Heading.vue';
import SelectNativo from '@/components/SelectNativo.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/cursos';

type Curso = {
    id: number;
    clave: string;
    materia: string;
    grupos: string;
    profesor: string | null;
    vacante: boolean;
    sincronia: string | null;
    alumnos: number | null;
};

type Filtros = { grupo: number | null; materia: number | null };

const props = defineProps<{
    ciclo: string;
    filtros: Filtros;
    grupos: { id: number; clave: string }[];
    materias: { id: number; clave: string; nombre: string }[];
    cursos: Curso[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Carga académica', href: index() }],
    },
});

const page = usePage();

function filtrar(campo: keyof Filtros, valor: string): void {
    const filtros: Filtros = {
        ...props.filtros,
        [campo]: valor === '' ? null : Number(valor),
    };
    const consulta = Object.fromEntries(
        Object.entries(filtros).filter(([, v]) => v !== null),
    );

    router.get(index.url(), consulta, { preserveScroll: true, replace: true });
}
</script>

<template>
    <Head title="Carga académica" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Carga académica"
                :description="`Los cursos del ciclo ${ciclo}: qué materia se da a qué grupos y con qué profesor. Sin profesor, lo elige el motor.`"
            />
            <Button as-child>
                <Link :href="CursoController.create()">
                    <Plus />
                    Nuevo curso
                </Link>
            </Button>
        </div>

        <div class="flex flex-wrap gap-3">
            <SelectNativo
                class="w-48"
                aria-label="Filtrar por grupo"
                @change="
                    filtrar('grupo', ($event.target as HTMLSelectElement).value)
                "
            >
                <option value="" :selected="filtros.grupo === null">
                    Todos los grupos
                </option>
                <option
                    v-for="grupo in grupos"
                    :key="grupo.id"
                    :value="grupo.id"
                    :selected="filtros.grupo === grupo.id"
                >
                    {{ grupo.clave }}
                </option>
            </SelectNativo>
            <SelectNativo
                class="w-64"
                aria-label="Filtrar por materia"
                @change="
                    filtrar(
                        'materia',
                        ($event.target as HTMLSelectElement).value,
                    )
                "
            >
                <option value="" :selected="filtros.materia === null">
                    Todas las materias
                </option>
                <option
                    v-for="materia in materias"
                    :key="materia.id"
                    :value="materia.id"
                    :selected="filtros.materia === materia.id"
                >
                    {{ materia.clave }} · {{ materia.nombre }}
                </option>
            </SelectNativo>
        </div>

        <AlertError
            v-if="page.props.errors.borrar"
            :errors="[page.props.errors.borrar]"
            title="No se pudo borrar"
        />

        <p
            v-if="cursos.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            {{
                filtros.grupo || filtros.materia
                    ? 'Ningún curso coincide con el filtro.'
                    : `Aún no hay cursos en el ciclo ${ciclo}.`
            }}
        </p>

        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 font-medium">Clave</th>
                        <th class="px-4 py-2 font-medium">Materia</th>
                        <th class="px-4 py-2 font-medium">Grupos</th>
                        <th class="px-4 py-2 font-medium">Profesor</th>
                        <th class="px-4 py-2 font-medium">Sincronía</th>
                        <th class="px-4 py-2 text-right font-medium">
                            Alumnos
                        </th>
                        <th class="px-4 py-2">
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="curso in cursos"
                        :key="curso.id"
                        class="border-t hover:bg-muted/30"
                    >
                        <td class="px-4 py-2 font-mono">{{ curso.clave }}</td>
                        <td class="px-4 py-2">{{ curso.materia }}</td>
                        <td class="px-4 py-2 font-mono">{{ curso.grupos }}</td>
                        <td class="px-4 py-2">
                            <Badge v-if="curso.vacante" variant="outline"
                                >Vacante</Badge
                            >
                            <span v-else-if="curso.profesor">{{
                                curso.profesor
                            }}</span>
                            <span v-else class="text-muted-foreground"
                                >Por asignar</span
                            >
                        </td>
                        <td class="px-4 py-2 font-mono text-muted-foreground">
                            {{ curso.sincronia ?? '—' }}
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ curso.alumnos ?? '—' }}
                        </td>
                        <td class="px-4 py-1">
                            <div class="flex justify-end gap-1">
                                <Button variant="ghost" size="icon-sm" as-child>
                                    <Link
                                        :href="CursoController.edit(curso.id)"
                                        :aria-label="`Editar ${curso.clave}`"
                                    >
                                        <Pencil />
                                    </Link>
                                </Button>
                                <BotonBorrar
                                    :nombre="curso.clave"
                                    :accion="CursoController.destroy(curso.id)"
                                />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
