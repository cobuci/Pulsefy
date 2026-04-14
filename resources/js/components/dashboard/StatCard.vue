<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';

defineProps<{
    label: string;
    value?: string | number;
    loading?: boolean;
}>();
</script>

<template>
    <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
        <p
            class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
        >
            {{ label }}
        </p>
        <Skeleton v-if="loading" class="mt-2 h-8 w-24" />
        <p v-else class="mt-1 text-2xl font-bold text-foreground">
            {{ value ?? '—' }}
        </p>
    </div>
</template>
