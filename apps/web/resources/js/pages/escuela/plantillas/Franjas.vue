<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { computed } from 'vue';
import FranjaController from '@/actions/App/Http/Controllers/Escuela/FranjaController';
import BotonBorrar from '@/components/BotonBorrar.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SelectNativo from '@/components/SelectNativo.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { DIAS_CORTOS, DIAS_LARGOS } from '@/lib/dias';
import { index } from '@/routes/plantillas';

type Franja = {
    id: number;
    dia: number;
    orden: number;
    inicio: string;
    fin: string;
    tipo: 'clase' | 'receso';
};

const props = defineProps<{
    plantilla: { id: number; clave: string; nombre: string };
    franjas: Franja[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Plantillas', href: index() }],
    },
});

// Horas de 24 h como texto «HH:MM»: así las guarda la base y las pide el motor.
const HORA = {
    pattern: '([01][0-9]|2[0-3]):[0-5][0-9]',
    placeholder: 'HH:MM',
    maxlength: 5,
    inputmode: 'numeric',
    title: 'Hora de 24 h, por ejemplo 07:00',
} as const;

const porDia = computed(() => {
    const dias = new Map<number, Franja[]>();

    for (const franja of props.franjas) {
        dias.set(franja.dia, [...(dias.get(franja.dia) ?? []), franja]);
    }

    return [...dias.entries()].sort(([a], [b]) => a - b);
});
</script>

<template>
    <Head :title="`Franjas de ${plantilla.nombre}`" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            :title="`Franjas de «${plantilla.nombre}»`"
            description="Las horas de clase y los recesos de cada día. Los grupos con esta plantilla usan estas franjas."
        />

        <section class="rounded-lg border p-4">
            <h3 class="font-medium">Generar franjas</h3>
            <p class="mb-4 text-sm text-muted-foreground">
                Arma la semana completa. Reemplaza todas las franjas actuales de
                esta plantilla.
            </p>

            <Form
                v-bind="FranjaController.generar.form(plantilla.id)"
                class="grid gap-4"
                :options="{ preserveScroll: true }"
                v-slot="{ errors, processing }"
            >
                <fieldset class="grid gap-2">
                    <legend class="mb-2 text-sm font-medium">Días</legend>
                    <div class="flex flex-wrap gap-4">
                        <label
                            v-for="(nombre, dia) in DIAS_CORTOS.slice(0, 6)"
                            :key="dia"
                            class="flex items-center gap-1.5 text-sm"
                        >
                            <input
                                type="checkbox"
                                name="dias[]"
                                :value="dia"
                                :checked="dia < 5"
                                class="size-4 accent-primary"
                            />
                            {{ nombre }}
                        </label>
                    </div>
                    <InputError :message="errors.dias ?? errors['dias.0']" />
                </fieldset>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="inicio">Primera clase</Label>
                        <Input
                            id="inicio"
                            name="inicio"
                            v-bind="HORA"
                            required
                            default-value="08:00"
                        />
                        <InputError :message="errors.inicio" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="numero">Clases por día</Label>
                        <Input
                            id="numero"
                            name="numero"
                            type="number"
                            required
                            min="1"
                            max="20"
                            :default-value="7"
                        />
                        <InputError :message="errors.numero" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="duracion">Minutos por clase</Label>
                        <Input
                            id="duracion"
                            name="duracion"
                            type="number"
                            required
                            min="10"
                            max="240"
                            :default-value="60"
                        />
                        <InputError :message="errors.duracion" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="receso_tras">Receso tras la clase</Label>
                        <Input
                            id="receso_tras"
                            name="receso_tras"
                            type="number"
                            min="1"
                            placeholder="Sin receso"
                        />
                        <InputError :message="errors.receso_tras" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="receso_min">Minutos de receso</Label>
                        <Input
                            id="receso_min"
                            name="receso_min"
                            type="number"
                            min="5"
                            max="120"
                            :default-value="30"
                        />
                        <InputError :message="errors.receso_min" />
                    </div>
                </div>

                <InputError :message="errors.generar" />

                <div>
                    <Button
                        type="submit"
                        :disabled="processing"
                        data-guia="plantilla"
                        >Generar</Button
                    >
                </div>
            </Form>
        </section>

        <p
            v-if="franjas.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Esta plantilla aún no tiene franjas. Genéralas con el formulario de
            arriba.
        </p>

        <div v-else class="grid gap-4 xl:grid-cols-2 2xl:grid-cols-3">
            <section
                v-for="[dia, lista] in porDia"
                :key="dia"
                class="rounded-lg border"
            >
                <h3 class="border-b px-4 py-2 font-medium">
                    {{ DIAS_LARGOS[dia] }}
                </h3>
                <div class="divide-y">
                    <Form
                        v-for="franja in lista"
                        :key="franja.id"
                        v-bind="FranjaController.update.form(franja.id)"
                        :options="{ preserveScroll: true }"
                        class="grid gap-1 px-4 py-2"
                        :class="{ 'bg-muted/40': franja.tipo === 'receso' }"
                        v-slot="{ errors, processing }"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="w-6 text-right font-mono text-xs text-muted-foreground"
                                :title="`Orden ${franja.orden}`"
                            >
                                {{ franja.orden }}
                            </span>
                            <SelectNativo
                                name="tipo"
                                class="w-28"
                                :aria-label="`Tipo de ${DIAS_CORTOS[dia]} ${franja.orden}`"
                            >
                                <option
                                    value="clase"
                                    :selected="franja.tipo === 'clase'"
                                >
                                    Clase
                                </option>
                                <option
                                    value="receso"
                                    :selected="franja.tipo === 'receso'"
                                >
                                    Receso
                                </option>
                            </SelectNativo>
                            <Input
                                name="inicio"
                                v-bind="HORA"
                                required
                                class="w-20 font-mono"
                                :default-value="franja.inicio"
                                :aria-label="`Inicio de ${DIAS_CORTOS[dia]} ${franja.orden}`"
                            />
                            <span aria-hidden="true">–</span>
                            <Input
                                name="fin"
                                v-bind="HORA"
                                required
                                class="w-20 font-mono"
                                :default-value="franja.fin"
                                :aria-label="`Fin de ${DIAS_CORTOS[dia]} ${franja.orden}`"
                            />
                            <Button
                                type="submit"
                                variant="ghost"
                                size="icon-sm"
                                :disabled="processing"
                                :aria-label="`Guardar ${DIAS_CORTOS[dia]} ${franja.orden}`"
                                title="Guardar"
                            >
                                <Check />
                            </Button>
                            <BotonBorrar
                                :nombre="`${DIAS_CORTOS[dia]} ${franja.orden}`"
                                :accion="FranjaController.destroy(franja.id)"
                            />
                        </div>
                        <InputError
                            :message="
                                errors.inicio ?? errors.fin ?? errors.tipo
                            "
                        />
                    </Form>
                </div>
            </section>
        </div>
    </div>
</template>
