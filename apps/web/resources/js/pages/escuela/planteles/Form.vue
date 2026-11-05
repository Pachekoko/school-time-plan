<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import PlantelController from '@/actions/App/Http/Controllers/Escuela/PlantelController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/planteles';

type Plantel = {
    id: number;
    clave: string;
    nombre: string;
    direccion: string | null;
};

defineProps<{
    plantel: Plantel | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Planteles', href: index() }],
    },
});
</script>

<template>
    <Head :title="plantel ? 'Editar plantel' : 'Nuevo plantel'" />

    <div class="flex max-w-xl flex-col gap-6 p-4">
        <Heading
            :title="plantel ? `Editar «${plantel.nombre}»` : 'Nuevo plantel'"
            description="La clave es corta y única en tu escuela; con ella se cruzan los archivos de Excel."
        />

        <Form
            v-bind="
                plantel
                    ? PlantelController.update.form(plantel.id)
                    : PlantelController.store.form()
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
                    :default-value="plantel?.clave"
                    placeholder="CEN"
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
                    :default-value="plantel?.nombre"
                    placeholder="Plantel Centro"
                />
                <InputError :message="errors.nombre" />
            </div>

            <div class="grid gap-2">
                <Label for="direccion">
                    Dirección
                    <span class="font-normal text-muted-foreground"
                        >(opcional)</span
                    >
                </Label>
                <Input
                    id="direccion"
                    name="direccion"
                    maxlength="255"
                    :default-value="plantel?.direccion ?? ''"
                />
                <InputError :message="errors.direccion" />
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
