<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import CorridaController from '@/actions/App/Http/Controllers/Escuela/CorridaController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/corridas';

defineProps<{
    ciclo: string;
    parametros: { tiempo_limite_s: number; permitir_parcial: boolean };
    hayActiva: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Horarios', href: index() }],
    },
});
</script>

<template>
    <Head title="Generar horario" />

    <div class="flex max-w-xl flex-col gap-6 p-4">
        <Heading
            title="Generar horario"
            :description="`El motor acomoda la carga académica del ciclo ${ciclo}.`"
        />

        <Form
            v-bind="CorridaController.store.form()"
            class="flex flex-col gap-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="tiempo_limite_s">Tiempo límite (segundos)</Label>
                <Input
                    id="tiempo_limite_s"
                    name="tiempo_limite_s"
                    type="number"
                    min="1"
                    max="300"
                    class="max-w-32"
                    :default-value="parametros.tiempo_limite_s"
                />
                <InputError :message="errors.tiempo_limite_s" />
            </div>

            <label class="flex items-start gap-2 text-sm">
                <input
                    type="checkbox"
                    name="permitir_parcial"
                    value="1"
                    :checked="parametros.permitir_parcial"
                    class="mt-0.5 size-4 accent-primary"
                />
                <span>
                    Permitir horario parcial
                    <span class="block text-muted-foreground">
                        Si no cabe todo, acomoda lo que pueda y marca lo que
                        falta.
                    </span>
                </span>
            </label>

            <InputError :message="errors.corrida" />

            <div class="flex items-center gap-2">
                <Button type="submit" :disabled="processing || hayActiva"
                    >Generar</Button
                >
                <Button variant="ghost" as-child>
                    <Link :href="index()">Cancelar</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
