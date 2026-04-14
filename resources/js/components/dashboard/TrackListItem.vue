<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
import type { SpotifyTrack } from '@/types/spotify';

defineProps<{
    track?: SpotifyTrack;
    rank: number;
    loading?: boolean;
}>();

function formatDuration(ms: number): string {
    const minutes = Math.floor(ms / 60000);
    const seconds = Math.floor((ms % 60000) / 1000);

    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
}
</script>

<template>
    <div
        class="flex items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-accent/30"
    >
        <span
            class="w-5 shrink-0 text-center text-sm font-semibold text-muted-foreground"
        >
            {{ rank }}
        </span>

        <template v-if="loading || !track">
            <Skeleton class="size-10 shrink-0 rounded-md" />
            <div class="flex flex-1 flex-col gap-1">
                <Skeleton class="h-4 w-40" />
                <Skeleton class="h-3 w-24" />
            </div>
            <Skeleton class="h-3 w-8" />
        </template>

        <template v-else>
            <img
                v-if="track.album.images[0]"
                :src="track.album.images[0].url"
                :alt="track.album.name"
                class="size-10 shrink-0 rounded-md object-cover"
            />
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-foreground">
                    {{ track.name }}
                </p>
                <p class="truncate text-xs text-muted-foreground">
                    {{ track.artists.map((a) => a.name).join(', ') }}
                </p>
            </div>
            <span class="shrink-0 text-xs text-muted-foreground tabular-nums">
                {{ formatDuration(track.duration_ms) }}
            </span>
        </template>
    </div>
</template>
