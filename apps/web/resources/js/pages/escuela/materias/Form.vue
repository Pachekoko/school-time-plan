<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MateriaController from '@/actions/App/Http/Controllers/Escuela/MateriaController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SelectNativo from '@/components/SelectNativo.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { derivarPatrones, formatearPatrones } from '@/lib/patrones';
import { index } from '@/routes/materias';

type Materia = {
    id: number;
    plan_id: number;
    clave: string;
    nombre: string;
    semestre: number;
    horas_semana: number;
    patrones: string;
    tipo_espacio: string;
};

const props = defineProps<{
    materia: Materia | null;
    planes: { id: number; clave: string; nombre: string }[];
    tipos: { valor: string; etiqueta: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Materias', href: index() }],
    },
});

const horas = ref<string | number>(props.materia?.horas_semana ?? 3);
const patrones = ref<string | number>(props.materia?.patrones ?? '');

// El patrón que se usará si el campo queda vacío.
const derivado = computed(() => {
    const n = Number(horas.value);

    return Number.isInteger(n) && n >= 1 && n <= 40
        ? formatearPatrones(derivarPatrones(n))
        : '';
});
</script>

<template>
    <Head :title="materia ? 'Editar materia' : 'Nueva materia'" />

    <div class="flex max-w-xl flex-col gap-6 p-4">
        <Heading
            :title="materia ? `Editar «${materia.nombre}»` : 'Nueva materia'"
        />

        <Form
            v-bind="
                materia
                    ? MateriaController.update.form(materia.id)
                    : MateriaController.store.form()
            "
            class="flex flex-col gap-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="plan_id">Plan de estudios</Label>
                <SelectNativo id="plan_id" name="plan_id" required>
                    <option value="" disabled :selected="!materia">
                        Elige un plan
                    </option>
                    <option
                        v-for="plan in planes"
                        :key="plan.id"
                        :value="plan.id"
                        :selected="materia?.plan_id === plan.id"
                    >
                        {{ plan.clave }} · {{ plan.nombre }}
                    </option>
                </SelectNativo>
                <InputError :message="errors.plan_id" />
            </div>

            <div class="grid gap-4 sm:grid-cols-[1fr_2fr]">
                <div class="grid gap-2">
                    <Label for="clave">Clave</Label>
                    <Input
                        id="clave"
                        name="clave"
                        required
                        maxlength="30"
                        :default-value="materia?.clave"
                        placeholder="MAT1"
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
                        :default-value="materia?.nombre"
                        placeholder="Matemáticas I"
                    />
                    <InputError :message="errors.nombre" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="grid gap-2">
                    <Label for="semestre">Semestre</Label>
                    <Input
                        id="semestre"
                        name="semestre"
                        type="number"
                        required
                        min="1"
                        max="12"
                        :default-value="materia?.semestre ?? 1"
                    />
                    <InputError :message="errors.semestre" />
                </div>
                <div class="grid gap-2">
                    <Label for="horas_semana">Horas por semana</Label>
                    <Input
                        id="horas_semana"
                        v-model="horas"
                        name="horas_semana"
                        type="number"
                        required
                        min="1"
                        max="40"
                    />
                    <InputError :message="errors.horas_semana" />
                </div>
                <div class="grid gap-2">
                    <Label for="tipo_espacio">Espacio</Label>
                    <SelectNativo
                        id="tipo_espacio"
                        name="tipo_espacio"
                        required
                    >
                        <option
                            v-for="tipo in tipos"
                            :key="tipo.valor"
                            :value="tipo.valor"
                            :selected="
                                (materia?.tipo_espacio ?? 'aula') === tipo.valor
                            "
                        >
                            {{ tipo.etiqueta }}
                        </option>
                    </SelectNativo>
                    <InputError :message="errors.tipo_espacio" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="patrones">
                    Bloques
                    <span class="font-normal text-muted-foreground"
                        >(opcional)</span
                    >
                </Label>
                <Input
                    id="patrones"
                    v-model="patrones"
                    name="patrones"
                    maxlength="100"
                    class="font-mono"
                    :placeholder="derivado"
                />
                <p
                    v-if="String(patrones).trim() === '' && derivado"
                    class="text-sm text-muted-foreground"
                >
                    Vacío: se usará
                    <span class="font-mono text-foreground">{{ derivado }}</span
                    >, según las horas.
                </p>
                <p v-else class="text-sm text-muted-foreground">
                    Bloques con + y alternativas con |, por ejemplo
                    <span class="font-mono">2 | 1+1</span>.
                </p>
                <InputError :message="errors.patrones" />
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
