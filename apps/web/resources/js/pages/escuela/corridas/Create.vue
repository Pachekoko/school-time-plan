<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import CorridaController from '@/actions/App/Http/Controllers/Escuela/CorridaController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SelectNativo from '@/components/SelectNativo.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/corridas';

type Regla = {
    clave: string;
    nombre: string;
    modo: string;
    peso: number;
    modos: string[];
};

const props = defineProps<{
    ciclo: string;
    parametros: { tiempo_limite_s: number; permitir_parcial: boolean };
    reglas: Regla[];
    hayActiva: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Horarios', href: index() }],
    },
});

const MODOS: Record<string, string> = {
    apagada: 'Apagada',
    preferencia: 'Preferencia',
    obligatoria: 'Obligatoria',
};

// El peso solo cuenta para las reglas en modo preferencia.
const modos = ref<Record<string, string>>(
    Object.fromEntries(props.reglas.map((r) => [r.clave, r.modo])),
);
</script>

<template>
    <Head title="Generar horario" />

    <div class="flex max-w-3xl flex-col gap-6 p-4">
        <Heading
            title="Generar horario"
            :description="`El motor acomoda la carga académica del ciclo ${ciclo} respetando la disponibilidad y las reglas.`"
        />

        <p
            v-if="hayActiva"
            class="rounded-lg border border-amber-500/40 bg-amber-50 p-3 text-sm dark:bg-amber-950/30"
        >
            Ya hay una corrida en curso para este ciclo. Espera a que termine.
        </p>

        <Form
            v-bind="CorridaController.store.form()"
            class="flex flex-col gap-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="tiempo_limite_s"
                        >Tiempo límite (segundos)</Label
                    >
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
                <label class="flex items-start gap-2 self-end pb-2 text-sm">
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
            </div>

            <fieldset class="grid gap-2">
                <legend class="mb-2 text-sm font-medium">Reglas</legend>
                <p class="text-sm text-muted-foreground">
                    Obligatoria: el horario la cumple siempre. Preferencia: la
                    cumple si puede; a mayor peso, más importa.
                </p>
                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full text-sm">
                        <thead
                            class="bg-muted/50 text-left text-muted-foreground"
                        >
                            <tr>
                                <th class="px-3 py-2 font-medium">Regla</th>
                                <th class="px-3 py-2 font-medium">Modo</th>
                                <th class="px-3 py-2 font-medium">Peso</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="regla in reglas"
                                :key="regla.clave"
                                class="border-t"
                            >
                                <td class="px-3 py-2">
                                    {{ regla.nombre }}
                                    <InputError
                                        :message="
                                            errors[`reglas.${regla.clave}.modo`]
                                        "
                                    />
                                </td>
                                <td class="px-3 py-2">
                                    <SelectNativo
                                        :name="`reglas[${regla.clave}][modo]`"
                                        class="w-40"
                                        :aria-label="`Modo de «${regla.nombre}»`"
                                        @change="
                                            modos[regla.clave] = (
                                                $event.target as HTMLSelectElement
                                            ).value
                                        "
                                    >
                                        <option
                                            v-for="modo in regla.modos"
                                            :key="modo"
                                            :value="modo"
                                            :selected="regla.modo === modo"
                                        >
                                            {{ MODOS[modo] }}
                                        </option>
                                    </SelectNativo>
                                </td>
                                <td class="px-3 py-2">
                                    <Input
                                        :name="`reglas[${regla.clave}][peso]`"
                                        type="number"
                                        min="1"
                                        max="10"
                                        class="w-20"
                                        :default-value="regla.peso"
                                        :disabled="
                                            modos[regla.clave] !== 'preferencia'
                                        "
                                        :aria-label="`Peso de «${regla.nombre}»`"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </fieldset>

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
