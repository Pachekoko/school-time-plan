<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Pencil, Plus } from '@lucide/vue';
import SincroniaController from '@/actions/App/Http/Controllers/Escuela/SincroniaController';
import BotonBorrar from '@/components/BotonBorrar.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/sincronias';

type Sincronia = {
    id: number;
    clave: string;
    nombre: string;
    cursos: string[];
};

defineProps<{
    ciclo: string;
    sincronias: Sincronia[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Sincronías', href: index() }],
    },
});
</script>

<template>
    <Head title="Sincronías" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Sincronías"
                :description="`Cursos del ciclo ${ciclo} que van a la misma hora, como los niveles de Inglés. Los cursos se asignan desde la carga académica.`"
            />
            <Button as-child>
                <Link :href="SincroniaController.create()">
                    <Plus />
                    Nueva sincronía
                </Link>
            </Button>
        </div>

        <p
            v-if="sincronias.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Aún no hay sincronías en el ciclo {{ ciclo }}.
        </p>

        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 font-medium">Clave</th>
                        <th class="px-4 py-2 font-medium">Nombre</th>
                        <th class="px-4 py-2 font-medium">Cursos</th>
                        <th class="px-4 py-2">
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="sincronia in sincronias"
                        :key="sincronia.id"
                        class="border-t hover:bg-muted/30"
                    >
                        <td class="px-4 py-2 font-mono">
                            {{ sincronia.clave }}
                        </td>
                        <td class="px-4 py-2">{{ sincronia.nombre }}</td>
                        <td class="px-4 py-2">
                            <span
                                v-if="sincronia.cursos.length === 0"
                                class="text-muted-foreground"
                            >
                                Sin cursos
                            </span>
                            <div v-else class="flex flex-wrap gap-1">
                                <Badge
                                    v-for="curso in sincronia.cursos"
                                    :key="curso"
                                    variant="secondary"
                                    class="font-mono"
                                >
                                    {{ curso }}
                                </Badge>
                            </div>
                        </td>
                        <td class="px-4 py-1">
                            <div class="flex justify-end gap-1">
                                <Button variant="ghost" size="icon-sm" as-child>
                                    <Link
                                        :href="
                                            SincroniaController.edit(
                                                sincronia.id,
                                            )
                                        "
                                        :aria-label="`Editar ${sincronia.nombre}`"
                                    >
                                        <Pencil />
                                    </Link>
                                </Button>
                                <BotonBorrar
                                    :nombre="sincronia.nombre"
                                    :accion="
                                        SincroniaController.destroy(
                                            sincronia.id,
                                        )
                                    "
                                />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
