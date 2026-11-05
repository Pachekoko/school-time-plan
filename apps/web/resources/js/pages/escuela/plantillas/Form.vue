<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import PlantillaController from '@/actions/App/Http/Controllers/Escuela/PlantillaController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/plantillas';

type Plantilla = {
    id: number;
    clave: string;
    nombre: string;
};

defineProps<{
    plantilla: Plantilla | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Plantillas', href: index() }],
    },
});
</script>

<template>
    <Head :title="plantilla ? 'Editar plantilla' : 'Nueva plantilla'" />

    <div class="flex max-w-xl flex-col gap-6 p-4">
        <Heading
            :title="
                plantilla ? `Editar «${plantilla.nombre}»` : 'Nueva plantilla'
            "
            description="Después de crearla, generas sus franjas de la semana."
        />

        <Form
            v-bind="
                plantilla
                    ? PlantillaController.update.form(plantilla.id)
                    : PlantillaController.store.form()
            "
            class="flex flex-col gap-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="clave">Clave</Label>
                <Input
                    id="clave"
                    name="clave"
                    required
                    maxlength="30"
                    :default-value="plantilla?.clave"
                    placeholder="MAT"
                />
                <InputError :message="errors.clave" />
            </div>

            <div class="grid gap-2">
                <Label for="nombre">Nombre</Label>
                <Input
                    id="nombre"
                    name="nombre"
                    required
                    maxlength="120"
                    :default-value="plantilla?.nombre"
                    placeholder="Matutino"
                />
                <InputError :message="errors.nombre" />
            </div>

            <div class="flex items-center gap-2">
                <Button type="submit" :disabled="processing">Guardar</Button>
                <Button variant="ghost" as-child>
                    <Link :href="index()">Cancelar</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
