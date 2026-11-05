<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import CicloController from '@/actions/App/Http/Controllers/Escuela/CicloController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SelectNativo from '@/components/SelectNativo.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/ciclos';

type Ciclo = {
    id: number;
    clave: string;
    inicio: string | null;
    fin: string | null;
    estado: string;
};

defineProps<{
    ciclo: Ciclo | null;
    estados: { valor: string; etiqueta: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Ciclos', href: index() }],
    },
});
</script>

<template>
    <Head :title="ciclo ? 'Editar ciclo' : 'Nuevo ciclo'" />

    <div class="flex max-w-xl flex-col gap-6 p-4">
        <Heading
            :title="ciclo ? `Editar «${ciclo.clave}»` : 'Nuevo ciclo'"
            description="Con el ciclo cerrado, los profesores ya no pueden cambiar su disponibilidad."
        />

        <Form
            v-bind="
                ciclo
                    ? CicloController.update.form(ciclo.id)
                    : CicloController.store.form()
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
                    :default-value="ciclo?.clave"
                    placeholder="2027-A"
                />
                <InputError :message="errors.clave" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="inicio">
                        Inicio
                        <span class="font-normal text-muted-foreground"
                            >(opcional)</span
                        >
                    </Label>
                    <Input
                        id="inicio"
                        name="inicio"
                        type="date"
                        :default-value="ciclo?.inicio ?? ''"
                    />
                    <InputError :message="errors.inicio" />
                </div>

                <div class="grid gap-2">
                    <Label for="fin">
                        Fin
                        <span class="font-normal text-muted-foreground"
                            >(opcional)</span
                        >
                    </Label>
                    <Input
                        id="fin"
                        name="fin"
                        type="date"
                        :default-value="ciclo?.fin ?? ''"
                    />
                    <InputError :message="errors.fin" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="estado">Estado</Label>
                <SelectNativo id="estado" name="estado" required>
                    <option
                        v-for="estado in estados"
                        :key="estado.valor"
                        :value="estado.valor"
                        :selected="
                            (ciclo?.estado ?? 'captura') === estado.valor
                        "
                    >
                        {{ estado.etiqueta }}
                    </option>
                </SelectNativo>
                <InputError :message="errors.estado" />
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
