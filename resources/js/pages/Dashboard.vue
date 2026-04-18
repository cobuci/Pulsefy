<script setup lang="ts">
import { Deferred, Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronRight,
    Clock,
    Music2,
    Sparkles,
    TrendingUp,
} from 'lucide-vue-next';
import { computed, onUnmounted, ref } from 'vue';
import ArtistCard from '@/components/dashboard/ArtistCard.vue';
import PeriodSelector from '@/components/dashboard/PeriodSelector.vue';
import SectionHeader from '@/components/dashboard/SectionHeader.vue';
import StatCard from '@/components/dashboard/StatCard.vue';
import TrackListItem from '@/components/dashboard/TrackListItem.vue';
import { usePlayer } from '@/composables/usePlayer';
import { dashboard, recentlyPlayed } from '@/routes';
import { index as artistsIndex, show as artistShow } from '@/routes/artists';
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
const topArtistsPreview = computed(() => (props.topArtists ?? []).slice(0, 4));
const recentPlaysPreview = computed(() =>
    (props.recentPlays ?? []).slice(0, 6),
);

const totalHours = computed(() => {
    const totalDurationMs = topTracksPreview.value.reduce(
        (carry, track) => carry + track.duration_ms,
        0,
    );

    return `${Math.max(1, Math.round(totalDurationMs / (1000 * 60 * 60)))}h`;
});

const uniqueTrackCount = computed(() => topTracksPreview.value.length);

const topGenre = computed(() => {
    return topArtistsPreview.value[0]?.genres?.[0] ?? 'Mixed';
});

const recommendationTracks = computed(() => {
    const source =
        topTracksPreview.value.length > 3
            ? topTracksPreview.value.slice(3, 6)
            : recentPlaysPreview.value.slice(0, 3).map((play) => play.track);

    return source;
});

const listeningHeatmap = computed(() => {
    const buckets = Array.from({ length: 24 }, (_, hour) => ({
        hour,
        value: 0,
    }));

    if (!recentPlaysPreview.value.length) {
        return buckets.map((bucket) => ({
            ...bucket,
            value: Math.round(
                20 +
                    Math.abs(Math.sin((bucket.hour / 24) * Math.PI * 2 + 1)) *
                        80,
            ),
        }));
    }

    recentPlaysPreview.value.forEach((play) => {
        const hour = new Date(play.played_at).getHours();

        buckets[hour].value += 1;
    });

    const maxValue = Math.max(...buckets.map((bucket) => bucket.value), 1);

    return buckets.map((bucket) => ({
        ...bucket,
        value: Math.round((bucket.value / maxValue) * 100),
    }));
});

async function handlePlay(track: SpotifyTrack) {
    await playTrack(`spotify:track:${track.id}`);
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-10 py-4">
        <section class="grid gap-4 lg:grid-cols-3">
            <div class="mb-2 lg:col-span-3">
                <p
                    class="text-xs font-semibold tracking-[0.2em] text-accent uppercase"
                >
                    Your listening snapshot
                </p>
                <h1
                    class="mt-2 max-w-2xl font-display text-4xl font-bold sm:text-5xl"
                >
                    Your {{ periodDescription.toLowerCase() }} in sound was
                    <span class="text-gradient">vivid & eclectic</span>.
                </h1>
            </div>

            <StatCard
                accent
                label="Listening time"
                :value="totalHours"
                hint="Based on your top tracks"
            />
            <StatCard
                label="Unique tracks"
                :value="uniqueTrackCount"
                hint="In this selected range"
            />
            <StatCard
                label="Top genre"
                :value="topGenre"
                hint="Artist-led estimate"
            />
        </section>

        <div class="flex items-center justify-end">
            <PeriodSelector :current="period" :loading="isReloading" />
        </div>

        <section>
            <SectionHeader
                hint="01"
                title="Top Tracks"
                :description="periodDescription"
            />
            <div class="grid gap-6 lg:grid-cols-3">
                <div
                    class="rounded-2xl border border-border bg-card p-5 shadow-card lg:col-span-2"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <TrendingUp class="size-4 text-accent" />
                            <h2 class="font-display text-lg font-bold">
                                Top Tracks
                            </h2>
                        </div>
                        <span
                            class="flex items-center gap-1 text-xs text-muted-foreground"
                        >
                            <Clock class="size-3" />
                            {{ periodDescription }}
                        </span>
                    </div>

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

                <div
                    class="rounded-2xl border border-accent/30 bg-gradient-to-br from-primary/10 to-accent/5 p-5"
                >
                    <div class="mb-3 flex items-center gap-2">
                        <Sparkles class="size-4 text-accent" />
                        <h2 class="font-display text-lg font-bold">For you</h2>
                    </div>
                    <p class="mb-4 text-xs text-muted-foreground">
                        Hand-picked from patterns we noticed this week.
                    </p>

                    <div class="space-y-3">
                        <button
                            v-for="track in recommendationTracks"
                            :key="track.id"
                            type="button"
                            class="-mx-2 flex w-[calc(100%+1rem)] cursor-pointer items-center gap-3 rounded-lg p-2 text-left transition-colors hover:bg-background/40"
                            @click="handlePlay(track)"
                        >
                            <img
                                v-if="track.album.images?.[0]?.url"
                                :src="track.album.images[0].url"
                                :alt="track.name"
                                class="size-12 rounded-lg object-cover"
                            />
                            <div v-else class="size-12 rounded-lg bg-muted" />

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">
                                    {{ track.name }}
                                </p>
                                <p
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{
                                        track.artists
                                            .map((artist) => artist.name)
                                            .join(', ')
                                    }}
                                </p>
                            </div>
                            <span
                                class="text-[10px] font-bold tracking-wider text-accent uppercase"
                            >
                                New
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section>
            <SectionHeader
                hint="02"
                title="Top Artists"
                :description="periodDescription"
            >
                <Link
                    :href="artistsIndex()"
                    class="text-xs text-muted-foreground transition-colors hover:text-accent"
                >
                    See all
                </Link>
            </SectionHeader>
            <Deferred data="topArtists">
                <template #fallback>
                    <div
                        class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4"
                    >
                        <ArtistCard
                            v-for="n in 4"
                            :key="n"
                            :rank="n"
                            :loading="true"
                        />
                    </div>
                </template>

                <template #default="{ reloading }">
                    <div
                        class="grid grid-cols-2 gap-4 transition-opacity duration-300 sm:grid-cols-3 lg:grid-cols-4"
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

        <section>
            <SectionHeader hint="03" title="Recently Played">
                <Link
                    :href="recentlyPlayed()"
                    class="flex items-center gap-1 text-xs text-muted-foreground transition-colors hover:text-accent"
                >
                    See all
                    <ChevronRight class="size-3" />
                </Link>
            </SectionHeader>
            <div
                class="rounded-2xl border border-border bg-card p-5 shadow-card"
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
                        <div class="space-y-1">
                            <TrackListItem
                                v-for="(play, i) in recentPlaysPreview"
                                :key="`${play.track.id}-${play.played_at}`"
                                :rank="i + 1"
                                :track="play.track"
                                :is-playing="isPlayingTrack(play.track.id)"
                                @play="handlePlay"
                            />
                        </div>
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

        <section
            class="rounded-2xl border border-border bg-card p-6 shadow-card"
        >
            <div class="mb-4 flex items-center gap-2">
                <Music2 class="size-4 text-accent" />
                <h2 class="font-display text-lg font-bold">When you listen</h2>
            </div>

            <div class="grid h-32 grid-cols-12 items-end gap-1.5">
                <div
                    v-for="(item, index) in listeningHeatmap"
                    :key="item.hour"
                    class="col-span-1 flex flex-col items-center gap-1"
                >
                    <div
                        class="w-full rounded-t-md bg-gradient-to-t from-primary to-accent opacity-80 transition-opacity hover:opacity-100"
                        :style="{ height: `${item.value}%` }"
                    />
                    <span
                        v-if="index % 4 === 0"
                        class="text-[9px] text-muted-foreground tabular-nums"
                    >
                        {{ item.hour.toString().padStart(2, '0') }}
                    </span>
                </div>
            </div>
        </section>
    </div>
</template>
