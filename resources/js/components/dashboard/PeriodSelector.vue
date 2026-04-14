<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';
import type { TimeRange } from '@/types/spotify';

const props = defineProps<{
    current: TimeRange;
    loading?: boolean;
}>();

const options: { label: string; value: TimeRange }[] = [
    { label: '4 weeks', value: 'short_term' },
    { label: '6 months', value: 'medium_term' },
    { label: 'All time', value: 'long_term' },
];

function select(value: TimeRange) {
    if (value === props.current || props.loading) {
        return;
    }

    router.reload({
        data: { period: value },
        only: ['period', 'topTracks', 'topArtists'],
        preserveScroll: true,
    });
}
</script>

<template>
    <div class="inline-flex items-center gap-1 rounded-lg bg-muted p-1">
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            :disabled="loading"
            :class="[
                'relative flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-semibold transition-all duration-200',
                current === option.value
                    ? 'bg-primary text-primary-foreground shadow-sm'
                    : 'text-muted-foreground hover:text-foreground',
                loading ? 'cursor-not-allowed' : 'cursor-pointer',
            ]"
            @click="select(option.value)"
        >
            <Loader2
                v-if="loading && current === option.value"
                class="h-3 w-3 animate-spin"
            />
            {{ option.label }}
        </button>
    </div>
</template>
