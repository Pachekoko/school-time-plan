<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CursoController from '@/actions/App/Http/Controllers/Escuela/CursoController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SelectNativo from '@/components/SelectNativo.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/cursos';

type Curso = {
    id: number;
    materia_id: number;
    grupos: number[];
    profesor_id: number | null;
    vacante: boolean;
    sincronia_id: number | null;
    alumnos: number | null;
    clave: string;
};

type Materia = { id: number; clave: string; nombre: string; semestre: number };
type Grupo = { id: number; clave: string; semestre: number };

const props = defineProps<{
    curso: Curso | null;
    ciclo: string;
    materias: Materia[];
    grupos: Grupo[];
    profesores: { id: number; nombre: string }[];
    sincronias: { id: number; clave: string; nombre: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Carga académica', href: index() }],
    },
});

const vacante = ref(props.curso?.vacante ?? false);

function porSemestre<T extends { semestre: number }>(
    lista: T[],
): [number, T[]][] {
    const grupos = new Map<number, T[]>();

    for (const elemento of lista) {
        grupos.set(elemento.semestre, [
            ...(grupos.get(elemento.semestre) ?? []),
            elemento,
        ]);
    }

    return [...grupos.entries()].sort(([a], [b]) => a - b);
}

const materiasPorSemestre = computed(() => porSemestre(props.materias));
const gruposPorSemestre = computed(() => porSemestre(props.grupos));
</script>

<template>
    <Head :title="curso ? 'Editar curso' : 'Nuevo curso'" />

    <div class="flex max-w-3xl flex-col gap-6 p-4">
        <Heading
            :title="curso ? `Editar «${curso.clave}»` : 'Nuevo curso'"
            :description="`Ciclo ${ciclo}. Varios grupos en un curso toman la clase juntos.`"
        />

        <Form
            v-bind="
                curso
                    ? CursoController.update.form(curso.id)
                    : CursoController.store.form()
            "
            class="flex flex-col gap-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="materia_id">Materia</Label>
                <SelectNativo id="materia_id" name="materia_id" required>
                    <option value="" disabled :selected="!curso">
                        Elige una materia
                    </option>
                    <optgroup
                        v-for="[semestre, lista] in materiasPorSemestre"
                        :key="semestre"
                        :label="`Semestre ${semestre}`"
                    >
                        <option
                            v-for="materia in lista"
                            :key="materia.id"
                            :value="materia.id"
                            :selected="curso?.materia_id === materia.id"
                        >
                            {{ materia.clave }} · {{ materia.nombre }}
                        </option>
                    </optgroup>
                </SelectNativo>
                <InputError :message="errors.materia_id" />
            </div>

            <fieldset class="grid gap-2">
                <legend class="mb-2 text-sm font-medium">Grupos</legend>
                <p
                    v-if="grupos.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    Aún no hay grupos en el ciclo {{ ciclo }}.
                </p>
                <div class="grid gap-3 rounded-lg border p-3">
                    <div
                        v-for="[semestre, lista] in gruposPorSemestre"
                        :key="semestre"
                    >
                        <p
                            class="mb-1 text-xs font-medium text-muted-foreground"
                        >
                            Semestre {{ semestre }}
                        </p>
                        <div class="flex flex-wrap gap-4">
                            <label
                                v-for="grupo in lista"
                                :key="grupo.id"
                                class="flex items-center gap-1.5 font-mono text-sm"
                            >
                                <input
                                    type="checkbox"
                                    name="grupos[]"
                                    :value="grupo.id"
                                    :checked="
                                        curso?.grupos.includes(grupo.id) ??
                                        false
                                    "
                                    class="size-4 accent-primary"
                                />
                                {{ grupo.clave }}
                            </label>
                        </div>
                    </div>
                </div>
                <InputError
                    :message="
                        errors.grupos ??
                        errors['grupos.0'] ??
                        errors['grupos.1']
                    "
                />
            </fieldset>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="profesor_id">Profesor</Label>
                    <SelectNativo
                        id="profesor_id"
                        name="profesor_id"
                        :disabled="vacante"
                    >
                        <option value="" :selected="!curso?.profesor_id">
                            Por asignar (lo elige el motor)
                        </option>
                        <option
                            v-for="profesor in profesores"
                            :key="profesor.id"
                            :value="profesor.id"
                            :selected="curso?.profesor_id === profesor.id"
                        >
                            {{ profesor.nombre }}
                        </option>
                    </SelectNativo>
                    <InputError :message="errors.profesor_id" />
                </div>
                <label class="flex items-start gap-2 self-end pb-2 text-sm">
                    <input
                        v-model="vacante"
                        type="checkbox"
                        name="vacante"
                        value="1"
                        class="mt-0.5 size-4 accent-primary"
                    />
                    <span>
                        Vacante
                        <span class="block text-muted-foreground">
                            Todavía no hay profesor contratado.
                        </span>
                    </span>
                </label>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="grid gap-2">
                    <Label for="sincronia_id">Sincronía</Label>
                    <SelectNativo id="sincronia_id" name="sincronia_id">
                        <option value="" :selected="!curso?.sincronia_id">
                            Sin sincronía
                        </option>
                        <option
                            v-for="sincronia in sincronias"
                            :key="sincronia.id"
                            :value="sincronia.id"
                            :selected="curso?.sincronia_id === sincronia.id"
                        >
                            {{ sincronia.clave }} · {{ sincronia.nombre }}
                        </option>
                    </SelectNativo>
                    <InputError :message="errors.sincronia_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="alumnos">
                        Alumnos
                        <span class="font-normal text-muted-foreground"
                            >(opcional)</span
                        >
                    </Label>
                    <Input
                        id="alumnos"
                        name="alumnos"
                        type="number"
                        min="1"
                        max="10000"
                        placeholder="Los de sus grupos"
                        :default-value="curso?.alumnos ?? ''"
                    />
                    <InputError :message="errors.alumnos" />
                </div>
                <div class="grid gap-2">
                    <Label for="clave">
                        Clave
                        <span class="font-normal text-muted-foreground"
                            >(opcional)</span
                        >
                    </Label>
                    <Input
                        id="clave"
                        name="clave"
                        maxlength="120"
                        class="font-mono"
                        :placeholder="
                            curso ? curso.clave : 'Se genera: 1A-MAT1'
                        "
                        :default-value="curso?.clave ?? ''"
                    />
                    <InputError :message="errors.clave" />
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
