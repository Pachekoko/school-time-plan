<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import SincroniaController from '@/actions/App/Http/Controllers/Escuela/SincroniaController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/sincronias';

type Sincronia = {
    id: number;
    clave: string;
    nombre: string;
};

defineProps<{
    sincronia: Sincronia | null;
    ciclo: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Sincronías', href: index() }],
    },
});
</script>

<template>
    <Head :title="sincronia ? 'Editar sincronía' : 'Nueva sincronía'" />

    <div class="flex max-w-xl flex-col gap-6 p-4">
        <Heading
            :title="
                sincronia ? `Editar «${sincronia.nombre}»` : 'Nueva sincronía'
            "
            :description="`Ciclo ${ciclo}.`"
        />

        <Form
            v-bind="
                sincronia
                    ? SincroniaController.update.form(sincronia.id)
                    : SincroniaController.store.form()
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
                    :default-value="sincronia?.clave"
                    placeholder="ING-1"
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
                    :default-value="sincronia?.nombre"
                    placeholder="Inglés nivel 1"
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
