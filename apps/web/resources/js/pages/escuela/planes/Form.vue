<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import PlanController from '@/actions/App/Http/Controllers/Escuela/PlanController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/planes';

type Plan = {
    id: number;
    clave: string;
    nombre: string;
};

defineProps<{
    plan: Plan | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Planes de estudio', href: index() }],
    },
});
</script>

<template>
    <Head :title="plan ? 'Editar plan' : 'Nuevo plan'" />

    <div class="flex max-w-xl flex-col gap-6 p-4">
        <Heading :title="plan ? `Editar «${plan.nombre}»` : 'Nuevo plan'" />

        <Form
            v-bind="
                plan
                    ? PlanController.update.form(plan.id)
                    : PlanController.store.form()
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
                    :default-value="plan?.clave"
                    placeholder="BGE-23"
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
                    :default-value="plan?.nombre"
                    placeholder="Bachillerato general 2023"
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
