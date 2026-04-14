<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';
import { next, pause, play, previous, shuffle } from '@/routes/player';
import { nowPlaying } from '@/routes/player';
import type { NowPlaying } from '@/types/spotify';

const POLL_INTERVAL = 30_000;

// ── State ────────────────────────────────────────────────────────────────────
const http = useHttp<NowPlaying | null>();
const data = computed(() => http.response ?? null);
const visible = computed(() => !!data.value?.track);
const isBusy = ref(false);

// ── Polling ──────────────────────────────────────────────────────────────────
async function fetchNowPlaying() {
    await http.get(nowPlaying.url());
}

fetchNowPlaying();
const pollTimer = setInterval(fetchNowPlaying, POLL_INTERVAL);

// ── Progress bar ─────────────────────────────────────────────────────────────
const progressPct = ref(0);
let progressTimer: ReturnType<typeof setInterval> | null = null;

watch(data, (val) => {
    if (progressTimer) {
        clearInterval(progressTimer);
        progressTimer = null;
    }

    if (!val?.track) {
        progressPct.value = 0;
        return;
    }

    const duration = val.track.duration_ms;
    progressPct.value = duration > 0 ? (val.progress_ms / duration) * 100 : 0;

    if (val.is_playing && duration > 0) {
        progressTimer = setInterval(() => {
            progressPct.value = Math.min(
                progressPct.value + (100 / duration) * 500,
                100,
            );
        }, 500);
    }
});

onUnmounted(() => {
    clearInterval(pollTimer);
    if (progressTimer) clearInterval(progressTimer);
});

// ── Controls ─────────────────────────────────────────────────────────────────
const csrfToken =
    document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content') ?? '';

async function sendCommand(url: string, body?: Record<string, unknown>) {
    if (isBusy.value) return;
    isBusy.value = true;
    try {
        await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: body ? JSON.stringify(body) : undefined,
        });
        // Refresh state after a short delay to let Spotify propagate the change
        await new Promise((r) => setTimeout(r, 600));
        await fetchNowPlaying();
    } finally {
        isBusy.value = false;
    }
}

function onPlay() {
    sendCommand(play.url());
}

function onPause() {
    sendCommand(pause.url());
}

function onNext() {
    sendCommand(next.url());
}

function onPrevious() {
    sendCommand(previous.url());
}

function onShuffle() {
    const newState = !data.value?.shuffle_state;
    sendCommand(shuffle.url(), { state: newState });
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function formatDuration(ms: number): string {
    const minutes = Math.floor(ms / 60000);
    const seconds = Math.floor((ms % 60000) / 1000);
    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
}

const progressMs = computed(() => {
    if (!data.value?.track) return 0;
    return (progressPct.value / 100) * data.value.track.duration_ms;
});
</script>

<template>
    <Transition
        enter-active-class="transition-transform duration-500 ease-out"
        enter-from-class="translate-y-full"
        enter-to-class="translate-y-0"
        leave-active-class="transition-transform duration-300 ease-in"
        leave-from-class="translate-y-0"
        leave-to-class="translate-y-full"
    >
        <div
            v-if="visible"
            class="fixed right-0 bottom-0 left-0 z-50 border-t border-border bg-card/95 backdrop-blur-md"
        >
            <!-- Progress bar -->
            <div class="h-0.5 w-full bg-muted">
                <div
                    class="h-full bg-primary transition-[width] duration-500 ease-linear"
                    :style="{ width: `${progressPct}%` }"
                />
            </div>

            <div
                class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:gap-4"
            >
                <!-- Album art -->
                <div class="relative shrink-0">
                    <img
                        v-if="data!.track.album.images[0]"
                        :src="data!.track.album.images[0].url"
                        :alt="data!.track.album.name"
                        class="size-10 rounded-md object-cover shadow-sm"
                    />
                    <div v-else class="size-10 rounded-md bg-muted" />

                    <span
                        v-if="data!.is_playing"
                        class="absolute -top-1 -right-1 flex size-3 items-center justify-center"
                    >
                        <span
                            class="absolute inline-flex size-3 animate-ping rounded-full bg-primary opacity-75"
                        />
                        <span
                            class="relative inline-flex size-2 rounded-full bg-primary"
                        />
                    </span>
                </div>

                <!-- Track info -->
                <div class="min-w-0 flex-1">
                    <a
                        :href="data!.track.external_urls.spotify"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="block truncate text-sm font-semibold text-foreground transition-colors hover:text-primary"
                    >
                        {{ data!.track.name }}
                    </a>
                    <p class="truncate text-xs text-muted-foreground">
                        {{ data!.track.artists.map((a) => a.name).join(', ') }}
                    </p>
                </div>

                <!-- Controls -->
                <div class="flex shrink-0 items-center gap-1">
                    <!-- Shuffle -->
                    <button
                        type="button"
                        title="Shuffle"
                        :disabled="isBusy"
                        :class="[
                            'flex size-8 items-center justify-center rounded-full transition-colors',
                            data!.shuffle_state
                                ? 'text-primary'
                                : 'text-muted-foreground hover:text-foreground',
                            isBusy
                                ? 'cursor-not-allowed opacity-40'
                                : 'cursor-pointer',
                        ]"
                        @click="onShuffle"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="size-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path
                                d="M2 18h1.4c1.3 0 2.5-.6 3.3-1.7l6.1-8.6c.7-1.1 2-1.7 3.3-1.7H22"
                            />
                            <path d="m19 15 3 3-3 3" />
                            <path d="M2 6h1.9c1.5 0 2.9.9 3.6 2.2" />
                            <path d="M22 6h-5.9c-1.3 0-2.6.7-3.3 1.8l-.5.8" />
                            <path d="m19 3 3 3-3 3" />
                        </svg>
                    </button>

                    <!-- Previous -->
                    <button
                        type="button"
                        title="Previous"
                        :disabled="isBusy"
                        :class="[
                            'flex size-8 items-center justify-center rounded-full text-foreground/80 transition-colors hover:text-foreground',
                            isBusy
                                ? 'cursor-not-allowed opacity-40'
                                : 'cursor-pointer',
                        ]"
                        @click="onPrevious"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="size-5"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                        >
                            <path d="M6 6h2v12H6zm3.5 6 8.5 6V6z" />
                        </svg>
                    </button>

                    <!-- Play / Pause -->
                    <button
                        type="button"
                        :title="data!.is_playing ? 'Pause' : 'Play'"
                        :disabled="isBusy"
                        :class="[
                            'flex size-10 items-center justify-center rounded-full bg-primary text-primary-foreground shadow transition-all hover:scale-105 active:scale-95',
                            isBusy
                                ? 'cursor-not-allowed opacity-60'
                                : 'cursor-pointer',
                        ]"
                        @click="data!.is_playing ? onPause() : onPlay()"
                    >
                        <!-- Pause icon -->
                        <svg
                            v-if="data!.is_playing"
                            xmlns="http://www.w3.org/2000/svg"
                            class="size-5"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                        >
                            <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z" />
                        </svg>
                        <!-- Play icon -->
                        <svg
                            v-else
                            xmlns="http://www.w3.org/2000/svg"
                            class="size-5 translate-x-0.5"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                        >
                            <path d="M8 5v14l11-7z" />
                        </svg>
                    </button>

                    <!-- Next -->
                    <button
                        type="button"
                        title="Next"
                        :disabled="isBusy"
                        :class="[
                            'flex size-8 items-center justify-center rounded-full text-foreground/80 transition-colors hover:text-foreground',
                            isBusy
                                ? 'cursor-not-allowed opacity-40'
                                : 'cursor-pointer',
                        ]"
                        @click="onNext"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="size-5"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                        >
                            <path d="M6 18l8.5-6L6 6v12zm8.5-6L23 6v12z" />
                        </svg>
                    </button>
                </div>

                <!-- Time (hidden on small screens) -->
                <div
                    class="hidden shrink-0 items-center gap-1 text-xs text-muted-foreground tabular-nums sm:flex"
                >
                    <span>{{ formatDuration(progressMs) }}</span>
                    <span class="opacity-40">/</span>
                    <span>{{ formatDuration(data!.track.duration_ms) }}</span>
                </div>
            </div>
        </div>
    </Transition>
</template>
