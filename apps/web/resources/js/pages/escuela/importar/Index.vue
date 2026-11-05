<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { FileDown, FileSpreadsheet, Upload } from '@lucide/vue';
import ImportacionController from '@/actions/App/Http/Controllers/Escuela/ImportacionController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SelectNativo from '@/components/SelectNativo.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { datos, plantilla } from '@/routes/exportar';
import { index } from '@/routes/importar';

defineProps<{
    ciclo: string;
    formatos: { valor: string; etiqueta: string }[];
}>();

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
                    <SelectNativo id="formato" name="formato" class="max-w-xs">
                        <option
                            v-for="(formato, i) in formatos"
                            :key="formato.valor"
                            :value="formato.valor"
                            :selected="i === 0"
                        >
                            {{ formato.etiqueta }}
                        </option>
                    </SelectNativo>
                    <InputError :message="errors.formato" />
                </div>

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
