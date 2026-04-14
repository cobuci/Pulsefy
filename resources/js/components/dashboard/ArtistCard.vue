<script setup lang="ts">
import { computed } from 'vue';
import { Skeleton } from '@/components/ui/skeleton';
import type { SpotifyArtist } from '@/types/spotify';

const props = defineProps<{
    artist?: SpotifyArtist;
    rank: number;
    loading?: boolean;
}>();

const primaryImageUrl = computed(() => {
    if (!props.artist || !Array.isArray(props.artist.images)) {
        return null;
    }

    return props.artist.images[0]?.url ?? null;
});

const primaryGenre = computed(() => {
    if (!props.artist || !Array.isArray(props.artist.genres)) {
        return null;
    }

    return props.artist.genres[0] ?? null;
});
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
                    v-if="primaryImageUrl"
                    :src="primaryImageUrl"
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
                v-if="primaryGenre"
                class="truncate text-xs text-muted-foreground capitalize"
            >
                {{ primaryGenre }}
            </p>
        </template>
    </div>
</template>
