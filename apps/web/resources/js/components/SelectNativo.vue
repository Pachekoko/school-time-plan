<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

// Un <select> nativo con el estilo de Input: viaja solo en los formularios de Inertia.
// No admite v-model: marca la opción con :selected y escucha @change.
defineOptions({ inheritAttrs: false });

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <select
        v-bind="$attrs"
        data-slot="select-nativo"
        :class="
            cn(
                'h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30',
                'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                'aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40',
                props.class,
            )
        "
    >
        <slot />
    </select>
</template>
