<script setup lang="ts">
import { useHttp, usePoll } from '@inertiajs/vue3';
import { Heart, Pause, Play, SkipForward, Sparkles, X } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { usePlayer } from '@/composables/usePlayer';
import { useSwipe } from '@/composables/useSwipe';
import LikeController from '@/actions/App/Http/Controllers/Discovery/LikeController';
import SkipController from '@/actions/App/Http/Controllers/Discovery/SkipController';
import IgnoreController from '@/actions/App/Http/Controllers/Discovery/IgnoreController';
import { index as discoveryIndex } from '@/routes/discovery';

interface Recommendation {
    spotify_id: string;
    name: string;
    artist: string;
    album: string;
    image_url: string | null;
    match_score: number;
}

const props = defineProps<{
    status: 'generating' | 'ready';
    recommendations: Recommendation[];
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

const { start: startPolling, stop: stopPolling } = usePoll(
    4000,
    { only: ['status', 'recommendations'] },
    { autoStart: false },
);

watch(
    () => props.status,
    (status) => {
        if (status === 'generating') {
            startPolling();
        } else {
            stopPolling();
        }
    },
    { immediate: true },
);

const { playTrack, pausePlayback, nowPlayingData, isPlayingTrack } = usePlayer();

function ignore() {
    const track = currentTrack.value;
    if (!track || processing.value) return;

    processing.value = true;
    ignoreHttp.spotify_id = track.spotify_id;
    ignoreHttp.post(IgnoreController.url(), {
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

const currentTrack = computed(() => props.recommendations[currentIndex.value] ?? null);

const stackEmpty = computed(
    () =>
        props.status === 'ready' &&
        (props.recommendations.length === 0 || currentIndex.value >= props.recommendations.length),
);

const isCurrentTrackPlaying = computed(() => {
    if (!currentTrack.value) return false;
    return isPlayingTrack.value(currentTrack.value.spotify_id) && nowPlayingData.value?.is_playing === true;
});

function togglePlayback() {
    if (!currentTrack.value) return;
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
    if (!track || processing.value) return;

    processing.value = true;

    if (dir === 'right') {
        Object.assign(likeHttp, {
            spotify_id: track.spotify_id,
            name: track.name,
            artist: track.artist,
            album: track.album,
            album_art: track.image_url,
        });
        likeHttp.post(LikeController.url(), {
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
        skipHttp.post(SkipController.url(), {
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
    if (tag === 'INPUT' || tag === 'TEXTAREA') return;
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
            <div class="text-accent inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-[0.2em]">
                <Sparkles class="h-3 w-3" />
                Discover
            </div>
            <h1 class="mt-3 text-4xl font-bold sm:text-5xl">Find your next obsession</h1>
            <p class="text-muted-foreground mt-3 text-sm">
                Swipe right to save · left to skip ·
                <kbd class="bg-secondary text-foreground/80 mx-0.5 rounded px-1.5 py-0.5">←</kbd>
                <kbd class="bg-secondary text-foreground/80 mx-0.5 rounded px-1.5 py-0.5">→</kbd>
                arrow keys
            </p>
        </div>

        <div v-if="status === 'generating'" class="mx-auto w-full max-w-[380px]">
            <div class="bg-card border-border aspect-[3/4] animate-pulse rounded-3xl border" />
            <div class="mt-4 text-center">
                <p class="text-muted-foreground flex items-center justify-center gap-2 text-sm">
                    <Sparkles class="text-accent h-3.5 w-3.5 animate-pulse" />
                    Generating your recommendations…
                </p>
            </div>
            <div class="mt-6 flex items-center justify-center gap-5">
                <div class="bg-card border-border h-14 w-14 animate-pulse rounded-full border" />
                <div class="bg-card border-border h-12 w-12 animate-pulse rounded-full border" />
                <div class="bg-card border-border h-14 w-14 animate-pulse rounded-full border" />
            </div>
        </div>

        <div v-else>
            <div class="relative mx-auto mb-8 aspect-[3/4] w-full max-w-[380px]">
                <div
                    v-if="stackEmpty"
                    class="border-border absolute inset-0 grid place-items-center rounded-3xl border border-dashed px-6 text-center"
                >
                    <div>
                        <Sparkles class="text-accent mx-auto mb-3 h-8 w-8 animate-pulse" />
                        <h3 class="text-xl font-bold">Come back tomorrow ✨</h3>
                        <p class="text-muted-foreground mt-1 text-sm">
                            You've gone through all of today's recommendations. New ones will be ready
                            tomorrow.
                        </p>
                    </div>
                </div>

                <div
                    v-else-if="currentTrack"
                    ref="cardRef"
                    :style="cardStyle"
                    class="border-border bg-card absolute inset-0 cursor-grab overflow-hidden rounded-3xl border select-none active:cursor-grabbing touch-none"
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
                            class="from-accent/30 to-secondary absolute inset-0 bg-gradient-to-br"
                        />
                        <div class="from-transparent to-background absolute inset-0 bg-gradient-to-b via-transparent" />
                    </div>

                    <div class="absolute inset-0 rounded-3xl ring-1 ring-inset ring-white/10" />

                    <div
                        :style="{ opacity: likeOverlayOpacity }"
                        class="border-accent text-accent bg-accent/10 absolute top-6 left-6 rotate-[-12deg] rounded-md border-2 px-3 py-1.5 text-lg font-black tracking-widest backdrop-blur-md"
                    >
                        SAVE
                    </div>

                    <div
                        :style="{ opacity: skipOverlayOpacity }"
                        class="border-destructive text-destructive bg-destructive/10 absolute top-6 right-6 rotate-[12deg] rounded-md border-2 px-3 py-1.5 text-lg font-black tracking-widest backdrop-blur-md"
                    >
                        SKIP
                    </div>

                    <div class="bg-background/40 border-accent/30 absolute top-4 left-4 flex items-center gap-1.5 rounded-full border px-2.5 py-1 backdrop-blur-md">
                        <Sparkles class="text-accent h-3 w-3" />
                        <span class="text-accent text-[11px] font-bold tabular-nums">
                            {{ currentTrack.match_score }}% match
                        </span>
                    </div>

                    <div class="absolute inset-x-0 bottom-0 space-y-1 p-6">
                        <h2 class="text-2xl font-bold leading-tight">{{ currentTrack.name }}</h2>
                        <p v-if="currentTrack.artist" class="text-foreground/80 text-sm font-medium">
                            {{ currentTrack.artist }}
                        </p>
                        <div class="flex items-center justify-between gap-3 pt-1">
                            <p v-if="currentTrack.album" class="text-muted-foreground truncate text-sm">
                                {{ currentTrack.album }}
                            </p>
                            <div v-else class="flex-1" />
                            <button
                                class="bg-background/40 hover:bg-background/70 text-foreground flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-full backdrop-blur-md transition-all hover:scale-110"
                                :aria-label="isCurrentTrackPlaying ? 'Pause' : 'Play'"
                                @click.stop="togglePlayback"
                            >
                                <Pause v-if="isCurrentTrackPlaying" class="h-4 w-4" fill="currentColor" />
                                <Play v-else class="h-4 w-4" fill="currentColor" />
                            </button>
                        </div>
                    </div>
                </div>

                <template v-if="!stackEmpty && recommendations.length">
                    <div
                        v-for="offset in [1, 2]"
                        :key="offset"
                        class="border-border bg-card pointer-events-none absolute inset-0 rounded-3xl border"
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
                    <span class="bg-popover text-popover-foreground pointer-events-none absolute -top-9 rounded px-2 py-1 text-xs opacity-0 transition-opacity group-hover:opacity-100">
                        Dislike
                    </span>
                    <button
                        :disabled="stackEmpty || processing"
                        aria-label="Skip"
                        class="border-border bg-card hover:border-destructive/60 hover:text-destructive grid h-14 w-14 cursor-pointer place-items-center rounded-full border transition-all hover:scale-105 disabled:opacity-40 disabled:hover:scale-100"
                        @click="commit('left')"
                    >
                        <X class="h-6 w-6" />
                    </button>
                </div>

                <div class="group relative flex flex-col items-center">
                    <span class="bg-popover text-popover-foreground pointer-events-none absolute -top-9 rounded px-2 py-1 text-xs opacity-0 transition-opacity group-hover:opacity-100">
                        Ignore
                    </span>
                    <button
                        :disabled="stackEmpty || processing"
                        aria-label="Ignore"
                        class="border-border bg-card hover:border-muted-foreground/60 hover:text-muted-foreground grid h-12 w-12 cursor-pointer place-items-center rounded-full border transition-all hover:scale-105 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="ignore"
                    >
                        <SkipForward class="h-5 w-5" />
                    </button>
                </div>

                <div class="group relative flex flex-col items-center">
                    <span class="bg-popover text-popover-foreground pointer-events-none absolute -top-9 rounded px-2 py-1 text-xs opacity-0 transition-opacity group-hover:opacity-100">
                        Save
                    </span>
                    <button
                        :disabled="stackEmpty || processing"
                        aria-label="Save"
                        class="bg-primary text-primary-foreground grid h-14 w-14 cursor-pointer place-items-center rounded-full shadow-lg transition-all hover:scale-105 disabled:opacity-40 disabled:hover:scale-100"
                        @click="commit('right')"
                    >
                        <Heart class="h-6 w-6" fill="currentColor" />
                    </button>
                </div>
            </div>

            <div class="text-muted-foreground mt-8 flex items-center justify-center gap-6 text-xs">
                <span>
                    Saved:
                    <span class="text-accent tabular-nums font-semibold">{{ stats.saved }}</span>
                </span>
                <span class="opacity-50">·</span>
                <span>
                    Skipped:
                    <span class="tabular-nums font-semibold">{{ stats.skipped }}</span>
                </span>
            </div>
        </div>
    </div>
</template>
