<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import CorridaController from '@/actions/App/Http/Controllers/Escuela/CorridaController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/corridas';

defineProps<{
    corrida: {
        id: number;
        estado: string;
        estado_etiqueta: string;
        activa: boolean;
        mensaje_error: string | null;
        puntaje: number | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Horarios', href: index() }],
    },
});
</script>

<template>
    <Head :title="`Corrida #${corrida.id}`" />

    <div class="flex flex-col gap-6 p-4">
        <Heading :title="`Corrida #${corrida.id}`" />

        <div class="flex flex-wrap items-center gap-3 text-sm">
            <Badge variant="secondary">{{ corrida.estado_etiqueta }}</Badge>
            <span v-if="corrida.puntaje !== null" class="text-muted-foreground">
                Puntaje {{ corrida.puntaje }}
            </span>
        </div>

        <p v-if="corrida.activa" class="text-sm text-muted-foreground">
            El motor está trabajando. Recarga la página en unos segundos.
        </p>

        <Form
            v-if="corrida.estado === 'error'"
            v-bind="CorridaController.reintentar.form(corrida.id)"
            class="flex flex-col gap-3"
            v-slot="{ errors, processing }"
        >
            <p class="text-sm">{{ corrida.mensaje_error }}</p>
            <InputError :message="errors.corrida" />
            <div>
                <Button type="submit" :disabled="processing">Reintentar</Button>
            </div>
        </Form>
    </div>
</template>
