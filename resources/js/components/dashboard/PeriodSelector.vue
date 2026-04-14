<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import type { TimeRange } from '@/types/spotify';

const props = defineProps<{
    current: TimeRange;
}>();

const options: { label: string; value: TimeRange }[] = [
    { label: '4 weeks', value: 'short_term' },
    { label: '6 months', value: 'medium_term' },
    { label: 'All time', value: 'long_term' },
];

function select(value: TimeRange) {
    if (value === props.current) {
        return;
    }

    router.visit(dashboard(), {
        data: { period: value },
        preserveScroll: true,
        preserveState: true,
    });
}
</script>

<template>
    <div class="inline-flex items-center gap-1 rounded-lg bg-muted p-1">
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            :class="[
                'rounded-md px-3 py-1.5 text-xs font-semibold transition-colors',
                current === option.value
                    ? 'bg-primary text-primary-foreground shadow-sm'
                    : 'text-muted-foreground hover:text-foreground',
            ]"
            @click="select(option.value)"
        >
            {{ option.label }}
        </button>
    </div>
</template>
