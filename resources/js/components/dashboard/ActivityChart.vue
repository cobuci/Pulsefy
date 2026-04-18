<script setup lang="ts">
import { computed } from 'vue';

const points = [
    { day: 'Mon', minutes: 72 },
    { day: 'Tue', minutes: 88 },
    { day: 'Wed', minutes: 54 },
    { day: 'Thu', minutes: 96 },
    { day: 'Fri', minutes: 76 },
    { day: 'Sat', minutes: 112 },
    { day: 'Sun', minutes: 84 },
];

const width = 520;
const height = 220;
const padding = 24;

const chartPoints = computed(() => {
    const max = Math.max(...points.map((point) => point.minutes), 1);
    const stepX = (width - padding * 2) / (points.length - 1);

    return points.map((point, index) => {
        const x = padding + index * stepX;
        const y =
            height - padding - (point.minutes / max) * (height - padding * 2);

        return { ...point, x, y };
    });
});

const linePath = computed(() => {
    return chartPoints.value
        .map(
            (point, index) =>
                `${index === 0 ? 'M' : 'L'} ${point.x} ${point.y}`,
        )
        .join(' ');
});

const areaPath = computed(() => {
    const first = chartPoints.value[0];
    const last = chartPoints.value.at(-1);

    if (!first || !last) {
        return '';
    }

    return `${linePath.value} L ${last.x} ${height - padding} L ${first.x} ${height - padding} Z`;
});
</script>

<template>
    <div
        class="h-full rounded-2xl border border-border bg-card p-5 shadow-card"
    >
        <div class="mb-4 flex items-baseline justify-between">
            <div>
                <p
                    class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                >
                    Listening Activity
                </p>
                <p class="mt-1 font-display text-xl font-bold">This week</p>
            </div>
            <p class="text-xs font-medium text-accent">+18% vs last week</p>
        </div>

        <svg
            viewBox="0 0 520 220"
            class="h-48 w-full"
            preserveAspectRatio="none"
        >
            <defs>
                <linearGradient
                    id="activity-gradient"
                    x1="0"
                    y1="0"
                    x2="0"
                    y2="1"
                >
                    <stop
                        offset="0%"
                        stop-color="oklch(0.92 0.1 200)"
                        stop-opacity="0.65"
                    />
                    <stop
                        offset="100%"
                        stop-color="oklch(0.72 0.11 188)"
                        stop-opacity="0"
                    />
                </linearGradient>
            </defs>

            <path :d="areaPath" fill="url(#activity-gradient)" />
            <path
                :d="linePath"
                fill="none"
                stroke="oklch(0.92 0.1 200)"
                stroke-width="2.5"
                stroke-linecap="round"
            />

            <g v-for="point in chartPoints" :key="point.day">
                <circle
                    :cx="point.x"
                    :cy="point.y"
                    r="3.5"
                    fill="oklch(0.92 0.1 200)"
                />
                <text
                    :x="point.x"
                    :y="height - 6"
                    text-anchor="middle"
                    class="fill-muted-foreground text-[10px]"
                >
                    {{ point.day }}
                </text>
            </g>
        </svg>
    </div>
</template>
