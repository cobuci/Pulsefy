<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowRight, Compass } from 'lucide-vue-next';
import {
    computed,
    defineAsyncComponent,
    onMounted,
    onUnmounted,
    ref,
} from 'vue';
import { index as discoveryIndex } from '@/actions/App/Http/Controllers/Discovery/DiscoveryController';
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
        listeningTimeLabel?: string;
        uniqueTracksCount?: number;
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

const { isPlayingTrack, playTrack, pausePlayback } = usePlayer();
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
    if (
        page.props.auth?.user?.id &&
        typeof window !== 'undefined' &&
        window.Echo
    ) {
        window.Echo.private(
            `App.Models.User.${page.props.auth.user.id}`,
        ).listen(
            '.Spotify.SyncStatusUpdated',
            (event: { status: typeof syncStatusRef.value }) => {
                syncStatusRef.value = event.status;

                if (!event.status.isRunning) {
                    router.reload({
                        only: [
                            'syncStatus',
                            'topTracks',
                            'topArtists',
                            'recentPlays',
                            'insights',
                        ],
                    });
                }
            },
        );
    }
});

onUnmounted(() => {
    if (
        page.props.auth?.user?.id &&
        typeof window !== 'undefined' &&
        window.Echo
    ) {
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
    return props.insights?.listeningTimeLabel ?? '0m';
});

const uniqueTrackCount = computed(() => props.insights?.uniqueTracksCount ?? 0);

const topGenre = computed(() => {
    const label =
        props.insights?.topGenre ??
        topArtistsPreview.value[0]?.genres?.[0] ??
        'Mixed';

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

async function handlePause(): Promise<void> {
    await pausePlayback();
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

        <Link
            :href="discoveryIndex().url"
            class="group relative block overflow-hidden rounded-2xl border border-accent/30 bg-gradient-to-br from-primary/15 via-accent/10 to-background p-6 transition-all hover:border-accent/60 hover:shadow-[0_0_24px_0_hsl(var(--accent)/0.15)] sm:p-8"
        >
            <div
                class="pointer-events-none absolute -top-12 -right-8 h-44 w-44 rounded-full bg-accent/20 blur-3xl"
            />
            <div
                class="relative flex flex-col gap-5 sm:flex-row sm:items-center"
            >
                <div
                    class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-primary to-accent shadow-[0_0_16px_0_hsl(var(--primary)/0.4)]"
                >
                    <Compass
                        class="h-7 w-7 text-primary-foreground"
                        :stroke-width="2"
                    />
                </div>
                <div class="min-w-0 flex-1">
                    <div
                        class="text-[10px] font-semibold tracking-[0.2em] text-accent uppercase"
                    >
                        New
                    </div>
                    <h3 class="mt-0.5 text-2xl font-bold">Discover Stack</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Swipe through fresh tracks tuned to your taste — save
                        the ones that hit.
                    </p>
                </div>
                <div
                    class="flex items-center gap-2 text-sm font-medium text-accent transition-all group-hover:gap-3"
                >
                    Start discovering
                    <ArrowRight class="h-4 w-4" />
                </div>
            </div>
        </Link>

        <div class="flex items-center justify-end">
            <div class="flex items-center gap-2">
                <span
                    v-if="currentSyncStatus.isRunning"
                    class="rounded-md border border-accent/40 bg-accent/10 px-2 py-1 text-[11px] font-medium text-accent"
                >
                    Syncing {{ currentSyncStatus.completed }}/{{
                        currentSyncStatus.total
                    }}
                    · {{ currentSyncStatus.progress }}%
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
                    :handle-pause="handlePause"
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
