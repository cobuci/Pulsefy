<script setup lang="ts">
import { Deferred } from '@inertiajs/vue3';
import { Clock, TrendingUp } from 'lucide-vue-next';
import TrackListItem from '@/components/dashboard/TrackListItem.vue';
import type { SpotifyTrack } from '@/types/spotify';

defineProps<{
    periodDescription: string;
    topTracksPreview: SpotifyTrack[];
    isPlayingTrack: (trackId: string) => boolean;
    handlePlay: (track: SpotifyTrack) => Promise<void>;
    handlePause: (track: SpotifyTrack) => Promise<void>;
    skeletonCount: number;
}>();
</script>

<template>
    <div
        class="rounded-2xl border border-border bg-card p-5 shadow-card lg:col-span-2"
    >
        <div class="mb-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <TrendingUp class="size-4 text-accent" />
                <h2 class="font-display text-lg font-bold">Top Tracks</h2>
            </div>
            <span class="flex items-center gap-1 text-xs text-muted-foreground">
                <Clock class="size-3" />
                {{ periodDescription }}
            </span>
        </div>

        <Deferred data="topTracks">
            <template #fallback>
                <TrackListItem
                    v-for="n in skeletonCount"
                    :key="n"
                    :rank="n"
                    :loading="true"
                />
            </template>

            <template #default="{ reloading }">
                <div
                    class="space-y-1 transition-opacity duration-300"
                    :class="{ 'opacity-40': reloading }"
                >
                    <TrackListItem
                        v-for="(track, i) in topTracksPreview"
                        :key="track.id"
                        :rank="i + 1"
                        :track="track"
                        :is-playing="isPlayingTrack(track.id)"
                        @play="handlePlay"
                        @pause="handlePause"
                    />
                    <p
                        v-if="topTracksPreview.length === 0"
                        class="py-6 text-center text-sm text-muted-foreground"
                    >
                        No tracks found for this period.
                    </p>
                </div>
            </template>
        </Deferred>
    </div>
</template>
