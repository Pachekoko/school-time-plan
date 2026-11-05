<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ProfesorController from '@/actions/App/Http/Controllers/Escuela/ProfesorController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SelectNativo from '@/components/SelectNativo.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/profesores';

type Profesor = {
    id: number;
    clave: string;
    nombre: string;
    contrato: string;
    horas_min: number;
    horas_max: number;
    max_dia: number;
    sin_huecos: boolean;
    planteles: number[];
    materias: number[];
    cuenta: string | null;
};

type Materia = { id: number; clave: string; nombre: string; semestre: number };

const props = defineProps<{
    profesor: Profesor | null;
    planteles: { id: number; nombre: string }[];
    materias: Materia[];
    contratos: { valor: string; etiqueta: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Profesores', href: index() }],
    },
});

// El filtro solo oculta: las casillas marcadas siguen yendo en el formulario.
const filtro = ref('');
const visible = (materia: Materia): boolean => {
    const texto = filtro.value.trim().toLowerCase();

    return (
        texto === '' ||
        `${materia.clave} ${materia.nombre}`.toLowerCase().includes(texto)
    );
};

const porSemestre = computed(() => {
    const grupos = new Map<number, Materia[]>();

    for (const materia of props.materias) {
        grupos.set(materia.semestre, [
            ...(grupos.get(materia.semestre) ?? []),
            materia,
        ]);
    }

    return [...grupos.entries()].sort(([a], [b]) => a - b);
});
</script>

<template>
    <Head :title="profesor ? 'Editar profesor' : 'Nuevo profesor'" />

    <div class="flex max-w-3xl flex-col gap-6 p-4">
        <Heading
            :title="profesor ? `Editar «${profesor.nombre}»` : 'Nuevo profesor'"
        />

        <Form
            v-bind="
                profesor
                    ? ProfesorController.update.form(profesor.id)
                    : ProfesorController.store.form()
            "
            class="flex flex-col gap-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-4 sm:grid-cols-[1fr_2fr]">
                <div class="grid gap-2">
                    <Label for="clave">Clave</Label>
                    <Input
                        id="clave"
                        name="clave"
                        required
                        maxlength="30"
                        :default-value="profesor?.clave"
                        placeholder="P001"
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
                        :default-value="profesor?.nombre"
                    />
                    <InputError :message="errors.nombre" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-4">
                <div class="grid gap-2 sm:col-span-1">
                    <Label for="contrato">Contrato</Label>
                    <SelectNativo id="contrato" name="contrato" required>
                        <option
                            v-for="contrato in contratos"
                            :key="contrato.valor"
                            :value="contrato.valor"
                            :selected="
                                (profesor?.contrato ?? 'asignatura') ===
                                contrato.valor
                            "
                        >
                            {{ contrato.etiqueta }}
                        </option>
                    </SelectNativo>
                    <InputError :message="errors.contrato" />
                </div>
                <div class="grid gap-2">
                    <Label for="horas_min">Horas mínimas</Label>
                    <Input
                        id="horas_min"
                        name="horas_min"
                        type="number"
                        required
                        min="0"
                        max="168"
                        :default-value="profesor?.horas_min ?? 0"
                    />
                    <InputError :message="errors.horas_min" />
                </div>
                <div class="grid gap-2">
                    <Label for="horas_max">Horas máximas</Label>
                    <Input
                        id="horas_max"
                        name="horas_max"
                        type="number"
                        required
                        min="0"
                        max="168"
                        :default-value="profesor?.horas_max ?? 20"
                    />
                    <InputError :message="errors.horas_max" />
                </div>
                <div class="grid gap-2">
                    <Label for="max_dia">Máximo por día</Label>
                    <Input
                        id="max_dia"
                        name="max_dia"
                        type="number"
                        required
                        min="1"
                        max="24"
                        :default-value="profesor?.max_dia ?? 6"
                    />
                    <InputError :message="errors.max_dia" />
                </div>
            </div>

            <label class="flex items-start gap-2 text-sm">
                <input
                    type="checkbox"
                    name="sin_huecos"
                    value="1"
                    :checked="profesor?.sin_huecos ?? false"
                    class="mt-0.5 size-4 accent-primary"
                />
                <span>
                    Sin horas libres entre clases
                    <span class="block text-muted-foreground">
                        Sus clases de cada día van seguidas.
                    </span>
                </span>
            </label>

            <fieldset class="grid gap-2">
                <legend class="mb-2 text-sm font-medium">
                    Planteles donde da clase
                </legend>
                <p
                    v-if="planteles.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    Aún no hay planteles.
                </p>
                <div class="flex flex-wrap gap-4">
                    <label
                        v-for="plantel in planteles"
                        :key="plantel.id"
                        class="flex items-center gap-1.5 text-sm"
                    >
                        <input
                            type="checkbox"
                            name="planteles[]"
                            :value="plantel.id"
                            :checked="
                                profesor?.planteles.includes(plantel.id) ??
                                false
                            "
                            class="size-4 accent-primary"
                        />
                        {{ plantel.nombre }}
                    </label>
                </div>
                <InputError
                    :message="errors.planteles ?? errors['planteles.0']"
                />
            </fieldset>

            <fieldset class="grid gap-2">
                <legend class="mb-2 text-sm font-medium">
                    Materias que puede dar
                </legend>
                <Input
                    v-if="materias.length > 8"
                    v-model="filtro"
                    type="search"
                    placeholder="Filtrar materias"
                    aria-label="Filtrar materias"
                    class="max-w-xs"
                />
                <p
                    v-if="materias.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    Aún no hay materias.
                </p>
                <div
                    class="grid max-h-80 gap-3 overflow-y-auto rounded-lg border p-3"
                >
                    <div
                        v-for="[semestre, lista] in porSemestre"
                        :key="semestre"
                    >
                        <p
                            class="mb-1 text-xs font-medium text-muted-foreground"
                        >
                            Semestre {{ semestre }}
                        </p>
                        <div class="grid gap-1 sm:grid-cols-2">
                            <label
                                v-for="materia in lista"
                                v-show="visible(materia)"
                                :key="materia.id"
                                class="flex items-center gap-1.5 text-sm"
                            >
                                <input
                                    type="checkbox"
                                    name="materias[]"
                                    :value="materia.id"
                                    :checked="
                                        profesor?.materias.includes(
                                            materia.id,
                                        ) ?? false
                                    "
                                    class="size-4 accent-primary"
                                />
                                <span
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{ materia.clave }}
                                </span>
                                {{ materia.nombre }}
                            </label>
                        </div>
                    </div>
                </div>
                <InputError
                    :message="errors.materias ?? errors['materias.0']"
                />
            </fieldset>

            <div class="grid gap-2">
                <Label for="correo"
                    >Cuenta para capturar su disponibilidad</Label
                >
                <p v-if="profesor?.cuenta" class="text-sm">
                    Vinculada a
                    <span class="font-medium">{{ profesor.cuenta }}</span
                    >.
                </p>
                <template v-else>
                    <Input
                        id="correo"
                        name="correo"
                        type="email"
                        maxlength="255"
                        autocomplete="off"
                        placeholder="correo@ejemplo.mx"
                        class="max-w-sm"
                    />
                    <p class="text-sm text-muted-foreground">
                        Opcional. Le mandamos un enlace para que defina su
                        contraseña.
                    </p>
                    <InputError :message="errors.correo" />
                </template>
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
