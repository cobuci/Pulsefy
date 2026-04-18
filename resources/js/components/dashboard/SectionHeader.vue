<script setup lang="ts">
defineProps<{
    title: string;
    description?: string;
    hint?: string;
}>();
</script>

<template>
    <div class="mb-4 flex items-end justify-between">
        <div>
            <p
                v-if="hint"
                class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
            >
                {{ hint }}
            </p>
            <h2 class="mt-0.5 font-display text-2xl font-bold text-foreground">
                {{ title }}
            </h2>
            <p v-if="description" class="mt-1 text-sm text-muted-foreground">
                {{ description }}
            </p>
        </div>
        <slot />
    </div>
</template>
