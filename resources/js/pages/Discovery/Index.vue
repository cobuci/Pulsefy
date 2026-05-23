<script setup lang="ts">
import { router, useHttp, usePoll } from '@inertiajs/vue3';
import {
    Heart,
    Pause,
    Play,
    RefreshCw,
    SkipForward,
    Sparkles,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import {
    like as discoveryLike,
    skip as discoverySkip,
    ignore as discoveryIgnore,
    retry as discoveryRetry,
} from '@/actions/App/Http/Controllers/Discovery/DiscoveryController';
import { usePlayer } from '@/composables/usePlayer';
import { useSwipe } from '@/composables/useSwipe';
import { index as discoveryIndex } from '@/routes/discovery';

interface Recommendation {
    spotify_id: string;
    name: string;
    artist: string;
    album: string;
    image_url: string | null;
    match_score: number;
}

type DiscoveryStatus = 'generating' | 'ready' | 'failed' | 'empty';

const POLL_INTERVAL_MS = 4000;
const MAX_POLL_ATTEMPTS = 45;

const props = defineProps<{
    status: DiscoveryStatus;
    recommendations: Recommendation[];
    error_message: string | null;
    can_retry: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Discovery',
                href: discoveryIndex().url,
            },
        ],
    },
});

const pollAttempts = ref(0);
const pollTimedOut = ref(false);

const { start: startPolling, stop: stopPolling } = usePoll(
    POLL_INTERVAL_MS,
    {
        only: ['status', 'recommendations', 'error_message', 'can_retry'],
        onFinish: () => {
            if (props.status !== 'generating') {
                return;
            }

            pollAttempts.value++;

            if (pollAttempts.value >= MAX_POLL_ATTEMPTS) {
                pollTimedOut.value = true;
                stopPolling();
            }
        },
    },
    { autoStart: false },
);

watch(
    () => props.status,
    (status) => {
        if (status === 'generating') {
            pollAttempts.value = 0;
            pollTimedOut.value = false;
            startPolling();
        } else {
            stopPolling();
        }
    },
    { immediate: true },
);

const showGenerating = computed(
    () => props.status === 'generating' && !pollTimedOut.value,
);
const showFailed = computed(
    () => props.status === 'failed' || pollTimedOut.value,
);
const showEmpty = computed(() => props.status === 'empty');
const showReady = computed(() => props.status === 'ready');

const displayError = computed(() => {
    if (pollTimedOut.value) {
        return 'Recommendation generation is taking longer than expected. Please try again.';
    }

    return (
        props.error_message ??
        'Recommendation generation failed. Please try again.'
    );
});

const retrying = ref(false);

function retryGeneration() {
    if (!props.can_retry && !pollTimedOut.value) {
        return;
    }

    retrying.value = true;
    router.post(
        discoveryRetry.url(),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                retrying.value = false;
                pollAttempts.value = 0;
                pollTimedOut.value = false;
            },
        },
    );
}
const { playTrack, pausePlayback, nowPlayingData, isPlayingTrack } =
    usePlayer();

function ignore() {
    const track = currentTrack.value;

    if (!track || processing.value) {
        return;
    }

    processing.value = true;
    ignoreHttp.spotify_id = track.spotify_id;
    ignoreHttp.post(discoveryIgnore.url(), {
        onSuccess: () => {
            currentIndex.value++;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}

const currentIndex = ref(0);
const cardRef = ref<HTMLElement | null>(null);
const stats = ref({ saved: 0, skipped: 0 });
const processing = ref(false);

const currentTrack = computed(
    () => props.recommendations[currentIndex.value] ?? null,
);

const stackEmpty = computed(
    () =>
        showReady.value &&
        (props.recommendations.length === 0 ||
            currentIndex.value >= props.recommendations.length),
);

const isCurrentTrackPlaying = computed(() => {
    if (!currentTrack.value) {
        return false;
    }

    return (
        isPlayingTrack.value(currentTrack.value.spotify_id) &&
        nowPlayingData.value?.is_playing === true
    );
});

function togglePlayback() {
    if (!currentTrack.value) {
        return;
    }

    if (isCurrentTrackPlaying.value) {
        pausePlayback();
    } else {
        playTrack(`spotify:track:${currentTrack.value.spotify_id}`);
    }
}

watch(currentTrack, (track) => {
    if (track) {
        playTrack(`spotify:track:${track.spotify_id}`);
    }
});

function commit(dir: 'left' | 'right') {
    const track = currentTrack.value;

    if (!track || processing.value) {
        return;
    }

    processing.value = true;

    if (dir === 'right') {
        Object.assign(likeHttp, {
            spotify_id: track.spotify_id,
            name: track.name,
            artist: track.artist,
            album: track.album,
            album_art: track.image_url,
        });
        likeHttp.post(discoveryLike.url(), {
            onSuccess: () => {
                stats.value.saved++;
                currentIndex.value++;
            },
            onFinish: () => {
                processing.value = false;
            },
        });
    } else {
        skipHttp.spotify_id = track.spotify_id;
        skipHttp.post(discoverySkip.url(), {
            onSuccess: () => {
                stats.value.skipped++;
                currentIndex.value++;
            },
            onFinish: () => {
                processing.value = false;
            },
        });
    }
}

const likeHttp = useHttp({
    spotify_id: '',
    name: '',
    artist: '',
    album: '',
    album_art: null as string | null,
});

const skipHttp = useHttp({ spotify_id: '' });
const ignoreHttp = useHttp({ spotify_id: '' });

const swipe = useSwipe(cardRef, {
    threshold: 80,
    onSwipeLeft: () => commit('left'),
    onSwipeRight: () => commit('right'),
});

watch(cardRef, () => {
    swipe.detach();
    swipe.attach();
});

onMounted(() => {
    swipe.attach();
    window.addEventListener('keydown', onKey);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKey);
});

function onKey(e: KeyboardEvent) {
    const tag = (e.target as HTMLElement)?.tagName;

    if (tag === 'INPUT' || tag === 'TEXTAREA') {
        return;
    }

    if (e.key === 'ArrowRight') {
        e.preventDefault();
        commit('right');
    } else if (e.key === 'ArrowLeft') {
        e.preventDefault();
        commit('left');
    }
}

const cardStyle = computed(() => ({
    transform: `translateX(${swipe.deltaX.value}px) rotate(${swipe.deltaX.value * 0.04}deg)`,
    transition: swipe.isDragging.value ? 'none' : 'transform 0.3s ease',
}));

const likeOverlayOpacity = computed(() =>
    Math.min(1, Math.max(0, (swipe.deltaX.value - 40) / 100)),
);

const skipOverlayOpacity = computed(() =>
    Math.min(1, Math.max(0, (-swipe.deltaX.value - 40) / 100)),
);
</script>

<template>
    <div class="mx-auto max-w-5xl px-6 py-10">
        <div class="mb-8 text-center">
            <div
                class="inline-flex items-center gap-1.5 text-xs font-semibold tracking-[0.2em] text-accent uppercase"
            >
                <Sparkles class="h-3 w-3" />
                Discover
            </div>
            <h1 class="mt-3 text-4xl font-bold sm:text-5xl">
                Find your next obsession
            </h1>
            <p class="mt-3 text-sm text-muted-foreground">
                Swipe right to save · left to skip ·
                <kbd
                    class="mx-0.5 rounded bg-secondary px-1.5 py-0.5 text-foreground/80"
                    >←</kbd
                >
                <kbd
                    class="mx-0.5 rounded bg-secondary px-1.5 py-0.5 text-foreground/80"
                    >→</kbd
                >
                arrow keys
            </p>
        </div>

        <div v-if="showGenerating" class="mx-auto w-full max-w-[380px]">
            <div
                class="aspect-[3/4] animate-pulse rounded-3xl border border-border bg-card"
            />
            <div class="mt-4 text-center">
                <p
                    class="flex items-center justify-center gap-2 text-sm text-muted-foreground"
                >
                    <Sparkles class="h-3.5 w-3.5 animate-pulse text-accent" />
                    Generating your recommendations…
                </p>
            </div>
            <div class="mt-6 flex items-center justify-center gap-5">
                <div
                    class="h-14 w-14 animate-pulse rounded-full border border-border bg-card"
                />
                <div
                    class="h-12 w-12 animate-pulse rounded-full border border-border bg-card"
                />
                <div
                    class="h-14 w-14 animate-pulse rounded-full border border-border bg-card"
                />
            </div>
        </div>

        <div
            v-else-if="showFailed"
            class="mx-auto grid aspect-[3/4] w-full max-w-[380px] place-items-center rounded-3xl border border-dashed border-border px-6 py-16 text-center"
        >
            <div>
                <Sparkles class="mx-auto mb-3 h-8 w-8 text-destructive" />
                <h3 class="text-xl font-bold">
                    Could not generate recommendations
                </h3>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{ displayError }}
                </p>
                <button
                    v-if="can_retry || pollTimedOut"
                    type="button"
                    class="mt-6 inline-flex cursor-pointer items-center gap-2 rounded-full bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground transition-opacity disabled:opacity-50"
                    :disabled="retrying"
                    @click="retryGeneration"
                >
                    <RefreshCw
                        class="h-4 w-4"
                        :class="{ 'animate-spin': retrying }"
                    />
                    Try again
                </button>
            </div>
        </div>

        <div
            v-else-if="showEmpty"
            class="mx-auto grid aspect-[3/4] w-full max-w-[380px] place-items-center rounded-3xl border border-dashed border-border px-6 py-16 text-center"
        >
            <div>
                <Sparkles class="mx-auto mb-3 h-8 w-8 text-accent" />
                <h3 class="text-xl font-bold">No recommendations today</h3>
                <p class="mt-2 text-sm text-muted-foreground">
                    We could not find new tracks for your taste profile. Sync
                    your Spotify listening data and try again.
                </p>
                <button
                    v-if="can_retry"
                    type="button"
                    class="mt-6 inline-flex cursor-pointer items-center gap-2 rounded-full bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground transition-opacity disabled:opacity-50"
                    :disabled="retrying"
                    @click="retryGeneration"
                >
                    <RefreshCw
                        class="h-4 w-4"
                        :class="{ 'animate-spin': retrying }"
                    />
                    Try again
                </button>
            </div>
        </div>

        <div v-else-if="showReady">
            <div
                class="relative mx-auto mb-8 aspect-[3/4] w-full max-w-[380px]"
            >
                <div
                    v-if="stackEmpty"
                    class="absolute inset-0 grid place-items-center rounded-3xl border border-dashed border-border px-6 text-center"
                >
                    <div>
                        <Sparkles
                            class="mx-auto mb-3 h-8 w-8 animate-pulse text-accent"
                        />
                        <h3 class="text-xl font-bold">Come back tomorrow ✨</h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                            You've gone through all of today's recommendations.
                            New ones will be ready tomorrow.
                        </p>
                    </div>
                </div>

                <div
                    v-else-if="currentTrack"
                    ref="cardRef"
                    :style="cardStyle"
                    class="absolute inset-0 cursor-grab touch-none overflow-hidden rounded-3xl border border-border bg-card select-none active:cursor-grabbing"
                >
                    <div class="absolute inset-0">
                        <img
                            v-if="currentTrack.image_url"
                            :src="currentTrack.image_url"
                            :alt="currentTrack.name"
                            class="absolute inset-0 h-full w-full object-cover"
                        />
                        <div
                            v-else
                            class="absolute inset-0 bg-gradient-to-br from-accent/30 to-secondary"
                        />
                        <div
                            class="absolute inset-0 bg-gradient-to-b from-transparent via-transparent to-background"
                        />
                    </div>

                    <div
                        class="absolute inset-0 rounded-3xl ring-1 ring-white/10 ring-inset"
                    />

                    <div
                        :style="{ opacity: likeOverlayOpacity }"
                        class="absolute top-6 left-6 rotate-[-12deg] rounded-md border-2 border-accent bg-accent/10 px-3 py-1.5 text-lg font-black tracking-widest text-accent backdrop-blur-md"
                    >
                        SAVE
                    </div>

                    <div
                        :style="{ opacity: skipOverlayOpacity }"
                        class="absolute top-6 right-6 rotate-[12deg] rounded-md border-2 border-destructive bg-destructive/10 px-3 py-1.5 text-lg font-black tracking-widest text-destructive backdrop-blur-md"
                    >
                        SKIP
                    </div>

                    <div
                        class="absolute top-4 left-4 flex items-center gap-1.5 rounded-full border border-accent/30 bg-background/40 px-2.5 py-1 backdrop-blur-md"
                    >
                        <Sparkles class="h-3 w-3 text-accent" />
                        <span
                            class="text-[11px] font-bold text-accent tabular-nums"
                        >
                            {{ currentTrack.match_score }}% match
                        </span>
                    </div>

                    <div class="absolute inset-x-0 bottom-0 space-y-1 p-6">
                        <h2 class="text-2xl leading-tight font-bold">
                            {{ currentTrack.name }}
                        </h2>
                        <p
                            v-if="currentTrack.artist"
                            class="text-sm font-medium text-foreground/80"
                        >
                            {{ currentTrack.artist }}
                        </p>
                        <div
                            class="flex items-center justify-between gap-3 pt-1"
                        >
                            <p
                                v-if="currentTrack.album"
                                class="truncate text-sm text-muted-foreground"
                            >
                                {{ currentTrack.album }}
                            </p>
                            <div v-else class="flex-1" />
                            <button
                                class="flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-full bg-background/40 text-foreground backdrop-blur-md transition-all hover:scale-110 hover:bg-background/70"
                                :aria-label="
                                    isCurrentTrackPlaying ? 'Pause' : 'Play'
                                "
                                @click.stop="togglePlayback"
                            >
                                <Pause
                                    v-if="isCurrentTrackPlaying"
                                    class="h-4 w-4"
                                    fill="currentColor"
                                />
                                <Play
                                    v-else
                                    class="h-4 w-4"
                                    fill="currentColor"
                                />
                            </button>
                        </div>
                    </div>
                </div>

                <template v-if="!stackEmpty && recommendations.length">
                    <div
                        v-for="offset in [1, 2]"
                        :key="offset"
                        class="pointer-events-none absolute inset-0 rounded-3xl border border-border bg-card"
                        :style="{
                            transform: `scale(${1 - offset * 0.05}) translateY(${offset * 14}px)`,
                            opacity: 1 - offset * 0.25,
                            zIndex: -offset,
                        }"
                    />
                </template>
            </div>

            <div class="flex items-center justify-center gap-5">
                <div class="group relative flex flex-col items-center">
                    <span
                        class="pointer-events-none absolute -top-9 rounded bg-popover px-2 py-1 text-xs text-popover-foreground opacity-0 transition-opacity group-hover:opacity-100"
                    >
                        Dislike
                    </span>
                    <button
                        :disabled="stackEmpty || processing"
                        aria-label="Skip"
                        class="grid h-14 w-14 cursor-pointer place-items-center rounded-full border border-border bg-card transition-all hover:scale-105 hover:border-destructive/60 hover:text-destructive disabled:opacity-40 disabled:hover:scale-100"
                        @click="commit('left')"
                    >
                        <X class="h-6 w-6" />
                    </button>
                </div>

                <div class="group relative flex flex-col items-center">
                    <span
                        class="pointer-events-none absolute -top-9 rounded bg-popover px-2 py-1 text-xs text-popover-foreground opacity-0 transition-opacity group-hover:opacity-100"
                    >
                        Ignore
                    </span>
                    <button
                        :disabled="stackEmpty || processing"
                        aria-label="Ignore"
                        class="grid h-12 w-12 cursor-pointer place-items-center rounded-full border border-border bg-card transition-all hover:scale-105 hover:border-muted-foreground/60 hover:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-40"
                        @click="ignore"
                    >
                        <SkipForward class="h-5 w-5" />
                    </button>
                </div>

                <div class="group relative flex flex-col items-center">
                    <span
                        class="pointer-events-none absolute -top-9 rounded bg-popover px-2 py-1 text-xs text-popover-foreground opacity-0 transition-opacity group-hover:opacity-100"
                    >
                        Save
                    </span>
                    <button
                        :disabled="stackEmpty || processing"
                        aria-label="Save"
                        class="grid h-14 w-14 cursor-pointer place-items-center rounded-full bg-primary text-primary-foreground shadow-lg transition-all hover:scale-105 disabled:opacity-40 disabled:hover:scale-100"
                        @click="commit('right')"
                    >
                        <Heart class="h-6 w-6" fill="currentColor" />
                    </button>
                </div>
            </div>

            <div
                class="mt-8 flex items-center justify-center gap-6 text-xs text-muted-foreground"
            >
                <span>
                    Saved:
                    <span class="font-semibold text-accent tabular-nums">{{
                        stats.saved
                    }}</span>
                </span>
                <span class="opacity-50">·</span>
                <span>
                    Skipped:
                    <span class="font-semibold tabular-nums">{{
                        stats.skipped
                    }}</span>
                </span>
            </div>
        </div>
    </div>
</template>
