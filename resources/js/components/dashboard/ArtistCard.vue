<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
import type { SpotifyArtist } from '@/types/spotify';

defineProps<{
    artist?: SpotifyArtist;
    rank: number;
    loading?: boolean;
}>();
</script>

<template>
    <div
        class="flex flex-col items-center gap-2 rounded-xl border border-border bg-card p-4 text-center shadow-sm"
    >
        <template v-if="loading || !artist">
            <Skeleton class="size-16 rounded-full" />
            <Skeleton class="h-4 w-24" />
            <Skeleton class="h-3 w-16" />
        </template>

        <template v-else>
            <div class="relative">
                <img
                    v-if="artist.images?.length"
                    :src="artist.images[0].url"
                    :alt="artist.name"
                    class="size-16 rounded-full object-cover ring-2 ring-primary/30"
                />
                <div v-else class="size-16 rounded-full bg-muted" />
                <span
                    class="absolute -top-1 -right-1 flex size-5 items-center justify-center rounded-full bg-primary text-[10px] font-bold text-primary-foreground"
                >
                    {{ rank }}
                </span>
            </div>
            <p class="line-clamp-2 text-sm font-semibold text-foreground">
                {{ artist.name }}
            </p>
            <p
                v-if="artist.genres?.length"
                class="truncate text-xs text-muted-foreground capitalize"
            >
                {{ artist.genres[0] }}
            </p>
        </template>
    </div>
</template>
