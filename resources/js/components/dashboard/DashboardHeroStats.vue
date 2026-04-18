<script setup lang="ts">
import StatCard from '@/components/dashboard/StatCard.vue';

defineProps<{
    greetingName: string;
    headlineText: string;
    totalHours: string;
    uniqueTrackCount: number;
    topGenre: string;
}>();
</script>

<template>
    <section class="grid gap-4 lg:grid-cols-3">
        <div class="mb-2 lg:col-span-3">
            <p class="text-xs font-semibold tracking-[0.2em] text-accent uppercase">
                Good evening, {{ greetingName }}
            </p>
            <h1 class="mt-2 max-w-2xl font-display text-4xl font-bold sm:text-5xl">
                {{ headlineText }}
            </h1>
        </div>

        <StatCard
            accent
            label="Listening time"
            :value="totalHours"
            hint="Based on your top tracks"
        />
        <StatCard
            label="Unique tracks"
            :value="uniqueTrackCount"
            hint="In this selected range"
        />
        <StatCard
            label="Top genre"
            :value="topGenre"
            hint="Artist-led estimate"
        />
    </section>
</template>
