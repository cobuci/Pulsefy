<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, defineAsyncComponent, onMounted, onUnmounted, ref } from 'vue';
import DashboardHeroStats from '@/components/dashboard/DashboardHeroStats.vue';
import DashboardRecentPlaysSection from '@/components/dashboard/DashboardRecentPlaysSection.vue';
import DashboardRecommendationsPanel from '@/components/dashboard/DashboardRecommendationsPanel.vue';
import DashboardTopArtistsSection from '@/components/dashboard/DashboardTopArtistsSection.vue';
import DashboardTopTracksSection from '@/components/dashboard/DashboardTopTracksSection.vue';
import PeriodSelector from '@/components/dashboard/PeriodSelector.vue';
import SectionHeader from '@/components/dashboard/SectionHeader.vue';
import { usePlayer } from '@/composables/usePlayer';
import { dashboard } from '@/routes';
import { refresh as refreshInsights } from '@/routes/insights';
import type {
    RecentPlay,
    SpotifyArtist,
    SpotifyTrack,
    TimeRange,
} from '@/types/spotify';

const ActivityChart = defineAsyncComponent(
    () => import('@/components/dashboard/ActivityChart.vue'),
);
const GenreChart = defineAsyncComponent(
    () => import('@/components/dashboard/GenreChart.vue'),
);

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

const props = defineProps<{
    period: TimeRange;
    topTracks?: SpotifyTrack[];
    topArtists?: SpotifyArtist[];
    recentPlays?: RecentPlay[];
    insights?: {
        headline?: string;
        topGenre?: string;
        topGenres?: Array<{ label: string; value: number; color: string }>;
        activitySeries?: Array<{ label: string; value: number }>;
        genreMix?: Array<{ label: string; value: number; color: string }>;
        recommendations?: SpotifyTrack[];
    };
    syncStatus?: {
        isRunning: boolean;
        hasFailure: boolean;
        completed: number;
        total: number;
        progress: number;
        updatedAt: string | null;
    };
}>();

const SKELETON_COUNT = 5;

const isReloading = ref(false);
const isRefreshing = ref(false);

const offStart = router.on('start', () => (isReloading.value = true));
const offFinish = router.on('finish', () => (isReloading.value = false));

onUnmounted(() => {
    offStart();
    offFinish();
});

const { isPlayingTrack, playTrack } = usePlayer();
const page = usePage<{
    auth: {
        user?: {
            id: number;
            name?: string;
        };
    };
}>();

const syncStatusRef = ref(
    props.syncStatus ?? {
        isRunning: false,
        hasFailure: false,
        completed: 0,
        total: 3,
        progress: 0,
        updatedAt: null,
    },
);

const currentSyncStatus = computed(() => syncStatusRef.value);

onMounted(() => {
    if (page.props.auth?.user?.id && typeof window !== 'undefined' && window.Echo) {
        window.Echo.private(`App.Models.User.${page.props.auth.user.id}`).listen(
            '.Spotify.SyncStatusUpdated',
            (event: { status: typeof syncStatusRef.value }) => {
                syncStatusRef.value = event.status;

                if (!event.status.isRunning) {
                    router.reload({
                        only: ['syncStatus', 'topTracks', 'topArtists', 'recentPlays', 'insights'],
                    });
                }
            },
        );
    }
});

onUnmounted(() => {
    if (page.props.auth?.user?.id && typeof window !== 'undefined' && window.Echo) {
        window.Echo.leave(`App.Models.User.${page.props.auth.user.id}`);
    }
});

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
    const label = props.insights?.topGenre ?? topArtistsPreview.value[0]?.genres?.[0] ?? 'Mixed';

    return label.charAt(0).toUpperCase() + label.slice(1);
});

const greetingName = computed(() => {
    const fullName = page.props.auth?.user?.name;

    if (!fullName) {
        return 'Listener';
    }

    return fullName.split(' ')[0] ?? fullName;
});

const recommendationTracks = computed(() => {
    if (props.insights?.recommendations?.length) {
        return props.insights.recommendations;
    }

    const source =
        topTracksPreview.value.length > 3
            ? topTracksPreview.value.slice(3, 6)
            : recentPlaysPreview.value.slice(0, 3).map((play) => play.track);

    return source;
});

const headlineText = computed(() => {
    return props.insights?.headline ?? 'Your recent listening kept evolving.';
});

const activitySeries = computed(() => props.insights?.activitySeries ?? []);
const genreMix = computed(() => {
    return props.insights?.topGenres ?? props.insights?.genreMix ?? [];
});

const recentlyPlayedTracks = computed(() => {
    const uniqueByTrack = new Map<string, RecentPlay>();

    (props.recentPlays ?? []).forEach((play) => {
        const trackId = play.track.id;

        if (!uniqueByTrack.has(trackId)) {
            uniqueByTrack.set(trackId, play);
        }
    });

    return Array.from(uniqueByTrack.values()).slice(0, 6);
});

async function handlePlay(track: SpotifyTrack) {
    await playTrack(`spotify:track:${track.id}`);
}

function refreshAllInsights() {
    if (isRefreshing.value) {
        return;
    }

    isRefreshing.value = true;

    router.post(
        refreshInsights.url(),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isRefreshing.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-10 py-4">
        <DashboardHeroStats
            :greeting-name="greetingName"
            :headline-text="headlineText"
            :total-hours="totalHours"
            :unique-track-count="uniqueTrackCount"
            :top-genre="topGenre"
        />

        <div class="flex items-center justify-end">
            <div class="flex items-center gap-2">
                <span
                    v-if="currentSyncStatus.isRunning"
                    class="rounded-md border border-accent/40 bg-accent/10 px-2 py-1 text-[11px] font-medium text-accent"
                >
                    Syncing {{ currentSyncStatus.completed }}/{{ currentSyncStatus.total }} · {{ currentSyncStatus.progress }}%
                </span>
                <span
                    v-else-if="currentSyncStatus.hasFailure"
                    class="rounded-md border border-destructive/40 bg-destructive/10 px-2 py-1 text-[11px] font-medium text-destructive"
                >
                    Sync failed
                </span>
                <button
                    type="button"
                    class="rounded-lg border border-border bg-card px-3 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="isRefreshing || currentSyncStatus.isRunning"
                    @click="refreshAllInsights"
                >
                    {{ isRefreshing ? 'Refreshing…' : 'Refresh data' }}
                </button>
                <PeriodSelector :current="period" :loading="isReloading" />
            </div>
        </div>

        <section class="grid gap-4 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <ActivityChart
                    :points="activitySeries"
                    trend-label="Recent activity"
                />
            </div>
            <GenreChart :genres="genreMix" />
        </section>

        <section>
            <SectionHeader
                title="Top Tracks"
                :description="periodDescription"
            />
            <div class="grid gap-6 lg:grid-cols-3">
                <DashboardTopTracksSection
                    :period-description="periodDescription"
                    :top-tracks-preview="topTracksPreview"
                    :is-playing-track="isPlayingTrack"
                    :handle-play="handlePlay"
                    :skeleton-count="SKELETON_COUNT"
                />

                <DashboardRecommendationsPanel
                    :recommendation-tracks="recommendationTracks"
                    :handle-play="handlePlay"
                />
            </div>
        </section>

        <DashboardTopArtistsSection
            :period-description="periodDescription"
            :top-artists-preview="topArtistsPreview"
        />

        <DashboardRecentPlaysSection
            :recently-played-tracks="recentlyPlayedTracks"
            :handle-play="handlePlay"
        />
    </div>
</template>
