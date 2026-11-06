<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

const props = defineProps<{
    nombre: string;
    accion: { url: string; method: 'delete' };
}>();

const abierto = ref(false);
const borrando = ref(false);

function borrar(): void {
    router.visit(props.accion, {
        preserveScroll: true,
        onStart: () => {
            borrando.value = true;
        },
        onFinish: () => {
            borrando.value = false;
            abierto.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="abierto">
        <DialogTrigger as-child>
            <Button
                variant="ghost"
                size="icon-sm"
                :aria-label="`Borrar ${nombre}`"
                class="text-muted-foreground hover:text-destructive"
            >
                <Trash2 />
            </Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>¿Borrar «{{ nombre }}»?</DialogTitle>
                <DialogDescription
                    >Esto no se puede deshacer.</DialogDescription
                >
            </DialogHeader>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">Cancelar</Button>
                </DialogClose>
                <Button
                    variant="destructive"
                    :disabled="borrando"
                    @click="borrar"
                >
                    Borrar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
