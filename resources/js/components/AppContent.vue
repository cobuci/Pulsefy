<script setup lang="ts">
import { computed } from 'vue';
import { SidebarInset } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

type Props = {
    variant?: AppVariant;
    class?: string;
};

const props = withDefaults(defineProps<Props>(), {
    variant: 'sidebar',
});
const className = computed(() => props.class);
</script>

<template>
    <SidebarInset v-if="props.variant === 'sidebar'" :class="className">
        <slot />
    </SidebarInset>
    <div
        v-else
        id="app-content"
        class="flex-1 overflow-y-auto"
        :class="className"
    >
        <main class="mx-auto flex w-full max-w-7xl flex-col gap-4 px-4 py-6 md:px-6">
            <slot />
        </main>
    </div>
</template>
