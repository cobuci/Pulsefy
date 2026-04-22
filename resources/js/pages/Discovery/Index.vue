<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { Heart, RotateCcw, Sparkles, X } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useSwipe } from '@/composables/useSwipe';
import LikeController from '@/actions/App/Http/Controllers/Discovery/LikeController';
import SkipController from '@/actions/App/Http/Controllers/Discovery/SkipController';
import { index as discoveryIndex } from '@/routes/discovery';

interface Recommendation {
    spotify_id: string;
    name: string;
    artist: string;
    album: string;
    image_url: string | null;
    match_score: number;
    preview_url: string | null;
}

const props = defineProps<{
    recommendations?: Recommendation[];
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

const currentIndex = ref(0);
const cardRef = ref<HTMLElement | null>(null);
const stats = ref({ saved: 0, skipped: 0 });
const processing = ref(false);

const likeHttp = useHttp({
    spotify_id: '',
    name: '',
    artist: '',
    album: '',
    album_art: null as string | null,
});

const skipHttp = useHttp({ spotify_id: '' });

const currentTrack = computed(() =>
    props.recommendations ? props.recommendations[currentIndex.value] ?? null : null,
);

const stackEmpty = computed(
    () =>
        props.recommendations !== undefined &&
        (props.recommendations.length === 0 || currentIndex.value >= props.recommendations.length),
);

const belowMinimum = computed(
    () =>
        props.recommendations !== undefined &&
        props.recommendations.length > 0 &&
        props.recommendations.length < 20,
);

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

onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => window.removeEventListener('keydown', onKey));

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

        <div
            v-if="belowMinimum"
            class="bg-accent/10 border-accent/30 mb-6 rounded-xl border px-4 py-3 text-sm"
        >
            <span class="text-accent font-semibold">Limited recommendations available.</span>
            <span class="text-muted-foreground ml-1">
                Showing {{ recommendations?.length }} tracks. Listen more on Spotify to unlock a fuller
                card deck.
            </span>
        </div>

        <div v-if="recommendations === undefined" class="mx-auto w-full max-w-[380px]">
            <div class="bg-card border-border aspect-[3/4] animate-pulse rounded-3xl border" />
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

                    <div class="absolute inset-x-0 top-0 h-[55%] overflow-hidden">
                        <img
                            v-if="currentTrack.image_url"
                            :src="currentTrack.image_url"
                            :alt="currentTrack.name"
                            class="h-full w-full object-cover"
                        />
                        <div class="from-transparent to-background/95 absolute inset-0 bg-gradient-to-b" />
                        <div class="bg-background/40 border-accent/30 absolute top-4 left-4 flex items-center gap-1.5 rounded-full border px-2.5 py-1 backdrop-blur-md">
                            <Sparkles class="text-accent h-3 w-3" />
                            <span class="text-accent text-[11px] font-bold tabular-nums">
                                {{ currentTrack.match_score }}% match
                            </span>
                        </div>
                    </div>

                    <div class="absolute inset-x-0 bottom-0 space-y-3 p-6">
                        <h2 class="text-2xl font-bold leading-tight">{{ currentTrack.name }}</h2>
                        <p class="text-muted-foreground text-sm">
                            {{ currentTrack.artist }}
                            <span v-if="currentTrack.album"> · {{ currentTrack.album }}</span>
                        </p>
                    </div>
                </div>

                <template v-if="!stackEmpty && recommendations">
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
                <button
                    :disabled="stackEmpty || processing"
                    aria-label="Skip"
                    class="border-border bg-card hover:border-destructive/60 hover:text-destructive grid h-14 w-14 cursor-pointer place-items-center rounded-full border transition-all hover:scale-105 disabled:opacity-40 disabled:hover:scale-100"
                    @click="commit('left')"
                >
                    <X class="h-6 w-6" />
                </button>

                <button
                    :disabled="true"
                    aria-label="Undo"
                    class="border-border bg-card grid h-12 w-12 place-items-center rounded-full border transition-all disabled:cursor-not-allowed disabled:opacity-40"
                >
                    <RotateCcw class="h-5 w-5" />
                </button>

                <button
                    :disabled="stackEmpty || processing"
                    aria-label="Save"
                    class="bg-primary text-primary-foreground grid h-14 w-14 cursor-pointer place-items-center rounded-full shadow-lg transition-all hover:scale-105 disabled:opacity-40 disabled:hover:scale-100"
                    @click="commit('right')"
                >
                    <Heart class="h-6 w-6" fill="currentColor" />
                </button>
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
