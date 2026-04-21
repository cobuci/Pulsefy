<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { SidebarProvider } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

type Props = {
    variant?: AppVariant;
};

withDefaults(defineProps<Props>(), {
    variant: 'sidebar',
});

const isOpen = Boolean(
    (usePage().props as { sidebarOpen?: boolean }).sidebarOpen ?? true,
);
</script>

<template>
    <div
        v-if="variant === 'header'"
        class="relative flex h-screen w-full flex-col overflow-hidden bg-background text-foreground"
    >
        <div
            class="pointer-events-none fixed inset-0 -z-10 opacity-80"
            style="background: var(--gradient-hero)"
        />
        <slot />
    </div>
    <SidebarProvider v-else :default-open="isOpen">
        <slot />
    </SidebarProvider>
</template>
