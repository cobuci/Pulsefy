<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { usePlayer } from '@/composables/usePlayer';
import { useSpotifyDevices } from '@/composables/useSpotifyDevices';
import { useSpotifyLyrics } from '@/composables/useSpotifyLyrics';
import { useSpotifyWebPlayer } from '@/composables/useSpotifyWebPlayer';
import IconDevice from '@/components/icons/IconDevice.vue';
import IconKaraoke from '@/components/icons/IconKaraoke.vue';
import IconMusicNote from '@/components/icons/IconMusicNote.vue';
import IconNext from '@/components/icons/IconNext.vue';
import IconPause from '@/components/icons/IconPause.vue';
import IconPlay from '@/components/icons/IconPlay.vue';
import IconPrevious from '@/components/icons/IconPrevious.vue';
import IconRefresh from '@/components/icons/IconRefresh.vue';
import { next, pause, play, previous, seek } from '@/routes/player';
import { getCsrfToken } from '@/utils/csrf';
import { formatDuration } from '@/utils/format';

const POLL_INTERVAL = 30_000;
const PREVIOUS_RESTART_THRESHOLD_MS = 3_000;

const { nowPlayingData, fetchNowPlaying } = usePlayer();
const data = computed(() => nowPlayingData.value);
const hasTrack = computed(() => !!data.value?.track);
const isPlaying = computed(() => data.value?.is_playing ?? false);
const isBusy = ref(false);
const activeTrackId = ref<string | null>(null);
const playerRootRef = ref<HTMLElement | null>(null);
const localStatus = ref('');

const progressPct = ref(0);
let progressTimer: ReturnType<typeof setInterval> | null = null;
let pollTimer: ReturnType<typeof setInterval> | null = null;

const progressMs = computed(() => {
    if (!data.value?.track) {
        return 0;
    }

    return (progressPct.value / 100) * data.value.track.duration_ms;
});

const webPlayer = useSpotifyWebPlayer(
    (message) => {
        localStatus.value = message;
    },
    () => {
        void devices.refreshDevices();
    },
);

const devices = useSpotifyDevices(
    webPlayer.localPlayerReady,
    webPlayer.localDeviceId,
    webPlayer.localPlayer,
    (message) => {
        localStatus.value = message;
    },
    () => isPlaying.value,
    () => void fetchNowPlaying(),
);

const lyrics = useSpotifyLyrics(
    () => data.value?.track,
    () => progressMs.value,
);

function handleDocumentPointerDown(event: PointerEvent) {
    if (!lyrics.lyricsOpen.value || !playerRootRef.value) {
        return;
    }

    if (
        event.target instanceof Node &&
        !playerRootRef.value.contains(event.target)
    ) {
        lyrics.lyricsOpen.value = false;
    }
}

watch(data, (val) => {
    if (progressTimer) {
        clearInterval(progressTimer);
        progressTimer = null;
    }

    if (!val?.track) {
        progressPct.value = 0;
        activeTrackId.value = null;
        lyrics.lyricsOpen.value = false;

        return;
    }

    if (activeTrackId.value !== val.track.id) {
        activeTrackId.value = val.track.id;
        lyrics.lyricLineRefs.value = [];
        lyrics.fetchLyricsForCurrentTrack();
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

onMounted(() => {
    fetchNowPlaying();
    pollTimer = setInterval(fetchNowPlaying, POLL_INTERVAL);
    document.addEventListener('pointerdown', handleDocumentPointerDown);
    void webPlayer.initLocalPlayer((state) => {
        webPlayer.syncFromLocalState(
            state,
            () => void fetchNowPlaying(),
            () => data.value?.track?.id,
            (pct) => {
                progressPct.value = pct;
            },
            () => data.value?.track?.duration_ms ?? 0,
        );
    });
    void devices.refreshDevices();
});

onUnmounted(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
    }

    if (progressTimer) {
        clearInterval(progressTimer);
    }

    document.removeEventListener('pointerdown', handleDocumentPointerDown);
    webPlayer.disconnect();
});

async function sendCommand(url: string, body?: Record<string, unknown>) {
    if (isBusy.value) {
        return;
    }

    isBusy.value = true;

    try {
        await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: body ? JSON.stringify(body) : undefined,
        });
        await new Promise((r) => setTimeout(r, 600));
        await fetchNowPlaying();
    } finally {
        isBusy.value = false;
    }
}

function onPlay() {
    if (webPlayer.localPlaybackActive.value && webPlayer.localPlayer.value) {
        void webPlayer.localPlayer.value.resume();

        return;
    }

    sendCommand(play.url());
}

function onPause() {
    if (webPlayer.localPlaybackActive.value && webPlayer.localPlayer.value) {
        void webPlayer.localPlayer.value.pause();

        return;
    }

    sendCommand(pause.url());
}

function onNext() {
    if (webPlayer.localPlaybackActive.value && webPlayer.localPlayer.value) {
        void webPlayer.localPlayer.value.nextTrack();

        return;
    }

    sendCommand(next.url());
}

function onPrevious() {
    if (progressMs.value > PREVIOUS_RESTART_THRESHOLD_MS) {
        if (
            webPlayer.localPlaybackActive.value &&
            webPlayer.localPlayer.value
        ) {
            void webPlayer.localPlayer.value.seek(0);
        } else {
            sendCommand(seek.url(), { position_ms: 0 });
        }

        return;
    }

    if (webPlayer.localPlaybackActive.value && webPlayer.localPlayer.value) {
        void webPlayer.localPlayer.value.previousTrack();

        return;
    }

    sendCommand(previous.url());
}
</script>

<template>
    <div ref="playerRootRef" class="fixed right-0 bottom-0 left-0 z-50">
        <Transition
            enter-active-class="transition-transform duration-500 ease-out"
            enter-from-class="translate-y-full"
            enter-to-class="translate-y-0"
            leave-active-class="transition-transform duration-300 ease-in"
            leave-from-class="translate-y-0"
            leave-to-class="translate-y-full"
        >
            <div
                v-if="hasTrack && lyrics.lyricsOpen.value"
                class="mx-auto max-h-[70vh] max-w-7xl overflow-hidden rounded-t-xl border border-b-0 border-border bg-card/95 px-4 pb-4 backdrop-blur-md"
            >
                <div class="flex items-center justify-between py-3">
                    <p
                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        Lyrics
                    </p>
                    <button
                        type="button"
                        class="text-xs text-muted-foreground transition-colors hover:text-foreground"
                        @click="lyrics.lyricsOpen.value = false"
                    >
                        Close
                    </button>
                </div>

                <div
                    class="h-[55vh] overflow-y-auto rounded-md bg-muted/20 px-2 py-2"
                >
                    <div v-if="lyrics.lyricsHttp.processing" class="space-y-2">
                        <div class="h-5 w-2/3 animate-pulse rounded bg-muted" />
                        <div class="h-5 w-1/2 animate-pulse rounded bg-muted" />
                        <div class="h-5 w-3/4 animate-pulse rounded bg-muted" />
                    </div>

                    <template
                        v-else-if="lyrics.lyricsData.value?.type === 'synced'"
                    >
                        <p
                            v-for="(line, index) in lyrics.parsedSyncedLyrics
                                .value"
                            :key="`${line.timeMs}-${index}`"
                            :ref="
                                (element) =>
                                    lyrics.setLyricLineRef(element, index)
                            "
                            :class="[
                                'w-full rounded-md px-3 py-2 text-sm transition-all hover:bg-muted/60',
                                index === lyrics.activeLyricLineIndex.value
                                    ? 'bg-muted/60 font-semibold text-foreground'
                                    : 'text-muted-foreground',
                            ]"
                        >
                            {{ line.text || '♪' }}
                        </p>
                    </template>

                    <pre
                        v-else-if="lyrics.lyricsData.value?.type === 'plain'"
                        class="text-sm leading-6 whitespace-pre-wrap text-foreground"
                        >{{ lyrics.lyricsData.value?.lyrics }}</pre
                    >

                    <div
                        v-else
                        class="flex h-full min-h-56 items-center justify-center"
                    >
                        <div
                            class="mx-auto flex max-w-md flex-col items-center text-center"
                        >
                            <div
                                class="mb-4 flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground"
                            >
                                <IconMusicNote class="size-5" />
                            </div>

                            <p class="text-base font-semibold text-foreground">
                                No lyrics found for this track
                            </p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Sometimes metadata differs. You can try
                                searching again.
                            </p>

                            <button
                                type="button"
                                :disabled="lyrics.lyricsHttp.processing"
                                class="mt-5 inline-flex items-center gap-2 rounded-md border border-border px-3 py-2 text-sm font-medium text-foreground transition-colors hover:bg-muted disabled:opacity-50"
                                @click="lyrics.retryLyricsFetch()"
                            >
                                <IconRefresh class="size-4" />
                                Try again
                            </button>
                        </div>
                    </div>
                </div>

                <p class="pt-3 text-[11px] text-muted-foreground">
                    Lyrics provided by LRCLIB
                </p>
            </div>
        </Transition>

        <div class="border-t border-border bg-card/95 backdrop-blur-md">
            <div class="h-0.5 w-full bg-muted">
                <div
                    class="h-full bg-primary transition-[width] duration-500 ease-linear"
                    :style="{ width: `${progressPct}%` }"
                />
            </div>

            <div
                class="mx-auto grid h-[72px] max-w-7xl grid-cols-3 items-center px-4"
            >
                <!-- Left: track info -->
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    <template v-if="hasTrack">
                        <div class="relative shrink-0">
                            <img
                                v-if="data?.track?.album?.images?.[0]"
                                :src="data?.track?.album?.images?.[0]?.url"
                                :alt="data?.track?.album?.name"
                                class="size-10 rounded-md object-cover shadow-sm"
                            />
                            <div v-else class="size-10 rounded-md bg-muted" />

                            <span
                                v-if="isPlaying"
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

                        <div class="min-w-0">
                            <a
                                :href="data?.track?.external_urls?.spotify"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="block truncate text-sm font-semibold text-foreground transition-colors hover:text-primary"
                            >
                                {{ data?.track?.name }}
                            </a>
                            <p class="truncate text-xs text-muted-foreground">
                                {{
                                    (data?.track?.artists ?? [])
                                        .map((a) => a.name)
                                        .join(', ')
                                }}
                            </p>
                        </div>
                    </template>

                    <p v-else class="text-xs text-muted-foreground">
                        Nothing playing
                    </p>
                </div>

                <!-- Center: controls + time stacked -->
                <div
                    class="flex flex-col items-center justify-center gap-0.5 justify-self-center"
                >
                    <div class="flex items-center gap-1">
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
                            <IconPrevious class="size-5" />
                        </button>

                        <!-- Play / Pause -->
                        <button
                            type="button"
                            :title="isPlaying ? 'Pause' : 'Play'"
                            :disabled="isBusy"
                            :class="[
                                'flex size-10 items-center justify-center rounded-full bg-primary text-primary-foreground shadow transition-all hover:scale-105 active:scale-95',
                                isBusy
                                    ? 'cursor-not-allowed opacity-60'
                                    : 'cursor-pointer',
                            ]"
                            @click="isPlaying ? onPause() : onPlay()"
                        >
                            <IconPause v-if="isPlaying" class="size-5" />
                            <IconPlay v-else class="size-5 translate-x-[1px]" />
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
                            <IconNext class="size-5" />
                        </button>
                    </div>

                    <!-- Time centered below the 3 controls -->
                    <div
                        class="flex w-full items-center justify-center gap-1 text-[10px] text-muted-foreground tabular-nums"
                    >
                        <span>{{ formatDuration(progressMs) }}</span>
                        <span class="opacity-40">/</span>
                        <span>{{
                            hasTrack && data?.track?.duration_ms
                                ? formatDuration(data.track.duration_ms)
                                : '--:--'
                        }}</span>
                    </div>
                </div>

                <!-- Right: device picker + lyrics -->
                <div
                    class="flex items-center justify-end gap-2 justify-self-end"
                >
                    <!-- Device picker -->
                    <div class="relative hidden md:block">
                        <button
                            type="button"
                            title="Choose playback device"
                            :disabled="
                                devices.devicesHttp.processing ||
                                devices.transferBusy.value
                            "
                            :class="[
                                'flex size-8 items-center justify-center rounded-md transition-colors disabled:opacity-50',
                                devices.devicesOpen.value
                                    ? 'text-primary'
                                    : 'text-muted-foreground hover:text-foreground',
                            ]"
                            @click="
                                devices.devicesOpen.value =
                                    !devices.devicesOpen.value;
                                if (devices.devicesOpen.value)
                                    devices.refreshDevices();
                            "
                        >
                            <IconDevice class="size-4" />
                        </button>

                        <div
                            v-if="devices.devicesOpen.value"
                            class="absolute right-0 bottom-full z-20 mb-2 w-64 rounded-md border border-border bg-card p-2 shadow-lg"
                        >
                            <p
                                class="mb-2 text-[11px] font-medium text-muted-foreground"
                            >
                                Select playback device
                            </p>

                            <div class="max-h-52 space-y-1 overflow-auto pr-1">
                                <button
                                    v-for="device in devices.selectableDevices
                                        .value"
                                    :key="device.id ?? device.name"
                                    type="button"
                                    class="flex w-full items-center justify-between rounded px-2 py-1 text-left text-xs transition-colors hover:bg-muted"
                                    :class="
                                        devices.selectedDeviceId.value ===
                                        device.id
                                            ? 'bg-muted'
                                            : ''
                                    "
                                    :disabled="
                                        devices.transferBusy.value || !device.id
                                    "
                                    @click="
                                        devices.selectedDeviceId.value =
                                            device.id ?? '';
                                        devices.onTransferToSelectedDevice();
                                    "
                                >
                                    <span class="truncate">{{
                                        device.name
                                    }}</span>
                                    <span
                                        class="text-[10px] text-muted-foreground"
                                        >{{ device.type }}</span
                                    >
                                </button>

                                <p
                                    v-if="
                                        !devices.selectableDevices.value
                                            .length &&
                                        !webPlayer.localPlayerReady.value
                                    "
                                    class="px-2 py-1 text-[11px] text-muted-foreground"
                                >
                                    No available devices.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Lyrics (microphone icon) -->
                    <button
                        type="button"
                        :disabled="lyrics.lyricsHttp.processing"
                        :title="
                            lyrics.lyricsOpen.value
                                ? 'Hide lyrics'
                                : 'Show lyrics'
                        "
                        :class="[
                            'flex size-8 shrink-0 items-center justify-center rounded-md transition-colors disabled:opacity-50',
                            lyrics.lyricsOpen.value
                                ? 'text-primary'
                                : 'text-muted-foreground hover:text-foreground',
                        ]"
                        @click="
                            lyrics.lyricsOpen.value = !lyrics.lyricsOpen.value
                        "
                    >
                        <IconKaraoke class="size-4" />
                    </button>
                </div>
            </div>

            <p
                v-if="localStatus && hasTrack"
                class="mx-auto max-w-7xl px-4 pb-2 text-[11px] text-muted-foreground"
            >
                {{ localStatus }}
            </p>
        </div>
    </div>
</template>
