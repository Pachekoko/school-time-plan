<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!-- La «H» de Horarios: una cuadrícula de horario con los dos huecos del centro. -->
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 32 32"
        :class="className"
        v-bind="$attrs"
    >
        <rect x="2" y="2" width="8" height="8" rx="2" fill="currentColor" />
        <rect
            x="12"
            y="2"
            width="8"
            height="8"
            rx="2"
            fill="currentColor"
            fill-opacity="0.25"
        />
        <rect x="22" y="2" width="8" height="8" rx="2" fill="currentColor" />
        <rect x="2" y="12" width="8" height="8" rx="2" fill="currentColor" />
        <rect x="12" y="12" width="8" height="8" rx="2" fill="currentColor" />
        <rect x="22" y="12" width="8" height="8" rx="2" fill="currentColor" />
        <rect x="2" y="22" width="8" height="8" rx="2" fill="currentColor" />
        <rect
            x="12"
            y="22"
            width="8"
            height="8"
            rx="2"
            fill="currentColor"
            fill-opacity="0.25"
        />
        <rect x="22" y="22" width="8" height="8" rx="2" fill="currentColor" />
    </svg>
</template>
