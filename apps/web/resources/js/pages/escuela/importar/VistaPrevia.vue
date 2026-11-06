<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { CircleAlert, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import ImportacionController from '@/actions/App/Http/Controllers/Escuela/ImportacionController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/importar';

type Conteo = { crear: number; actualizar: number; sin_cambios: number };

const props = defineProps<{
    uuid: string;
    archivo: string | null;
    formato: string;
    opciones: Record<string, number> | null;
    conteos: Record<string, Conteo>;
    errores: { hoja: string; fila: number; columna: string; mensaje: string }[];
    avisos: { hoja: string; fila: number | null; mensaje: string }[];
    tieneErrores: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Excel', href: index() }],
    },
});

const totales = computed(() =>
    Object.values(props.conteos).reduce(
        (suma, c) => ({
            crear: suma.crear + c.crear,
            actualizar: suma.actualizar + c.actualizar,
        }),
        { crear: 0, actualizar: 0 },
    ),
);
</script>

<template>
    <Head title="Vista previa de la importación" />

    <div class="flex max-w-4xl flex-col gap-6 p-4">
        <Heading
            title="Vista previa de la importación"
            :description="archivo ? `Archivo: ${archivo}` : undefined"
        />

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 font-medium">Hoja</th>
                        <th class="px-4 py-2 text-right font-medium">Nuevos</th>
                        <th class="px-4 py-2 text-right font-medium">
                            Cambian
                        </th>
                        <th class="px-4 py-2 text-right font-medium">
                            Sin cambios
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(conteo, hoja) in conteos"
                        :key="hoja"
                        class="border-t"
                    >
                        <td class="px-4 py-2">{{ hoja }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ conteo.crear }}
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ conteo.actualizar }}
                        </td>
                        <td
                            class="px-4 py-2 text-right text-muted-foreground tabular-nums"
                        >
                            {{ conteo.sin_cambios }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <section v-if="errores.length > 0" class="flex flex-col gap-2">
            <h3 class="flex items-center gap-2 font-medium text-destructive">
                <CircleAlert class="size-4" aria-hidden="true" />
                {{
                    errores.length === 1
                        ? '1 error'
                        : `${errores.length} errores`
                }}
            </h3>
            <ul
                class="flex flex-col gap-1 rounded-lg border border-destructive/40 p-3 text-sm"
            >
                <li v-for="(error, i) in errores" :key="i">
                    <span class="font-medium"
                        >{{ error.hoja }}, fila {{ error.fila }}</span
                    >
                    <span
                        class="mx-1 rounded bg-muted px-1.5 font-mono text-xs"
                        >{{ error.columna }}</span
                    >
                    {{ error.mensaje }}
                </li>
            </ul>
        </section>

        <section v-if="avisos.length > 0" class="flex flex-col gap-2">
            <h3 class="flex items-center gap-2 font-medium">
                <TriangleAlert
                    class="size-4 text-amber-600"
                    aria-hidden="true"
                />
                Avisos
            </h3>
            <ul
                class="flex flex-col gap-1 rounded-lg border border-amber-500/40 p-3 text-sm"
            >
                <li v-for="(aviso, i) in avisos" :key="i">
                    <span class="font-medium">
                        {{ aviso.hoja
                        }}<template v-if="aviso.fila"
                            >, fila {{ aviso.fila }}</template
                        >:
                    </span>
                    {{ aviso.mensaje }}
                </li>
            </ul>
        </section>

        <Form
            v-bind="ImportacionController.confirmar.form(uuid)"
            class="flex flex-col gap-2"
            v-slot="{ errors, processing }"
        >
            <input type="hidden" name="formato" :value="formato" />
            <input
                v-for="(valor, nombre) in opciones ?? {}"
                :key="nombre"
                type="hidden"
                :name="nombre"
                :value="valor"
            />
            <InputError :message="errors.importacion" />
            <p v-if="tieneErrores" class="text-sm text-muted-foreground">
                Corrige los errores y vuelve a subir el archivo.
            </p>
            <p
                v-else-if="totales.crear + totales.actualizar === 0"
                class="text-sm text-muted-foreground"
            >
                No hay nada que cambiar.
            </p>
            <div class="flex flex-wrap items-center gap-2">
                <Button type="submit" :disabled="tieneErrores || processing">
                    Aplicar cambios
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="index()">Subir otro archivo</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
