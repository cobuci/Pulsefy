<script setup lang="ts">
import { Deferred, Head, Link, router } from '@inertiajs/vue3';
import { computed, onUnmounted, ref } from 'vue';
import ArtistCard from '@/components/dashboard/ArtistCard.vue';
import PeriodSelector from '@/components/dashboard/PeriodSelector.vue';
import SectionHeader from '@/components/dashboard/SectionHeader.vue';
import TrackListItem from '@/components/dashboard/TrackListItem.vue';
import { usePlayer } from '@/composables/usePlayer';
import { dashboard, recentlyPlayed } from '@/routes';
import { show as artistShow } from '@/routes/artists';
import type {
    RecentPlay,
    SpotifyArtist,
    SpotifyTrack,
    TimeRange,
} from '@/types/spotify';

defineOptions({
    inheritAttrs: false,
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const props = defineProps<{
    period: TimeRange;
    topTracks?: SpotifyTrack[];
    topArtists?: SpotifyArtist[];
    recentPlays?: RecentPlay[];
}>();

const SKELETON_COUNT = 5;

const isReloading = ref(false);

const offStart = router.on('start', () => (isReloading.value = true));
const offFinish = router.on('finish', () => (isReloading.value = false));

onUnmounted(() => {
    offStart();
    offFinish();
});

const { isPlayingTrack, playTrack } = usePlayer();

const periodDescription = computed(() => {
    if (props.period === 'short_term') {
        return 'Last 4 weeks';
    }

    if (props.period === 'medium_term') {
        return 'Last 6 months';
    }

    return 'All time';
});

const topTracksPreview = computed(() =>
    (props.topTracks ?? []).slice(0, SKELETON_COUNT),
);
const topArtistsPreview = computed(() =>
    (props.topArtists ?? []).slice(0, SKELETON_COUNT),
);
const recentPlaysPreview = computed(() =>
    (props.recentPlays ?? []).slice(0, SKELETON_COUNT),
);

async function handlePlay(track: SpotifyTrack) {
    await playTrack(`spotify:track:${track.id}`);
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col gap-6 p-4">
        <!-- Period Selector -->
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-bold text-foreground">Your Stats</h1>
            <PeriodSelector :current="period" :loading="isReloading" />
        </div>

        <!-- Top Tracks -->
        <section>
            <SectionHeader
                title="Top Tracks"
                :description="periodDescription"
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
                        <div
                            class="transition-opacity duration-300"
                            :class="{ 'opacity-40': reloading }"
                        >
                            <TrackListItem
                                v-for="(track, i) in topTracksPreview"
                                :key="track.id"
                                :rank="i + 1"
                                :track="track"
                                :is-playing="isPlayingTrack(track.id)"
                                @play="handlePlay"
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
        </section>

        <!-- Top Artists -->
        <section>
            <SectionHeader title="Top Artists" />
            <Deferred data="topArtists">
                <template #fallback>
                    <div
                        class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-5"
                    >
                        <ArtistCard
                            v-for="n in SKELETON_COUNT"
                            :key="n"
                            :rank="n"
                            :loading="true"
                        />
                    </div>
                </template>

                <template #default="{ reloading }">
                    <div
                        class="mt-3 grid grid-cols-2 gap-3 transition-opacity duration-300 sm:grid-cols-3 md:grid-cols-5"
                        :class="{ 'opacity-40': reloading }"
                    >
                        <Link
                            v-for="(artist, i) in topArtistsPreview"
                            :key="artist.id"
                            :href="artistShow(artist.id).url"
                        >
                            <ArtistCard :rank="i + 1" :artist="artist" />
                        </Link>
                    </div>
                </template>
            </Deferred>
        </section>

        <!-- Recently Played -->
        <section>
            <SectionHeader title="Recently Played">
                <Link
                    :href="recentlyPlayed()"
                    class="text-xs font-medium text-muted-foreground transition-colors hover:text-foreground"
                >
                    See all
                </Link>
            </SectionHeader>
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

                    <template #default>
                        <TrackListItem
                            v-for="(play, i) in recentPlaysPreview"
                            :key="`${play.track.id}-${play.played_at}`"
                            :rank="i + 1"
                            :track="play.track"
                            :is-playing="isPlayingTrack(play.track.id)"
                            @play="handlePlay"
                        />
                        <p
                            v-if="recentPlaysPreview.length === 0"
                            class="py-6 text-center text-sm text-muted-foreground"
                        >
                            No recent plays found.
                        </p>
                    </template>
                </Deferred>
            </div>
        </section>
    </div>
</template>
