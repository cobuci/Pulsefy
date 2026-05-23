<script setup lang="ts">
import { Deferred, Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import IconPlay from '@/components/icons/IconPlay.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { useTrackContextMenu } from '@/composables/useTrackContextMenu';
import { recentlyPlayed } from '@/routes';
import { show as albumShow } from '@/routes/albums';
import { show as artistShow } from '@/routes/artists';
import type { RecentPlay, SpotifyTrack } from '@/types/spotify';

defineProps<{
    recentlyPlayedTracks: RecentPlay[];
    handlePlay: (track: SpotifyTrack) => Promise<void>;
}>();

const { openTrackContextMenu } = useTrackContextMenu();
</script>

<template>
    <section>
        <div class="mb-4 flex items-center justify-between">
            <h2 class="font-display text-lg font-bold">Recently Played</h2>
            <Link
                :href="recentlyPlayed()"
                class="flex items-center gap-1 text-xs text-muted-foreground transition-colors hover:text-accent"
            >
                See all
                <ChevronRight class="size-3" />
            </Link>
        </div>

        <Deferred data="recentPlays">
            <template #fallback>
                <div
                    class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6"
                >
                    <div
                        v-for="n in 6"
                        :key="n"
                        class="rounded-xl border border-border bg-card p-3 shadow-card"
                    >
                        <Skeleton class="aspect-square w-full rounded-lg" />
                        <Skeleton class="mt-3 h-4 w-3/4" />
                        <Skeleton class="mt-2 h-3 w-1/2" />
                    </div>
                </div>
            </template>

            <template #default>
                <div
                    v-if="!recentlyPlayedTracks.length"
                    class="rounded-2xl border border-border bg-card p-8 text-center text-sm text-muted-foreground shadow-card"
                >
                    No recent plays found.
                </div>

                <div
                    v-else
                    class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6"
                >
                    <div
                        v-for="play in recentlyPlayedTracks"
                        :key="`${play.track.id}-${play.played_at}`"
                        class="group"
                        @contextmenu="openTrackContextMenu($event, play.track)"
                    >
                        <div
                            class="relative mb-3 aspect-square overflow-hidden rounded-xl shadow-card"
                        >
                            <img
                                v-if="play.track.album.images?.[0]?.url"
                                :src="play.track.album.images[0].url"
                                :alt="play.track.album.name"
                                class="size-full object-cover transition-transform duration-500 group-hover:scale-105"
                            />
                            <div v-else class="size-full bg-muted" />
                            <div
                                class="absolute inset-0 grid place-items-center bg-background/0 transition-colors group-hover:bg-background/30"
                            >
                                <button
                                    type="button"
                                    class="bg-gradient-primary shadow-glow grid size-12 scale-75 cursor-pointer place-items-center rounded-full opacity-0 transition-all group-hover:scale-100 group-hover:opacity-100"
                                    @click="handlePlay(play.track)"
                                >
                                    <IconPlay
                                        class="ml-0.5 size-5 text-primary-foreground"
                                    />
                                </button>
                            </div>
                        </div>

                        <div class="min-w-0">
                            <Link
                                :href="albumShow(play.track.album.id).url"
                                class="block max-w-full truncate text-sm font-medium transition-colors group-hover:text-accent hover:text-accent"
                            >
                                {{ play.track.name }}
                            </Link>
                            <p class="truncate text-xs text-muted-foreground">
                                <template
                                    v-for="(artist, artistIndex) in play.track
                                        .artists"
                                    :key="artist.id"
                                >
                                    <Link
                                        :href="artistShow(artist.id).url"
                                        class="cursor-pointer hover:text-foreground"
                                    >
                                        {{ artist.name }}
                                    </Link>
                                    <span
                                        v-if="
                                            artistIndex <
                                            play.track.artists.length - 1
                                        "
                                        >,
                                    </span>
                                </template>
                            </p>
                        </div>
                    </div>
                </div>
            </template>
        </Deferred>
    </section>
</template>
