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
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 40 32"
        :class="className"
        v-bind="$attrs"
    >
        <path
            fill="none"
            stroke="currentColor"
            stroke-width="2.5"
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M0 16 h4 l3 -10 l4 20 l4 -16 l3 8 l4 -6 l3 4 h15"
        />
    </svg>
</template>
