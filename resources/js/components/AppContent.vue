<script setup lang="ts">
import { computed } from 'vue';

type Props = {
    class?: string;
};

const props = defineProps<Props>();
const className = computed(() => props.class);
</script>

<template>
    <div id="app-content" class="flex-1 overflow-y-auto" :class="className">
        <main
            class="mx-auto flex w-full max-w-7xl flex-col gap-4 px-4 py-6 md:px-6"
        >
            <slot />
        </main>
    </div>
</template>
