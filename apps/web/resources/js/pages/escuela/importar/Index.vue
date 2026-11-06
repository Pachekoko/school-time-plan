<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { FileDown, FileSpreadsheet, Upload } from '@lucide/vue';
import { ref } from 'vue';
import ImportacionController from '@/actions/App/Http/Controllers/Escuela/ImportacionController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SelectNativo from '@/components/SelectNativo.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { datos, plantilla } from '@/routes/exportar';
import { index } from '@/routes/importar';

type Opcion = { id: number; nombre: string };

const props = defineProps<{
    ciclo: string;
    formatos: { valor: string; etiqueta: string }[];
    planteles: Opcion[];
    plantillas: Opcion[];
    planes: (Opcion & { clave: string })[];
}>();

const formato = ref(props.formatos[0]?.valor ?? 'plantilla');

// Para la carga de UTEG: dónde van los grupos que trae.
const opcionesUteg = [
    { nombre: 'plantel_id', etiqueta: 'Plantel', lista: 'planteles' },
    {
        nombre: 'plantilla_1y2_id',
        etiqueta: 'Plantilla de 1.º y 2.º semestre',
        lista: 'plantillas',
    },
    {
        nombre: 'plantilla_3a6_id',
        etiqueta: 'Plantilla de 3.º a 6.º semestre',
        lista: 'plantillas',
    },
    { nombre: 'plan_id', etiqueta: 'Plan de estudios', lista: 'planes' },
] as const;

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Excel', href: index() }],
    },
});
</script>

<template>
    <Head title="Excel" />

    <div class="flex max-w-3xl flex-col gap-6 p-4">
        <Heading
            title="Excel"
            description="Captura en Excel lo que ya tienes y súbelo; antes de aplicar ves qué se crea y qué cambia."
        />

        <section class="flex flex-col gap-3 rounded-lg border p-4">
            <h3 class="font-medium">1. Baja la plantilla</h3>
            <p class="text-sm text-muted-foreground">
                Trae una hoja de instrucciones y listas desplegables. «Mis
                datos» la llena con lo que ya capturaste, para editarlo en Excel
                y volver a subirlo.
            </p>
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" as-child>
                    <a :href="plantilla.url()" download>
                        <FileSpreadsheet />
                        Plantilla vacía
                    </a>
                </Button>
                <Button variant="outline" as-child>
                    <a :href="datos.url()" download>
                        <FileDown />
                        Mis datos en Excel
                    </a>
                </Button>
            </div>
        </section>

        <section class="flex flex-col gap-3 rounded-lg border p-4">
            <h3 class="font-medium">2. Súbela</h3>
            <p class="text-sm text-muted-foreground">
                Los grupos y la carga se importan al ciclo {{ ciclo }}. Importar
                nunca borra: lo que no venga en el archivo se queda como está.
            </p>

            <Form
                v-bind="ImportacionController.store.form()"
                class="flex flex-col gap-4"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="archivo">Archivo .xlsx (máximo 5 MB)</Label>
                    <input
                        id="archivo"
                        name="archivo"
                        type="file"
                        required
                        accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                        class="text-sm file:mr-3 file:rounded-md file:border file:bg-background file:px-3 file:py-1.5 file:text-sm"
                    />
                    <InputError :message="errors.archivo" />
                </div>

                <div class="grid gap-2">
                    <Label for="formato">Formato</Label>
                    <SelectNativo
                        id="formato"
                        name="formato"
                        class="max-w-sm"
                        @change="
                            formato = ($event.target as HTMLSelectElement).value
                        "
                    >
                        <option
                            v-for="opcion in formatos"
                            :key="opcion.valor"
                            :value="opcion.valor"
                            :selected="opcion.valor === formato"
                        >
                            {{ opcion.etiqueta }}
                        </option>
                    </SelectNativo>
                    <InputError :message="errors.formato" />
                </div>

                <fieldset
                    v-if="formato === 'uteg'"
                    class="grid gap-4 rounded-lg border p-3 sm:grid-cols-2"
                >
                    <legend class="px-1 text-sm font-medium">
                        Dónde van los grupos de la carga
                    </legend>
                    <div
                        v-for="opcion in opcionesUteg"
                        :key="opcion.nombre"
                        class="grid gap-2"
                    >
                        <Label :for="opcion.nombre">{{
                            opcion.etiqueta
                        }}</Label>
                        <SelectNativo
                            :id="opcion.nombre"
                            :name="opcion.nombre"
                            required
                        >
                            <option value="" disabled selected>Elige</option>
                            <option
                                v-for="elemento in props[opcion.lista]"
                                :key="elemento.id"
                                :value="elemento.id"
                            >
                                {{ elemento.nombre }}
                            </option>
                        </SelectNativo>
                        <InputError :message="errors[opcion.nombre]" />
                    </div>
                    <p class="text-sm text-muted-foreground sm:col-span-2">
                        Los grupos de 1.º y 2.º usan la primera plantilla y el
                        resto la segunda; los 09… cuentan como 6.º. GENERICO en
                        Docente es un curso vacante.
                    </p>
                </fieldset>

                <div>
                    <Button type="submit" :disabled="processing">
                        <Upload />
                        Revisar archivo
                    </Button>
                </div>
            </Form>
        </section>
    </div>
</template>
