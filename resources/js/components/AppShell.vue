<script setup lang="ts">
</script>

<template>
    <div
        class="relative flex h-screen w-full flex-col overflow-hidden bg-background text-foreground"
    >
        <div
            class="pointer-events-none fixed inset-0 -z-10 opacity-80"
            style="background: var(--gradient-hero)"
        />
        <slot />
    </div>
</template>
