<script setup lang="ts">
defineProps<{
    genres?: Array<{ label: string; value: number; color: string }>;
}>();
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
        <p class="mt-1 mb-3 font-display text-xl font-bold">Top genres</p>

        <div v-if="genres?.length" class="flex items-center gap-4">
            <div class="relative grid size-36 place-items-center">
                <div
                    class="size-36 rounded-full"
                    :style="{
                        background: `conic-gradient(${genres
                            .reduce(
                                (carry, genre, index) => {
                                    const start = carry.offset;
                                    const end = start + genre.value * 3.6;

                                    carry.parts.push(
                                        `${genre.color} ${start}deg ${end}deg`,
                                    );
                                    carry.offset = end;

                                    if (
                                        index === genres.length - 1 &&
                                        end < 360
                                    ) {
                                        carry.parts.push(
                                            `${genre.color} ${end}deg 360deg`,
                                        );
                                    }

                                    return carry;
                                },
                                { parts: [] as string[], offset: 0 },
                            )
                            .parts.join(', ')})`,
                    }"
                />
                <div class="absolute size-20 rounded-full bg-card" />
            </div>

            <div class="flex-1 space-y-1.5">
                <div
                    v-for="genre in genres"
                    :key="genre.label"
                    class="flex items-center gap-2 text-sm"
                >
                    <span
                        class="size-2.5 rounded-full"
                        :style="{ background: genre.color }"
                    />
                    <span class="flex-1 truncate">{{ genre.label }}</span>
                    <span class="text-muted-foreground tabular-nums"
                        >{{ genre.value }}%</span
                    >
                </div>
            </div>
        </div>

        <div
            v-else
            class="grid h-36 place-items-center rounded-lg border border-dashed border-border text-sm text-muted-foreground"
        >
            Not enough genre data yet.
        </div>
    </div>
</template>
