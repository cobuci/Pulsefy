<script setup lang="ts">
const genres = [
    { name: 'Indie Pop', value: 32, color: 'oklch(0.92 0.1 200)' },
    { name: 'Alt Rock', value: 24, color: 'oklch(0.72 0.11 188)' },
    { name: 'Lo-fi', value: 18, color: 'oklch(0.65 0.13 155)' },
    { name: 'Neo Soul', value: 14, color: 'oklch(0.78 0.08 220)' },
    { name: 'Electronic', value: 12, color: 'oklch(0.55 0.1 188)' },
];
</script>

<template>
    <div
        class="h-full rounded-2xl border border-border bg-card p-5 shadow-card"
    >
        <p
            class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
        >
            Genre Mix
        </p>
        <p class="mt-1 mb-3 font-display text-xl font-bold">Top 5 genres</p>

        <div class="flex items-center gap-4">
            <div class="relative grid size-36 place-items-center">
                <div
                    class="size-36 rounded-full"
                    style="
                        background: conic-gradient(
                            oklch(0.92 0.1 200) 0deg 115deg,
                            oklch(0.72 0.11 188) 115deg 202deg,
                            oklch(0.65 0.13 155) 202deg 267deg,
                            oklch(0.78 0.08 220) 267deg 317deg,
                            oklch(0.55 0.1 188) 317deg 360deg
                        );
                    "
                />
                <div class="absolute size-20 rounded-full bg-card" />
            </div>

            <div class="flex-1 space-y-1.5">
                <div
                    v-for="genre in genres"
                    :key="genre.name"
                    class="flex items-center gap-2 text-sm"
                >
                    <span
                        class="size-2.5 rounded-full"
                        :style="{ background: genre.color }"
                    />
                    <span class="flex-1 truncate">{{ genre.name }}</span>
                    <span class="text-muted-foreground tabular-nums"
                        >{{ genre.value }}%</span
                    >
                </div>
            </div>
        </div>
    </div>
</template>
