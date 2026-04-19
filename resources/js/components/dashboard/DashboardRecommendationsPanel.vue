<script setup lang="ts">
import { Sparkles } from 'lucide-vue-next';
import IconPlay from '@/components/icons/IconPlay.vue';
import type { SpotifyTrack } from '@/types/spotify';

defineProps<{
    recommendationTracks: SpotifyTrack[];
    handlePlay: (track: SpotifyTrack) => Promise<void>;
}>();
</script>

<template>
    <div class="rounded-2xl border border-accent/30 bg-gradient-to-br from-primary/10 to-accent/5 p-5">
        <div class="mb-3 flex items-center gap-2">
            <Sparkles class="size-4 text-accent" />
            <h2 class="font-display text-lg font-bold">For you</h2>
        </div>
        <p class="mb-4 text-xs text-muted-foreground">
            Recommended from your top and recent listening.
        </p>

        <div class="space-y-3">
            <button
                v-for="track in recommendationTracks"
                :key="track.id"
                type="button"
                class="group -mx-2 flex w-[calc(100%+1rem)] cursor-pointer items-center gap-3 rounded-lg p-2 text-left transition-colors hover:bg-background/40"
                @click="handlePlay(track)"
            >
                <div class="relative size-12 shrink-0 overflow-hidden rounded-lg">
                    <img
                        v-if="track.album.images?.[0]?.url"
                        :src="track.album.images[0].url"
                        :alt="track.name"
                        class="size-12 rounded-lg object-cover"
                    />
                    <div v-else class="size-12 rounded-lg bg-muted" />

                    <div
                        class="absolute inset-0 hidden place-items-center bg-background/70 transition-opacity group-hover:grid"
                    >
                        <span
                            class="grid size-7 place-items-center rounded-full bg-primary text-primary-foreground"
                        >
                            <IconPlay class="ml-0.5 size-3.5" />
                        </span>
                    </div>
                </div>

                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ track.name }}</p>
                    <p class="truncate text-xs text-muted-foreground">
                        {{ track.artists.map((artist) => artist.name).join(', ') }}
                    </p>
                </div>
                <span class="text-[10px] font-bold tracking-wider text-accent uppercase">
                    Pick
                </span>
            </button>
        </div>
    </div>
</template>
