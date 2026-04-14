<script setup lang="ts">
import { Deferred, Head } from '@inertiajs/vue3';
import ArtistCard from '@/components/dashboard/ArtistCard.vue';
import PeriodSelector from '@/components/dashboard/PeriodSelector.vue';
import SectionHeader from '@/components/dashboard/SectionHeader.vue';
import TrackListItem from '@/components/dashboard/TrackListItem.vue';
import { dashboard } from '@/routes';
import type {
    RecentPlay,
    SpotifyArtist,
    SpotifyTrack,
    TimeRange,
} from '@/types/spotify';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

defineProps<{
    period: TimeRange;
    topTracks?: SpotifyTrack[];
    topArtists?: SpotifyArtist[];
    recentPlays?: RecentPlay[];
}>();

const SKELETON_COUNT = 5;
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col gap-6 p-4">
        <!-- Period Selector -->
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-bold text-foreground">Your Stats</h1>
            <PeriodSelector :current="period" />
        </div>

        <!-- Top Tracks -->
        <section>
            <SectionHeader
                title="Top Tracks"
                :description="
                    period === 'short_term'
                        ? 'Last 4 weeks'
                        : period === 'medium_term'
                          ? 'Last 6 months'
                          : 'All time'
                "
            />
            <div
                class="mt-3 rounded-xl border border-border bg-card p-2 shadow-sm"
            >
                <Deferred data="topTracks">
                    <template #fallback>
                        <TrackListItem
                            v-for="n in SKELETON_COUNT"
                            :key="n"
                            :rank="n"
                            :loading="true"
                        />
                    </template>

                    <template #default="{ reloading }">
                        <div :class="{ 'opacity-50': reloading }">
                            <TrackListItem
                                v-for="(track, i) in topTracks!.slice(
                                    0,
                                    SKELETON_COUNT,
                                )"
                                :key="track.id"
                                :rank="i + 1"
                                :track="track"
                            />
                            <p
                                v-if="topTracks!.length === 0"
                                class="py-6 text-center text-sm text-muted-foreground"
                            >
                                No tracks found for this period.
                            </p>
                        </div>
                    </template>
                </Deferred>
            </div>
        </section>

        <!-- Top Artists -->
        <section>
            <SectionHeader title="Top Artists" />
            <div
                class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-5"
            >
                <Deferred data="topArtists">
                    <template #fallback>
                        <ArtistCard
                            v-for="n in SKELETON_COUNT"
                            :key="n"
                            :rank="n"
                            :loading="true"
                        />
                    </template>

                    <template #default="{ reloading }">
                        <div
                            class="contents"
                            :class="{ 'opacity-50': reloading }"
                        >
                            <ArtistCard
                                v-for="(artist, i) in topArtists!.slice(
                                    0,
                                    SKELETON_COUNT,
                                )"
                                :key="artist.id"
                                :rank="i + 1"
                                :artist="artist"
                            />
                        </div>
                    </template>
                </Deferred>
            </div>
        </section>

        <!-- Recently Played -->
        <section>
            <SectionHeader title="Recently Played" />
            <div
                class="mt-3 rounded-xl border border-border bg-card p-2 shadow-sm"
            >
                <Deferred data="recentPlays">
                    <template #fallback>
                        <TrackListItem
                            v-for="n in SKELETON_COUNT"
                            :key="n"
                            :rank="n"
                            :loading="true"
                        />
                    </template>

                    <template #default="{ reloading }">
                        <div :class="{ 'opacity-50': reloading }">
                            <TrackListItem
                                v-for="(play, i) in recentPlays!.slice(
                                    0,
                                    SKELETON_COUNT,
                                )"
                                :key="`${play.track.id}-${play.played_at}`"
                                :rank="i + 1"
                                :track="play.track"
                            />
                            <p
                                v-if="recentPlays!.length === 0"
                                class="py-6 text-center text-sm text-muted-foreground"
                            >
                                No recent plays found.
                            </p>
                        </div>
                    </template>
                </Deferred>
            </div>
        </section>
    </div>
</template>
