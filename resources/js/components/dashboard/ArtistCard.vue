<script setup lang="ts">
import { computed } from 'vue';
import IconPlay from '@/components/icons/IconPlay.vue';
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
    <div class="group relative block">
        <template v-if="loading || !artist">
            <div
                class="relative aspect-square overflow-hidden rounded-2xl shadow-card"
            >
                <Skeleton class="size-full" />
                <div class="absolute right-14 bottom-3 left-3">
                    <Skeleton class="h-5 w-28" />
                    <Skeleton class="mt-2 h-4 w-20" />
                </div>
            </div>
        </template>

        <template v-else>
            <div
                class="relative aspect-square overflow-hidden rounded-2xl shadow-card"
            >
                <img
                    v-if="primaryImageUrl"
                    :src="primaryImageUrl"
                    :alt="artist.name"
                    class="size-full object-cover transition-transform duration-500 group-hover:scale-110"
                />
                <div v-else class="size-full bg-muted" />

                <div
                    class="absolute inset-0 bg-gradient-to-t from-background via-background/40 to-transparent opacity-90"
                />

                <span
                    class="glass absolute top-3 left-3 grid size-7 place-items-center rounded-full text-xs font-bold"
                >
                    {{ rank }}
                </span>

                <button
                    type="button"
                    class="bg-gradient-primary shadow-glow absolute right-3 bottom-3 grid size-10 translate-y-2 place-items-center rounded-full opacity-0 transition-all group-hover:translate-y-0 group-hover:opacity-100"
                >
                    <IconPlay class="ml-0.5 size-4 text-primary-foreground" />
                </button>

                <div class="absolute right-14 bottom-3 left-3">
                    <p
                        class="truncate font-display text-base font-bold text-foreground"
                    >
                        {{ artist.name }}
                    </p>
                    <p
                        v-if="primaryGenre"
                        class="truncate text-xs text-muted-foreground capitalize"
                    >
                        {{ primaryGenre }}
                    </p>
                </div>
            </div>
        </template>
    </div>
</template>
