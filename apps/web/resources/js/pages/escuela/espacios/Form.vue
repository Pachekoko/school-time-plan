<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import EspacioController from '@/actions/App/Http/Controllers/Escuela/EspacioController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SelectNativo from '@/components/SelectNativo.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/espacios';

type Espacio = {
    id: number;
    plantel_id: number;
    clave: string;
    nombre: string;
    tipo: string;
    capacidad: number;
};

defineProps<{
    espacio: Espacio | null;
    planteles: { id: number; nombre: string }[];
    tipos: { valor: string; etiqueta: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Espacios', href: index() }],
    },
});
</script>

<template>
    <Head :title="espacio ? 'Editar espacio' : 'Nuevo espacio'" />

    <div class="flex max-w-xl flex-col gap-6 p-4">
        <Heading
            :title="espacio ? `Editar «${espacio.nombre}»` : 'Nuevo espacio'"
            description="El tipo decide qué materias pueden usarlo; la capacidad, qué grupos caben."
        />

        <Form
            v-bind="
                espacio
                    ? EspacioController.update.form(espacio.id)
                    : EspacioController.store.form()
            "
            class="flex flex-col gap-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="plantel_id">Plantel</Label>
                <SelectNativo id="plantel_id" name="plantel_id" required>
                    <option value="" disabled :selected="!espacio">
                        Elige un plantel
                    </option>
                    <option
                        v-for="plantel in planteles"
                        :key="plantel.id"
                        :value="plantel.id"
                        :selected="espacio?.plantel_id === plantel.id"
                    >
                        {{ plantel.nombre }}
                    </option>
                </SelectNativo>
                <InputError :message="errors.plantel_id" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="clave">Clave</Label>
                    <Input
                        id="clave"
                        name="clave"
                        required
                        maxlength="30"
                        :default-value="espacio?.clave"
                        placeholder="A-101"
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
                        :default-value="espacio?.nombre"
                        placeholder="Aula 101"
                    />
                    <InputError :message="errors.nombre" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="tipo">Tipo</Label>
                    <SelectNativo id="tipo" name="tipo" required>
                        <option
                            v-for="tipo in tipos"
                            :key="tipo.valor"
                            :value="tipo.valor"
                            :selected="(espacio?.tipo ?? 'aula') === tipo.valor"
                        >
                            {{ tipo.etiqueta }}
                        </option>
                    </SelectNativo>
                    <InputError :message="errors.tipo" />
                </div>

                <div class="grid gap-2">
                    <Label for="capacidad">Capacidad (alumnos)</Label>
                    <Input
                        id="capacidad"
                        name="capacidad"
                        type="number"
                        required
                        min="1"
                        max="10000"
                        :default-value="espacio?.capacidad ?? 40"
                    />
                    <InputError :message="errors.capacidad" />
                </div>
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
