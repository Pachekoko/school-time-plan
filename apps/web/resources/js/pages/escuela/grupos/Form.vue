<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import GrupoController from '@/actions/App/Http/Controllers/Escuela/GrupoController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SelectNativo from '@/components/SelectNativo.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/grupos';

type Grupo = {
    id: number;
    plantel_id: number;
    plantilla_id: number;
    plan_id: number;
    semestre: number;
    clave: string;
    alumnos: number;
};

type Opcion = { id: number; nombre: string };

defineProps<{
    grupo: Grupo | null;
    ciclo: string;
    planteles: Opcion[];
    plantillas: Opcion[];
    planes: (Opcion & { clave: string })[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Grupos', href: index() }],
    },
});
</script>

<template>
    <Head :title="grupo ? 'Editar grupo' : 'Nuevo grupo'" />

    <div class="flex max-w-xl flex-col gap-6 p-4">
        <Heading
            :title="grupo ? `Editar «${grupo.clave}»` : 'Nuevo grupo'"
            :description="`Ciclo ${ciclo}.`"
        />

        <Form
            v-bind="
                grupo
                    ? GrupoController.update.form(grupo.id)
                    : GrupoController.store.form()
            "
            class="flex flex-col gap-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="grid gap-2">
                    <Label for="clave">Clave</Label>
                    <Input
                        id="clave"
                        name="clave"
                        required
                        maxlength="30"
                        :default-value="grupo?.clave"
                        placeholder="1A"
                    />
                    <InputError :message="errors.clave" />
                </div>
                <div class="grid gap-2">
                    <Label for="semestre">Semestre</Label>
                    <Input
                        id="semestre"
                        name="semestre"
                        type="number"
                        required
                        min="1"
                        max="12"
                        :default-value="grupo?.semestre ?? 1"
                    />
                    <InputError :message="errors.semestre" />
                </div>
                <div class="grid gap-2">
                    <Label for="alumnos">Alumnos</Label>
                    <Input
                        id="alumnos"
                        name="alumnos"
                        type="number"
                        required
                        min="1"
                        max="10000"
                        :default-value="grupo?.alumnos ?? 30"
                    />
                    <InputError :message="errors.alumnos" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="plantel_id">Plantel</Label>
                <SelectNativo id="plantel_id" name="plantel_id" required>
                    <option value="" disabled :selected="!grupo">
                        Elige un plantel
                    </option>
                    <option
                        v-for="plantel in planteles"
                        :key="plantel.id"
                        :value="plantel.id"
                        :selected="grupo?.plantel_id === plantel.id"
                    >
                        {{ plantel.nombre }}
                    </option>
                </SelectNativo>
                <InputError :message="errors.plantel_id" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="plantilla_id">Plantilla horaria</Label>
                    <SelectNativo
                        id="plantilla_id"
                        name="plantilla_id"
                        required
                    >
                        <option value="" disabled :selected="!grupo">
                            Elige una plantilla
                        </option>
                        <option
                            v-for="plantilla in plantillas"
                            :key="plantilla.id"
                            :value="plantilla.id"
                            :selected="grupo?.plantilla_id === plantilla.id"
                        >
                            {{ plantilla.nombre }}
                        </option>
                    </SelectNativo>
                    <InputError :message="errors.plantilla_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="plan_id">Plan de estudios</Label>
                    <SelectNativo id="plan_id" name="plan_id" required>
                        <option value="" disabled :selected="!grupo">
                            Elige un plan
                        </option>
                        <option
                            v-for="plan in planes"
                            :key="plan.id"
                            :value="plan.id"
                            :selected="grupo?.plan_id === plan.id"
                        >
                            {{ plan.clave }} · {{ plan.nombre }}
                        </option>
                    </SelectNativo>
                    <InputError :message="errors.plan_id" />
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
