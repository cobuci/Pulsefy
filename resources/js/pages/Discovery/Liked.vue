<script setup lang="ts">
import { InfiniteScroll, Link } from '@inertiajs/vue3';
import { Heart, Music } from 'lucide-vue-next';
import { index as discoveryIndex, liked as likedRoute } from '@/routes/discovery';

interface LikedTrack {
    id: number;
    spotify_id: string;
    name: string;
    artist_name: string;
    album_name: string;
    image_url: string | null;
    liked_at: string;
    liked_at_formatted: string;
}

interface PaginatedLikedTracks {
    data: LikedTrack[];
    meta: {
        total: number;
    };
}

defineProps<{
    likedTracks: PaginatedLikedTracks;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Discovery', href: discoveryIndex().url },
            { title: 'Liked Tracks', href: likedRoute().url },
        ],
    },
});
</script>

<template>
    <div class="mx-auto max-w-3xl px-6 py-10">
        <div class="mb-8">
            <div class="text-accent inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-[0.2em]">
                <Heart class="h-3 w-3" fill="currentColor" />
                Saved
            </div>
            <h1 class="mt-3 text-4xl font-bold">Liked Tracks</h1>
            <p class="text-muted-foreground mt-2 text-sm">
                Tracks you saved from Discovery.
                <span v-if="likedTracks.meta"> {{ likedTracks.meta.total }} total.</span>
            </p>
        </div>

        <div
            v-if="likedTracks.data.length === 0"
            class="border-border grid place-items-center rounded-3xl border border-dashed py-24 text-center"
        >
            <Music class="text-muted-foreground mb-4 h-10 w-10" />
            <h3 class="text-lg font-semibold">No liked tracks yet</h3>
            <p class="text-muted-foreground mt-1 text-sm">
                Head to
                <Link :href="discoveryIndex().url" class="text-accent underline">Discovery</Link>
                and swipe right on tracks you love.
            </p>
        </div>

        <InfiniteScroll v-else data="likedTracks" class="space-y-2">
            <div
                v-for="track in likedTracks.data"
                :key="track.id"
                class="bg-card border-border hover:bg-card/80 flex items-center gap-4 rounded-xl border p-4 transition-colors"
            >
                <div class="h-12 w-12 flex-shrink-0 overflow-hidden rounded-lg">
                    <img
                        v-if="track.image_url"
                        :src="track.image_url"
                        :alt="track.name"
                        class="h-full w-full object-cover"
                    />
                    <div v-else class="bg-muted grid h-full w-full place-items-center">
                        <Music class="text-muted-foreground h-5 w-5" />
                    </div>
                </div>

                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold">{{ track.name }}</p>
                    <p class="text-muted-foreground truncate text-sm">
                        {{ track.artist_name }}
                        <span v-if="track.album_name"> · {{ track.album_name }}</span>
                    </p>
                </div>

                <p class="text-muted-foreground flex-shrink-0 text-xs">
                    {{ track.liked_at_formatted }}
                </p>

                <Heart class="text-accent h-4 w-4 flex-shrink-0" fill="currentColor" />
            </div>
        </InfiniteScroll>
    </div>
</template>
