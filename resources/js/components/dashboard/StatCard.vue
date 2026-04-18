<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';

defineProps<{
    label: string;
    value?: string | number;
    hint?: string;
    accent?: boolean;
    loading?: boolean;
}>();
</script>

<template>
    <div
        class="relative overflow-hidden rounded-2xl border p-5"
        :class="
            accent
                ? 'shadow-glow border-accent/30 bg-gradient-to-br from-primary/15 to-accent/10'
                : 'border-border bg-card shadow-card'
        "
    >
        <p
            class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
        >
            {{ label }}
        </p>
        <Skeleton v-if="loading" class="mt-2 h-8 w-24" />
        <p v-else class="mt-2 font-display text-3xl font-bold text-foreground">
            {{ value ?? '—' }}
        </p>
        <p v-if="hint" class="mt-1 text-xs text-muted-foreground">
            {{ hint }}
        </p>

        <div
            v-if="accent"
            class="absolute -right-8 -bottom-8 size-24 rounded-full bg-accent/20 blur-2xl"
        />
    </div>
</template>
