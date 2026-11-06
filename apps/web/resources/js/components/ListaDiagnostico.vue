<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';

type Entidad = { tipo: string; id: string };

type Diagnostico = {
    codigo: string;
    regla: string;
    entidades: Entidad[];
    mensaje: string;
};

defineProps<{
    diagnostico: Diagnostico[];
    // «tipo:id» → nombre y enlace; null si ya no existe.
    entidades: Record<string, { nombre: string | null; url: string | null }>;
}>();

const TIPOS: Record<string, string> = {
    grupo: 'Grupo',
    profesor: 'Profesor',
    curso: 'Curso',
    plantel: 'Plantel',
    espacio: 'Espacio',
    sincronia: 'Sincronía',
    sesion: 'Sesión',
    materia: 'Materia',
    plantilla: 'Plantilla',
};
</script>

<template>
    <ul class="flex flex-col gap-3">
        <li
            v-for="(item, i) in diagnostico"
            :key="i"
            class="flex gap-3 rounded-lg border border-amber-500/40 bg-amber-50 p-3 text-sm dark:bg-amber-950/30"
        >
            <TriangleAlert
                class="mt-0.5 size-4 shrink-0 text-amber-600"
                aria-hidden="true"
            />
            <div class="flex flex-col gap-2">
                <p>{{ item.mensaje }}</p>
                <div
                    v-if="item.entidades.length > 0"
                    class="flex flex-wrap gap-1.5"
                >
                    <template
                        v-for="entidad in item.entidades"
                        :key="`${entidad.tipo}:${entidad.id}`"
                    >
                        <Link
                            v-if="
                                entidades[`${entidad.tipo}:${entidad.id}`]?.url
                            "
                            :href="
                                entidades[`${entidad.tipo}:${entidad.id}`]
                                    .url ?? ''
                            "
                            class="rounded-md border bg-background px-2 py-0.5 text-xs hover:bg-muted"
                        >
                            {{ TIPOS[entidad.tipo] ?? entidad.tipo }}
                            {{
                                entidades[`${entidad.tipo}:${entidad.id}`]
                                    .nombre
                            }}
                        </Link>
                        <span
                            v-else
                            class="rounded-md border border-dashed px-2 py-0.5 font-mono text-xs text-muted-foreground"
                            :title="
                                entidad.tipo === 'sesion'
                                    ? undefined
                                    : 'Ya no existe'
                            "
                        >
                            {{ TIPOS[entidad.tipo] ?? entidad.tipo }}
                            {{ entidad.id }}
                        </span>
                    </template>
                </div>
            </div>
        </li>
    </ul>
</template>
