<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import {
    devices,
    deviceToken,
    lyrics,
    next,
    nowPlaying,
    pause,
    play,
    previous,
    shuffle,
    transfer,
} from '@/routes/player';
import type {
    LyricsResponse,
    NowPlaying,
    SpotifyDevice,
} from '@/types/spotify';
import type {
    SpotifyPlayer,
    SpotifyWebPlaybackState,
} from '@/types/spotify-web-playback';

const POLL_INTERVAL = 30_000;

const nowPlayingHttp = useHttp<NowPlaying | null>();
const lyricsHttp = useHttp<LyricsResponse>();
const deviceTokenHttp = useHttp<{ token: string }>();
const devicesHttp = useHttp<{ devices: SpotifyDevice[] }>();
const data = computed(() => nowPlayingHttp.response ?? null);
const lyricsData = computed(() => lyricsHttp.response ?? null);
const visible = computed(() => !!data.value?.track);
const isBusy = ref(false);
const lyricsOpen = ref(false);
const activeTrackId = ref<string | null>(null);
const lyricLineRefs = ref<HTMLElement[]>([]);
const playerRootRef = ref<HTMLElement | null>(null);
const localPlayer = ref<SpotifyPlayer | null>(null);
const localDeviceId = ref<string | null>(null);
const localPlayerReady = ref(false);
const localPlaybackActive = ref(false);
const localPlayerInitializing = ref(false);
const selectedDeviceId = ref<string>('');
const transferBusy = ref(false);
const localStatus = ref('');
const devicesOpen = ref(false);

function handleDocumentPointerDown(event: PointerEvent) {
    if (!lyricsOpen.value || !playerRootRef.value) {
        return;
    }

    if (
        event.target instanceof Node &&
        !playerRootRef.value.contains(event.target)
    ) {
        lyricsOpen.value = false;
    }
}

async function fetchNowPlaying() {
    try {
        await nowPlayingHttp.get(nowPlaying.url());
    } catch {}
}

async function fetchDeviceToken(): Promise<string | null> {
    try {
        await deviceTokenHttp.get(deviceToken.url());

        return deviceTokenHttp.response?.token ?? null;
    } catch {
        return null;
    }
}

async function refreshDevices() {
    try {
        await devicesHttp.get(devices.url());
        localStatus.value = '';

        const active = selectableDevices.value.find(
            (device) => device.is_active,
        );

        if (active?.id) {
            selectedDeviceId.value = active.id;

            return;
        }

        if (!selectedDeviceId.value && selectableDevices.value[0]?.id) {
            selectedDeviceId.value = selectableDevices.value[0].id;
        }
    } catch {
        localStatus.value = 'Unable to load Spotify devices.';
    }
}

const availableDevices = computed(() => devicesHttp.response?.devices ?? []);

const selectableDevices = computed(() => {
    const spotifyDevices = availableDevices.value.filter(
        (device) => device.id && !device.is_restricted,
    );

    if (!localPlayerReady.value || !localDeviceId.value) {
        return spotifyDevices;
    }

    const hasLocal = spotifyDevices.some(
        (device) => device.id === localDeviceId.value,
    );

    if (hasLocal) {
        return spotifyDevices;
    }

    return [
        {
            id: localDeviceId.value,
            is_active: false,
            is_private_session: false,
            is_restricted: false,
            name: 'Pulsefy Web Player',
            type: 'computer',
            volume_percent: null,
            supports_volume: true,
        },
        ...spotifyDevices,
    ];
});

async function fetchLyricsForCurrentTrack() {
    await fetchLyrics(false);
}

async function fetchLyrics(forceRefresh: boolean) {
    if (!data.value?.track) {
        return;
    }

    const artist = data.value.track.artists.map((item) => item.name).join(', ');

    try {
        await lyricsHttp.get(
            lyrics.url({
                query: {
                    track_id: data.value.track.id,
                    artist,
                    track_name: data.value.track.name,
                    album_name: data.value.track.album.name,
                    duration: data.value.track.duration_ms,
                    force_refresh: forceRefresh,
                },
            }),
        );
    } catch {}
}

function retryLyricsFetch() {
    void fetchLyrics(true);
}

let pollTimer: ReturnType<typeof setInterval> | null = null;

const progressPct = ref(0);
let progressTimer: ReturnType<typeof setInterval> | null = null;

function syncFromLocalState(state: SpotifyWebPlaybackState | null) {
    if (!state) {
        localPlaybackActive.value = false;

        return;
    }

    localPlaybackActive.value = true;

    const duration = state.duration || data.value?.track.duration_ms || 0;

    if (duration > 0) {
        progressPct.value = (state.position / duration) * 100;
    }

    if (data.value?.track?.id !== state.track_window.current_track.id) {
        void fetchNowPlaying();
    }
}

function loadSpotifySdk(): Promise<void> {
    if (window.Spotify) {
        return Promise.resolve();
    }

    return new Promise((resolve, reject) => {
        const existingScript = document.querySelector<HTMLScriptElement>(
            'script[data-spotify-web-playback="true"]',
        );

        window.onSpotifyWebPlaybackSDKReady = () => {
            resolve();
        };

        if (existingScript) {
            return;
        }

        const script = document.createElement('script');
        script.src = 'https://sdk.scdn.co/spotify-player.js';
        script.async = true;
        script.dataset.spotifyWebPlayback = 'true';
        script.onerror = () => reject(new Error('Failed to load Spotify SDK'));
        document.body.appendChild(script);
    });
}

async function initLocalPlayer() {
    if (localPlayer.value || localPlayerInitializing.value) {
        return;
    }

    localPlayerInitializing.value = true;

    try {
        await loadSpotifySdk();

        if (!window.Spotify) {
            return;
        }

        const player = new window.Spotify.Player({
            name: 'Pulsefy Web Player',
            getOAuthToken: async (callback) => {
                const token = await fetchDeviceToken();
                callback(token ?? '');
            },
            volume: 0.85,
        });

        player.addListener('ready', ({ device_id }: { device_id: string }) => {
            localDeviceId.value = device_id;
            localPlayerReady.value = true;
            void refreshDevices();
        });

        player.addListener('not_ready', () => {
            localPlayerReady.value = false;
            localPlaybackActive.value = false;
        });

        player.addListener('player_state_changed', (state) => {
            syncFromLocalState(state as SpotifyWebPlaybackState | null);
        });

        player.addListener('initialization_error', () => {
            localPlayerReady.value = false;
            localStatus.value =
                'Spotify Web Playback failed to initialize for this account.';
        });

        player.addListener('authentication_error', () => {
            localPlayerReady.value = false;
            localStatus.value =
                'Spotify Web Playback permission denied. Reconnect Spotify with streaming scope.';
        });

        player.addListener('account_error', () => {
            localPlayerReady.value = false;
            localStatus.value =
                'Spotify Premium is required for browser playback (Web Playback SDK).';
        });

        player.addListener('playback_error', () => {
            localPlaybackActive.value = false;
        });

        await player.connect();
        localPlayer.value = player;
    } finally {
        localPlayerInitializing.value = false;
    }
}

async function onTransferToSelectedDevice() {
    if (!selectedDeviceId.value || transferBusy.value) {
        return;
    }

    transferBusy.value = true;
    localStatus.value = '';

    try {
        if (
            localPlayer.value &&
            localDeviceId.value &&
            selectedDeviceId.value === localDeviceId.value
        ) {
            await localPlayer.value.activateElement();
        }

        const response = await fetch(transfer.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                device_id: selectedDeviceId.value,
                play: data.value?.is_playing ?? true,
            }),
        });

        if (!response.ok) {
            localStatus.value = 'Could not switch device.';

            return;
        }

        localStatus.value = 'Playback device updated.';
        await refreshDevices();
        await fetchNowPlaying();
        devicesOpen.value = false;
    } catch {
        localStatus.value = 'Could not switch device.';
    } finally {
        transferBusy.value = false;
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
        lyricsOpen.value = false;

        return;
    }

    if (activeTrackId.value !== val.track.id) {
        activeTrackId.value = val.track.id;
        lyricLineRefs.value = [];
        void fetchLyricsForCurrentTrack();
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
    if (pollTimer) {
        clearInterval(pollTimer);
    }

    if (progressTimer) {
        clearInterval(progressTimer);
    }

    document.removeEventListener('pointerdown', handleDocumentPointerDown);

    if (localPlayer.value) {
        localPlayer.value.disconnect();
        localPlayer.value = null;
    }
});

let csrfToken = '';

onMounted(() => {
    csrfToken =
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? '';

    fetchNowPlaying();
    pollTimer = setInterval(fetchNowPlaying, POLL_INTERVAL);
    document.addEventListener('pointerdown', handleDocumentPointerDown);
    void initLocalPlayer();
    void refreshDevices();
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
                'X-CSRF-TOKEN': csrfToken,
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
    if (localPlaybackActive.value && localPlayer.value) {
        void localPlayer.value.resume();

        return;
    }

    sendCommand(play.url());
}
function onPause() {
    if (localPlaybackActive.value && localPlayer.value) {
        void localPlayer.value.pause();

        return;
    }

    sendCommand(pause.url());
}
function onNext() {
    if (localPlaybackActive.value && localPlayer.value) {
        void localPlayer.value.nextTrack();

        return;
    }

    sendCommand(next.url());
}
function onPrevious() {
    if (localPlaybackActive.value && localPlayer.value) {
        void localPlayer.value.previousTrack();

        return;
    }

    sendCommand(previous.url());
}
function onShuffle() {
    sendCommand(shuffle.url(), { state: !data.value?.shuffle_state });
}

function formatDuration(ms: number): string {
    const minutes = Math.floor(ms / 60000);
    const seconds = Math.floor((ms % 60000) / 1000);

    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
}

const progressMs = computed(() => {
    if (!data.value?.track) {
        return 0;
    }

    return (progressPct.value / 100) * data.value.track.duration_ms;
});

type ParsedLyricLine = {
    timeMs: number;
    text: string;
};

const parsedSyncedLyrics = computed<ParsedLyricLine[]>(() => {
    if (lyricsData.value?.type !== 'synced' || !lyricsData.value.lyrics) {
        return [];
    }

    const lines = lyricsData.value.lyrics.split('\n');

    return lines
        .map((line) => {
            const match = line.match(
                /^\[(\d{1,2}):(\d{2})(?:\.(\d{1,3}))?\]\s*(.*)$/,
            );

            if (!match) {
                return null;
            }

            const minutes = Number(match[1]);
            const seconds = Number(match[2]);
            const fraction = (match[3] ?? '0').padEnd(3, '0').slice(0, 3);
            const milliseconds = Number(fraction);
            const text = match[4] ?? '';

            return {
                timeMs: minutes * 60_000 + seconds * 1_000 + milliseconds,
                text,
            };
        })
        .filter((line): line is ParsedLyricLine => line !== null)
        .sort((a, b) => a.timeMs - b.timeMs);
});

const activeLyricLineIndex = computed(() => {
    if (!parsedSyncedLyrics.value.length) {
        return -1;
    }

    let index = -1;

    for (let i = 0; i < parsedSyncedLyrics.value.length; i += 1) {
        if (progressMs.value >= parsedSyncedLyrics.value[i].timeMs) {
            index = i;
        } else {
            break;
        }
    }

    return index;
});

watch(activeLyricLineIndex, (index) => {
    if (!lyricsOpen.value || index < 0) {
        return;
    }

    lyricLineRefs.value[index]?.scrollIntoView({
        behavior: 'smooth',
        block: 'center',
    });
});

function setLyricLineRef(element: Element | null, index: number) {
    if (element instanceof HTMLElement) {
        lyricLineRefs.value[index] = element;
    }
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
                v-if="visible && lyricsOpen"
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
                        @click="lyricsOpen = false"
                    >
                        Close
                    </button>
                </div>

                <div
                    class="h-[55vh] overflow-y-auto rounded-md bg-muted/20 px-2 py-2"
                >
                    <div v-if="lyricsHttp.processing" class="space-y-2">
                        <div class="h-5 w-2/3 animate-pulse rounded bg-muted" />
                        <div class="h-5 w-1/2 animate-pulse rounded bg-muted" />
                        <div class="h-5 w-3/4 animate-pulse rounded bg-muted" />
                    </div>

                    <template v-else-if="lyricsData?.type === 'synced'">
                        <p
                            v-for="(line, index) in parsedSyncedLyrics"
                            :key="`${line.timeMs}-${index}`"
                            :ref="(element) => setLyricLineRef(element, index)"
                            :class="[
                                'w-full rounded-md px-3 py-2 text-sm transition-all hover:bg-muted/60',
                                index === activeLyricLineIndex
                                    ? 'bg-muted/60 font-semibold text-foreground'
                                    : 'text-muted-foreground',
                            ]"
                        >
                            {{ line.text || '♪' }}
                        </p>
                    </template>

                    <pre
                        v-else-if="lyricsData?.type === 'plain'"
                        class="text-sm leading-6 whitespace-pre-wrap text-foreground"
                        >{{ lyricsData?.lyrics }}</pre
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
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="currentColor"
                                    class="size-5"
                                >
                                    <path
                                        d="M12 3v10.55A4 4 0 1 0 14 17V8.69l6-1.5V15a4 4 0 1 0 2 3.45V4.63L12 7.13V3z"
                                    />
                                </svg>
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
                                :disabled="lyricsHttp.processing"
                                class="mt-5 inline-flex items-center gap-2 rounded-md border border-border px-3 py-2 text-sm font-medium text-foreground transition-colors hover:bg-muted disabled:opacity-50"
                                @click="retryLyricsFetch"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="size-4"
                                >
                                    <path d="M20 11a8 8 0 1 0 2.3 5.7" />
                                    <path d="M20 4v7h-7" />
                                </svg>
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
                class="border-t border-border bg-card/95 backdrop-blur-md"
            >
                <div class="h-0.5 w-full bg-muted">
                    <div
                        class="h-full bg-primary transition-[width] duration-500 ease-linear"
                        :style="{ width: `${progressPct}%` }"
                    />
                </div>

                <div
                    class="mx-auto grid h-16 max-w-7xl grid-cols-3 items-center px-4"
                >
                    <div class="flex min-w-0 flex-1 items-center gap-3">
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

                        <div class="min-w-0">
                            <a
                                :href="data!.track.external_urls.spotify"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="block truncate text-sm font-semibold text-foreground transition-colors hover:text-primary"
                            >
                                {{ data!.track.name }}
                            </a>
                            <p class="truncate text-xs text-muted-foreground">
                                {{
                                    data!.track.artists
                                        .map((a) => a.name)
                                        .join(', ')
                                }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="flex items-center justify-center gap-1 justify-self-center"
                    >
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
                                <path
                                    d="M22 6h-5.9c-1.3 0-2.6.7-3.3 1.8l-.5.8"
                                />
                                <path d="m19 3 3 3-3 3" />
                            </svg>
                        </button>

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
                            <svg
                                v-if="data!.is_playing"
                                xmlns="http://www.w3.org/2000/svg"
                                class="size-5"
                                viewBox="0 0 24 24"
                                fill="currentColor"
                            >
                                <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z" />
                            </svg>
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
                                <path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z" />
                            </svg>
                        </button>
                    </div>

                    <div
                        class="flex items-center justify-end gap-3 justify-self-end"
                    >
                        <div class="relative hidden md:block">
                            <button
                                type="button"
                                class="inline-flex rounded-md border border-border px-2 py-1 text-[11px] text-muted-foreground transition-colors hover:text-foreground disabled:opacity-50"
                                :disabled="
                                    devicesHttp.processing || transferBusy
                                "
                                title="Choose playback device"
                                @click="
                                    devicesOpen = !devicesOpen;
                                    if (devicesOpen) refreshDevices();
                                "
                            >
                                Devices
                            </button>

                            <div
                                v-if="devicesOpen"
                                class="absolute right-0 bottom-full z-20 mb-2 w-64 rounded-md border border-border bg-card p-2 shadow-lg"
                            >
                                <p
                                    class="mb-2 text-[11px] font-medium text-muted-foreground"
                                >
                                    Select playback device
                                </p>

                                <div
                                    class="max-h-52 space-y-1 overflow-auto pr-1"
                                >
                                    <button
                                        v-for="device in selectableDevices"
                                        :key="device.id ?? device.name"
                                        type="button"
                                        class="flex w-full items-center justify-between rounded px-2 py-1 text-left text-xs transition-colors hover:bg-muted"
                                        :class="
                                            selectedDeviceId === device.id
                                                ? 'bg-muted'
                                                : ''
                                        "
                                        :disabled="transferBusy || !device.id"
                                        @click="
                                            selectedDeviceId = device.id ?? '';
                                            onTransferToSelectedDevice();
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
                                            !selectableDevices.length &&
                                            !localPlayerReady
                                        "
                                        class="px-2 py-1 text-[11px] text-muted-foreground"
                                    >
                                        No available devices.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div
                            class="flex min-w-[92px] items-center justify-end gap-1 text-[11px] text-muted-foreground tabular-nums sm:text-xs"
                        >
                            <span>{{ formatDuration(progressMs) }}</span>
                            <span class="hidden opacity-40 sm:inline">/</span>
                            <span class="hidden sm:inline">{{
                                formatDuration(data!.track.duration_ms)
                            }}</span>
                        </div>

                        <button
                            type="button"
                            :disabled="lyricsHttp.processing"
                            :title="lyricsOpen ? 'Hide lyrics' : 'Show lyrics'"
                            class="flex size-8 shrink-0 items-center justify-center rounded-md border border-border text-muted-foreground transition-colors hover:text-foreground disabled:opacity-50"
                            @click="lyricsOpen = !lyricsOpen"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="currentColor"
                                class="size-4"
                            >
                                <path
                                    d="M12 3v10.55A4 4 0 1 0 14 17V8.69l6-1.5V15a4 4 0 1 0 2 3.45V4.63L12 7.13V3z"
                                />
                            </svg>
                        </button>
                    </div>
                </div>

                <p
                    v-if="localStatus"
                    class="mx-auto max-w-7xl px-4 pb-2 text-[11px] text-muted-foreground"
                >
                    {{ localStatus }}
                </p>
            </div>
        </Transition>
    </div>
</template>
