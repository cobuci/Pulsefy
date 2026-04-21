<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Heart, Languages, MicVocal, Repeat, Repeat1, Shuffle, Sparkles, X } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import IconDevice from '@/components/icons/IconDevice.vue';
import IconKaraoke from '@/components/icons/IconKaraoke.vue';
import IconMusicNote from '@/components/icons/IconMusicNote.vue';
import IconNext from '@/components/icons/IconNext.vue';
import IconPause from '@/components/icons/IconPause.vue';
import IconPlay from '@/components/icons/IconPlay.vue';
import IconPrevious from '@/components/icons/IconPrevious.vue';
import IconRefresh from '@/components/icons/IconRefresh.vue';
import TrackInsightsPanel from '@/components/TrackInsightsPanel.vue';
import VolumeControl from '@/components/player/VolumeControl.vue';
import { usePlayer } from '@/composables/usePlayer';
import { useSpotifyDevices } from '@/composables/useSpotifyDevices';
import { useSpotifyLyrics } from '@/composables/useSpotifyLyrics';
import { useSpotifyWebPlayer } from '@/composables/useSpotifyWebPlayer';
import { show as albumShow } from '@/routes/albums';
import { show as artistShow } from '@/routes/artists';
import {
    next,
    pause,
    play,
    previous,
    repeat as repeatRoute,
    seek,
    shuffle as shuffleRoute,
} from '@/routes/player';
import type { LyricsPronunciationUpdatedEvent, LyricsTranslationUpdatedEvent } from '@/types/spotify';
import { getCsrfToken } from '@/utils/csrf';
import { formatDuration } from '@/utils/format';

const POLL_INTERVAL = 30_000;
const PREVIOUS_RESTART_THRESHOLD_MS = 3_000;

const { nowPlayingData, fetchNowPlaying, isCurrentTrackSaved, toggleSaveTrack } = usePlayer();
const page = usePage<{
    auth: {
        user?: {
            id: number;
        };
    };
}>();
const data = computed(() => nowPlayingData.value);
const hasTrack = computed(() => !!data.value?.track);
const isPlaying = computed(() => data.value?.is_playing ?? false);
const isBusy = ref(false);
const isShuffleBusy = ref(false);
const isRepeatBusy = ref(false);
const isFavoriteBusy = ref(false);
const activeTrackId = ref<string | null>(null);
const playerRootRef = ref<HTMLElement | null>(null);
const activeLyricRef = ref<HTMLElement | null>(null);
const localStatus = ref('');
const lyricsSecondaryMode = ref<'none' | 'translation' | 'romanization'>('none');
const lyricsTranslationLanguage = ref<'pt-BR' | 'en'>('pt-BR');

const progressPct = ref(0);
const seekSliderPct = ref(0);
const isScrubbing = ref(false);
const seekBusy = ref(false);
let progressTimer: ReturnType<typeof setInterval> | null = null;
let pollTimer: ReturnType<typeof setInterval> | null = null;

const displayedProgressPct = computed(() => {
    return isScrubbing.value ? seekSliderPct.value : progressPct.value;
});

const progressMs = computed(() => {
    if (!data.value?.track) {
        return 0;
    }

    return (displayedProgressPct.value / 100) * data.value.track.duration_ms;
});

const webPlayer = useSpotifyWebPlayer(
    (message) => {
        localStatus.value = message;
    },
    () => {},
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

const insightsOpen = ref(false);

const isPlayingExternally = computed(() => {
    if (!hasTrack.value || !webPlayer.localDeviceId.value) {
        return false;
    }

    return (
        devices.selectedDeviceId.value !== '' &&
        devices.selectedDeviceId.value !== webPlayer.localDeviceId.value
    );
});

const activeDeviceName = computed(() => {
    if (!isPlayingExternally.value) {
        return null;
    }

    return (
        devices.selectableDevices.value.find(
            (d) => d.id === devices.selectedDeviceId.value,
        )?.name ?? null
    );
});

async function transferToLocalPlayer(): Promise<void> {
    if (!webPlayer.localDeviceId.value) {
        return;
    }

    devices.selectedDeviceId.value = webPlayer.localDeviceId.value;
    await devices.onTransferToSelectedDevice();
}

watch(() => data.value?.track?.id, () => {
    insightsOpen.value = false;
});

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

function handleLyricsHotkeys(event: KeyboardEvent) {
    if (!lyrics.lyricsOpen.value) {
        return;
    }

    if (event.key === 'Escape') {
        lyrics.lyricsOpen.value = false;

        return;
    }

    if (event.key.toLowerCase() === 't') {
        handleTranslationToggle();
    }

    if (event.key.toLowerCase() === 'r') {
        handleRomanizationToggle();
    }

    if (event.key.toLowerCase() === 'l' && lyricsSecondaryMode.value !== 'none') {
        lyricsTranslationLanguage.value =
            lyricsTranslationLanguage.value === 'pt-BR' ? 'en' : 'pt-BR';
    }
}

const hasLyricsTranslation = computed(() => {
    return (lyrics.lyricsData.value?.translation?.translated_lines?.length ?? 0) > 0;
});

const translationStatus = computed(() => {
    return lyrics.lyricsData.value?.translation?.status ?? null;
});

const isTranslating = computed(() => {
    return lyrics.translationRequestBusy.value || translationStatus.value === 'queued' || translationStatus.value === 'processing';
});

const translationButtonLabel = computed(() => {
    if (isTranslating.value) {
        return 'Translating...';
    }

    if (translationStatus.value === 'failed') {
        return 'Retry translation';
    }

    if (!hasLyricsTranslation.value) {
        return 'Get translation';
    }

    return 'Translate';
});

const hasLyricsRomanization = computed(() => {
    return (lyrics.lyricsData.value?.romanization?.romanized_lines?.length ?? 0) > 0;
});

const romanizationStatus = computed(() => {
    return lyrics.lyricsData.value?.romanization?.status ?? null;
});

const isRomanizing = computed(() => {
    return lyrics.romanizationRequestBusy.value || romanizationStatus.value === 'queued' || romanizationStatus.value === 'processing';
});

const romanizationButtonLabel = computed(() => {
    if (isRomanizing.value) {
        return 'Romanizing...';
    }

    if (romanizationStatus.value === 'failed') {
        return 'Retry romanization';
    }

    if (!hasLyricsRomanization.value) {
        return 'Get romanization';
    }

    return 'Romanize';
});

function renderedSecondaryLine(index: number, fallbackText: string): string {
    if (lyricsSecondaryMode.value === 'romanization') {
        const romanizedLine = lyrics.romanizedLinesByIndex.value.get(index);

        if (!romanizedLine) {
            return fallbackText;
        }

        if (lyricsTranslationLanguage.value === 'pt-BR') {
            return romanizedLine.pt_br ?? fallbackText;
        }

        return romanizedLine.en ?? fallbackText;
    }

    const translatedLine = lyrics.translatedLinesByIndex.value.get(index);

    if (!translatedLine) {
        return fallbackText;
    }

    if (lyricsTranslationLanguage.value === 'pt-BR') {
        return translatedLine.pt_br ?? fallbackText;
    }

    return translatedLine.en ?? fallbackText;
}

function requestLyricsTranslation() {
    void lyrics.requestTranslation();
}

function handleTranslationToggle() {
    if (!hasLyricsTranslation.value) {
        requestLyricsTranslation();

        return;
    }

    lyricsSecondaryMode.value = lyricsSecondaryMode.value === 'translation' ? 'none' : 'translation';
}

function handleRomanizationToggle() {
    if (!hasLyricsRomanization.value) {
        void lyrics.requestRomanization();

        return;
    }

    lyricsSecondaryMode.value = lyricsSecondaryMode.value === 'romanization' ? 'none' : 'romanization';
}

watch(data, (val) => {
    if (progressTimer) {
        clearInterval(progressTimer);
        progressTimer = null;
    }

    if (!val?.track) {
        progressPct.value = 0;
        seekSliderPct.value = 0;
        isScrubbing.value = false;
        activeTrackId.value = null;
        lyrics.lyricsOpen.value = false;

        return;
    }

    if (activeTrackId.value !== val.track.id) {
        activeTrackId.value = val.track.id;
        lyricsSecondaryMode.value = 'none';
        lyricsTranslationLanguage.value = 'pt-BR';
        lyrics.fetchLyricsForCurrentTrack();
    }

    const duration = val.track.duration_ms;
    progressPct.value = duration > 0 ? (val.progress_ms / duration) * 100 : 0;

    if (!isScrubbing.value) {
        seekSliderPct.value = progressPct.value;
    }

    if (val.is_playing && duration > 0) {
        progressTimer = setInterval(() => {
            progressPct.value = Math.min(
                progressPct.value + (100 / duration) * 500,
                100,
            );

            if (!isScrubbing.value) {
                seekSliderPct.value = progressPct.value;
            }
        }, 500);
    }
});

onMounted(() => {
    fetchNowPlaying();
    pollTimer = setInterval(fetchNowPlaying, POLL_INTERVAL);
    document.addEventListener('pointerdown', handleDocumentPointerDown);
    document.addEventListener('keydown', handleLyricsHotkeys);
    void webPlayer.initLocalPlayer((state) => {
        webPlayer.syncFromLocalState(
            state,
            () => void fetchNowPlaying(),
            () => data.value?.track?.id,
            (pct) => {
                progressPct.value = pct;

                if (!isScrubbing.value) {
                    seekSliderPct.value = pct;
                }
            },
            () => data.value?.track?.duration_ms ?? 0,
            (isPlayingState) => {
                if (!nowPlayingData.value) {
                    return;
                }

                nowPlayingData.value = {
                    ...nowPlayingData.value,
                    is_playing: isPlayingState,
                };

                if (!isPlayingState && progressTimer) {
                    clearInterval(progressTimer);
                    progressTimer = null;
                }
            },
        );
    });

    if (!webPlayer.localPlayerSupported.value) {
        localStatus.value =
            'Your browser has limited DRM capability. Spotify Web Player controls may not be available.';
    }

    if (page.props.auth?.user?.id && typeof window !== 'undefined' && window.Echo) {
        window.Echo.private(`App.Models.User.${page.props.auth.user.id}`)
            .listen(
                '.Lyrics.TranslationUpdated',
                (event: LyricsTranslationUpdatedEvent) => {
                    const currentTrackId = data.value?.track?.id;

                    if (!currentTrackId || event.trackId !== currentTrackId) {
                        return;
                    }

                    const payload = lyrics.lyricsData.value;

                    if (!payload) {
                        return;
                    }

                    lyrics.lyricsHttp.response = {
                        ...payload,
                        translation: {
                            ...payload.translation,
                            status: event.status,
                            translated_lines: event.translatedLines,
                            error_message: event.errorMessage,
                        },
                    };
                },
            )
            .listen(
                '.Lyrics.PronunciationUpdated',
                (event: LyricsPronunciationUpdatedEvent) => {
                    const currentTrackId = data.value?.track?.id;

                    if (!currentTrackId || event.trackId !== currentTrackId) {
                        return;
                    }

                    const payload = lyrics.lyricsData.value;

                    if (!payload) {
                        return;
                    }

                    lyrics.lyricsHttp.response = {
                        ...payload,
                        romanization: {
                            ...payload.romanization,
                            status: event.status,
                            romanized_lines: event.romanizedLines,
                            error_message: event.errorMessage,
                        },
                    };
                },
            );
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
    document.removeEventListener('keydown', handleLyricsHotkeys);
    webPlayer.disconnect();

    if (page.props.auth?.user?.id && typeof window !== 'undefined' && window.Echo) {
        window.Echo.leave(`App.Models.User.${page.props.auth.user.id}`);
    }
});

async function postPlayerCommand(url: string, body?: Record<string, unknown>) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: body ? JSON.stringify(body) : undefined,
    });

    if (!response.ok) {
        return false;
    }

    const payload = (await response.json()) as { ok?: boolean };

    return payload.ok ?? true;
}

async function sendCommand(url: string, body?: Record<string, unknown>) {
    if (isBusy.value) {
        return;
    }

    isBusy.value = true;

    try {
        await postPlayerCommand(url, body);
        await new Promise((r) => setTimeout(r, 600));
        await fetchNowPlaying();
    } finally {
        isBusy.value = false;
    }
}

function onPlay() {
    if (webPlayer.localPlayer.value && webPlayer.localPlayerReady.value) {
        nowPlayingData.value = nowPlayingData.value
            ? {
                  ...nowPlayingData.value,
                  is_playing: true,
              }
            : nowPlayingData.value;

        void webPlayer.localPlayer.value.resume();
        void fetchNowPlaying();

        return;
    }

    sendCommand(play.url());
}

function applyPausedStateLocally() {
    if (!nowPlayingData.value) {
        return;
    }

    nowPlayingData.value = {
        ...nowPlayingData.value,
        is_playing: false,
    };

    if (progressTimer) {
        clearInterval(progressTimer);
        progressTimer = null;
    }
}

function onPause() {
    if (webPlayer.localPlaybackActive.value && webPlayer.localPlayer.value) {
        void webPlayer.localPlayer.value.pause();
        applyPausedStateLocally();
        void fetchNowPlaying();

        return;
    }

    applyPausedStateLocally();
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

            return;
        }

        sendCommand(seek.url(), { position_ms: 0 });

        return;
    }

    if (webPlayer.localPlaybackActive.value && webPlayer.localPlayer.value) {
        void webPlayer.localPlayer.value.previousTrack();

        return;
    }

    sendCommand(previous.url());
}

function onToggleFavorite() {
    if (isFavoriteBusy.value || !hasTrack.value) {
        return;
    }

    isFavoriteBusy.value = true;

    void toggleSaveTrack().finally(() => {
        isFavoriteBusy.value = false;
    });
}

function onToggleShuffle() {
    if (isShuffleBusy.value || !data.value) {
        return;
    }

    isShuffleBusy.value = true;

    const nextState = !data.value.shuffle_state;

    void sendCommand(shuffleRoute.url(), { state: nextState }).finally(() => {
        isShuffleBusy.value = false;
    });
}

function nextRepeatState(
    current: 'off' | 'track' | 'context',
): 'off' | 'track' | 'context' {
    if (current === 'off') {
        return 'context';
    }

    if (current === 'context') {
        return 'track';
    }

    return 'off';
}

function onToggleRepeat() {
    if (isRepeatBusy.value || !data.value) {
        return;
    }

    isRepeatBusy.value = true;
    const nextState = nextRepeatState(data.value.repeat_state ?? 'off');

    void sendCommand(repeatRoute.url(), { state: nextState }).finally(() => {
        isRepeatBusy.value = false;
    });
}

function onSeekInput(event: Event) {
    const target = event.target as HTMLInputElement;
    const value = Number(target.value);

    if (!Number.isFinite(value)) {
        return;
    }

    isScrubbing.value = true;
    seekSliderPct.value = Math.max(0, Math.min(100, value));
}

async function seekToPosition(positionMs: number) {
    if (!data.value?.track) {
        return;
    }

    const clampedPositionMs = Math.max(
        0,
        Math.min(positionMs, data.value.track.duration_ms),
    );
    const pct =
        data.value.track.duration_ms > 0
            ? (clampedPositionMs / data.value.track.duration_ms) * 100
            : 0;

    seekBusy.value = true;
    progressPct.value = pct;
    seekSliderPct.value = pct;
    isScrubbing.value = false;

    try {
        if (
            webPlayer.localPlaybackActive.value &&
            webPlayer.localPlayer.value
        ) {
            await webPlayer.localPlayer.value.seek(clampedPositionMs);

            await new Promise((resolve) => setTimeout(resolve, 250));
            await fetchNowPlaying();

            return;
        }

        await postPlayerCommand(seek.url(), {
            position_ms: clampedPositionMs,
        });

        await new Promise((resolve) => setTimeout(resolve, 250));
        await fetchNowPlaying();
    } finally {
        seekBusy.value = false;
    }
}

async function onSeekChange(event: Event) {
    if (!data.value?.track) {
        return;
    }

    const target = event.target as HTMLInputElement;
    const value = Number(target.value);

    if (!Number.isFinite(value) || seekBusy.value) {
        isScrubbing.value = false;

        return;
    }

    const pct = Math.max(0, Math.min(100, value));
    const positionMs = Math.floor((pct / 100) * data.value.track.duration_ms);

    isScrubbing.value = false;

    await seekToPosition(positionMs);
}

function onLyricLineClick(timeMs: number) {
    if (!hasTrack.value || seekBusy.value) {
        return;
    }

    void seekToPosition(timeMs);
}

function setActiveLyricRef(element: unknown, index: number) {
    if (index !== lyrics.activeLyricLineIndex.value) {
        return;
    }

    if (element instanceof HTMLElement) {
        activeLyricRef.value = element;

        return;
    }

    if (
        typeof element === 'object' &&
        element !== null &&
        '$el' in element &&
        (element as { $el?: unknown }).$el instanceof HTMLElement
    ) {
        activeLyricRef.value = (element as { $el: HTMLElement }).$el;

        return;
    }

    activeLyricRef.value = null;
}

function toggleLyricsTranslationLanguage() {
    lyricsTranslationLanguage.value =
        lyricsTranslationLanguage.value === 'pt-BR' ? 'en' : 'pt-BR';
}

watch(
    [() => lyrics.activeLyricLineIndex.value, () => lyrics.lyricsOpen.value],
    ([index, open]) => {
        if (!open || index < 0) {
            return;
        }

        activeLyricRef.value?.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
        });
    },
);
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
                class="relative mx-auto max-h-[78vh] max-w-7xl overflow-hidden rounded-t-2xl border border-b-0 border-border/60"
            >
                <div
                    class="absolute inset-0 overflow-hidden"
                >
                    <img
                        v-if="data?.track?.album?.images?.[0]?.url"
                        :src="data.track.album.images[0].url"
                        alt=""
                        aria-hidden="true"
                        class="absolute inset-0 h-full w-full scale-125 object-cover opacity-40 blur-3xl"
                    />
                    <div class="absolute inset-0 bg-background/85 backdrop-blur-2xl" />
                </div>

                <div class="relative flex h-[74vh] flex-col">
                    <header class="flex items-center justify-between gap-4 px-6 pt-5">
                        <div class="min-w-0">
                            <p class="text-[10px] font-semibold tracking-[0.2em] text-accent uppercase">
                                Lyrics
                            </p>
                            <p class="truncate text-sm font-semibold text-foreground">
                                {{ data?.track?.name }}
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ data?.track?.artists?.map((item) => item.name).join(', ') }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                :disabled="isTranslating"
                                :title="lyricsSecondaryMode === 'translation' ? 'Translation on (T)' : 'Translation off (T)'"
                                :class="[
                                    'inline-flex h-9 items-center gap-2 rounded-full border px-3 text-xs font-medium transition-colors',
                                    lyricsSecondaryMode === 'translation'
                                        ? 'border-accent bg-accent text-accent-foreground'
                                        : 'border-border text-muted-foreground hover:text-foreground',
                                    isTranslating
                                        ? 'cursor-not-allowed opacity-40'
                                        : 'cursor-pointer',
                                ]"
                                @click="handleTranslationToggle"
                            >
                                <Languages class="size-3.5" />
                                <span class="hidden sm:inline">{{ translationButtonLabel }}</span>
                            </button>

                            <button
                                type="button"
                                :disabled="isRomanizing"
                                :title="lyricsSecondaryMode === 'romanization' ? 'Romanization on (R)' : 'Romanization off (R)'"
                                :class="[
                                    'inline-flex h-9 items-center gap-2 rounded-full border px-3 text-xs font-medium transition-colors',
                                    lyricsSecondaryMode === 'romanization'
                                        ? 'border-accent bg-accent text-accent-foreground'
                                        : 'border-border text-muted-foreground hover:text-foreground',
                                    isRomanizing
                                        ? 'cursor-not-allowed opacity-40'
                                        : 'cursor-pointer',
                                ]"
                                @click="handleRomanizationToggle"
                            >
                                <span class="text-[11px]">あ</span>
                                <span class="hidden sm:inline">{{ romanizationButtonLabel }}</span>
                            </button>

                            <button
                                type="button"
                                :disabled="lyricsSecondaryMode === 'none'"
                                title="Toggle language (L)"
                                :class="[
                                    'inline-flex h-9 items-center rounded-full border px-3 text-xs font-medium transition-colors',
                                    lyricsSecondaryMode !== 'none'
                                        ? 'border-border text-foreground hover:text-accent'
                                        : 'border-border text-muted-foreground opacity-40',
                                ]"
                                @click="toggleLyricsTranslationLanguage"
                            >
                                {{ lyricsTranslationLanguage }}
                            </button>

                            <p
                                v-if="translationStatus === 'failed'"
                                class="max-w-60 text-[11px] text-destructive"
                            >
                                Translation failed. Try again.
                            </p>

                            <p
                                v-if="romanizationStatus === 'failed'"
                                class="max-w-60 text-[11px] text-destructive"
                            >
                                Romanization failed. Try again.
                            </p>

                            <button
                                type="button"
                                class="grid size-9 place-items-center rounded-full border border-border text-muted-foreground transition-colors hover:text-foreground"
                                aria-label="Close lyrics"
                                @click="lyrics.lyricsOpen.value = false"
                            >
                                <X class="size-4" />
                            </button>
                        </div>
                    </header>

                    <div class="h-full overflow-y-auto px-4 py-8 sm:px-8">
                        <div class="mx-auto max-w-3xl space-y-6 pb-[26vh] pt-[14vh]">
                            <div v-if="lyrics.lyricsHttp.processing" class="space-y-2">
                                <div class="h-5 w-2/3 animate-pulse rounded bg-muted" />
                                <div class="h-5 w-1/2 animate-pulse rounded bg-muted" />
                                <div class="h-5 w-3/4 animate-pulse rounded bg-muted" />
                            </div>

                            <template
                                v-else-if="lyrics.lyricsData.value?.type === 'synced'"
                            >
                                <button
                                    v-for="(line, index) in lyrics.parsedSyncedLyrics
                                        .value"
                                    :key="`${line.timeMs}-${index}`"
                                    type="button"
                                    :ref="(element) => setActiveLyricRef(element, index)"
                                    :disabled="seekBusy || !hasTrack"
                                    :class="[
                                        'w-full cursor-pointer rounded-md px-3 py-2 text-left transition-all disabled:cursor-not-allowed disabled:opacity-50',
                                        index === lyrics.activeLyricLineIndex.value
                                            ? 'bg-muted/60 font-semibold text-foreground [text-shadow:0_0_26px_color-mix(in_oklab,var(--accent)_45%,transparent)]'
                                            : index < lyrics.activeLyricLineIndex.value
                                              ? 'text-muted-foreground/45'
                                              : 'text-muted-foreground/70',
                                    ]"
                                    @click="onLyricLineClick(line.timeMs)"
                                >
                                    <p class="font-display text-xl leading-tight sm:text-3xl">
                                        {{ line.text || '♪' }}
                                    </p>

                                    <p
                                        v-if="lyricsSecondaryMode !== 'none'"
                                        class="mt-1 border-l-2 border-accent/70 pl-3 text-sm italic text-accent/90"
                                    >
                                        {{ renderedSecondaryLine(index, line.text || '♪') }}
                                    </p>
                                </button>
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
                    </div>

                    <footer class="absolute right-0 bottom-0 left-0 border-t border-border/60 bg-background/80 px-6 py-3 text-[11px] text-muted-foreground backdrop-blur">
                        <div class="mx-auto flex max-w-3xl flex-wrap items-center gap-2">
                            <span>Lyrics by LRCLIB</span>
                            <span>·</span>
                            <kbd class="rounded border border-border bg-secondary px-1.5 py-0.5 text-[10px]">Esc</kbd>
                            <span>close</span>
                            <span>·</span>
                            <kbd class="rounded border border-border bg-secondary px-1.5 py-0.5 text-[10px]">T</kbd>
                            <span>translation</span>
                            <span>·</span>
                            <kbd class="rounded border border-border bg-secondary px-1.5 py-0.5 text-[10px]">R</kbd>
                            <span>romanization</span>
                            <span>·</span>
                            <kbd class="rounded border border-border bg-secondary px-1.5 py-0.5 text-[10px]">L</kbd>
                            <span>language</span>
                        </div>
                    </footer>
                </div>
            </div>
        </Transition>

        <div class="glass-strong border-t border-border/60">
            <div
                class="absolute inset-x-0 -top-px h-px bg-gradient-to-r from-transparent via-accent/60 to-transparent"
            />

            <div class="h-0.5 w-full bg-secondary/90">
                <div
                    class="bg-gradient-primary h-full transition-[width] duration-500 ease-linear"
                    :style="{ width: `${displayedProgressPct}%` }"
                />
            </div>

            <div
                class="mx-auto flex h-20 max-w-7xl items-center gap-4 px-4 sm:px-6"
            >
                <div class="flex w-1/3 min-w-0 items-center gap-3 lg:w-1/4">
                    <template v-if="hasTrack">
                        <div class="relative shrink-0">
                            <img
                                v-if="data?.track?.album?.images?.[0]"
                                :src="data?.track?.album?.images?.[0]?.url"
                                :alt="data?.track?.album?.name"
                                class="shadow-glow size-12 rounded-md object-cover"
                            />
                            <div v-else class="size-12 rounded-md bg-muted" />

                            <div
                                v-if="isPlaying"
                                class="absolute inset-0 grid place-items-center bg-background/40"
                            >
                                <div class="flex items-end gap-0.5">
                                    <span
                                        class="eq-bar h-3 w-0.5 rounded-full bg-accent"
                                    />
                                    <span
                                        class="eq-bar h-3 w-0.5 rounded-full bg-accent"
                                        style="animation-delay: 0.15s"
                                    />
                                    <span
                                        class="eq-bar h-3 w-0.5 rounded-full bg-accent"
                                        style="animation-delay: 0.3s"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="min-w-0">
                            <Link
                                v-if="data?.track?.album?.id"
                                :href="albumShow(data.track.album.id).url"
                                class="block truncate text-sm font-semibold text-foreground transition-colors hover:text-primary"
                            >
                                {{ data?.track?.name }}
                            </Link>
                            <p
                                v-else
                                class="block truncate text-sm font-semibold text-foreground"
                            >
                                {{ data?.track?.name }}
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                <template
                                    v-for="(artist, index) in data?.track
                                        ?.artists ?? []"
                                    :key="artist.id"
                                >
                                    <Link
                                        v-if="artist.id"
                                        :href="artistShow(artist.id).url"
                                        class="hover:text-foreground"
                                    >
                                        {{ artist.name }}
                                    </Link>
                                    <span v-else>{{ artist.name }}</span>
                                    <span
                                        v-if="
                                            index <
                                            (data?.track?.artists?.length ??
                                                0) -
                                                1
                                        "
                                        >,
                                    </span>
                                </template>
                            </p>
                        </div>
                    </template>

                    <button
                        v-if="hasTrack"
                        type="button"
                        :title="isCurrentTrackSaved ? 'Remove from library' : 'Save to library'"
                        :disabled="isFavoriteBusy"
                        :class="[
                            'ml-1 grid size-7 shrink-0 place-items-center rounded-full transition-colors',
                            isCurrentTrackSaved
                                ? 'text-accent'
                                : 'text-muted-foreground hover:text-foreground',
                            isFavoriteBusy ? 'cursor-not-allowed opacity-40' : 'cursor-pointer',
                        ]"
                        @click="onToggleFavorite"
                    >
                        <Heart
                            class="size-3.5"
                            :fill="isCurrentTrackSaved ? 'currentColor' : 'none'"
                        />
                    </button>

                    <p v-else class="text-xs text-muted-foreground">
                        Nothing playing
                    </p>
                </div>
                <div class="flex flex-1 flex-col items-center gap-1.5">
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            title="Shuffle"
                            :disabled="isShuffleBusy || !hasTrack"
                            :class="[
                                'grid size-8 place-items-center rounded-full transition-colors',
                                data?.shuffle_state
                                    ? 'text-accent'
                                    : 'text-muted-foreground hover:text-foreground',
                                isShuffleBusy || !hasTrack
                                    ? 'cursor-not-allowed opacity-40'
                                    : 'cursor-pointer',
                            ]"
                            @click="onToggleShuffle"
                        >
                            <Shuffle class="size-3.5" />
                        </button>

                        <button
                            type="button"
                            title="Previous"
                            :disabled="isBusy"
                            :class="[
                                'grid size-8 place-items-center rounded-full text-muted-foreground transition-colors hover:text-foreground',
                                isBusy
                                    ? 'cursor-not-allowed opacity-40'
                                    : 'cursor-pointer',
                            ]"
                            @click="onPrevious"
                        >
                            <IconPrevious class="size-5" />
                        </button>

                        <button
                            type="button"
                            :title="isPlaying ? 'Pause' : 'Play'"
                            :disabled="isBusy"
                            :class="[
                                'shadow-glow grid size-9 place-items-center rounded-full bg-foreground text-background transition-all hover:scale-105 active:scale-95',
                                isBusy
                                    ? 'cursor-not-allowed opacity-60'
                                    : 'cursor-pointer',
                            ]"
                            @click="isPlaying ? onPause() : onPlay()"
                        >
                            <IconPause v-if="isPlaying" class="size-5" />
                            <IconPlay v-else class="size-5 translate-x-[1px]" />
                        </button>

                        <button
                            type="button"
                            title="Next"
                            :disabled="isBusy"
                            :class="[
                                'grid size-8 place-items-center rounded-full text-muted-foreground transition-colors hover:text-foreground',
                                isBusy
                                    ? 'cursor-not-allowed opacity-40'
                                    : 'cursor-pointer',
                            ]"
                            @click="onNext"
                        >
                            <IconNext class="size-5" />
                        </button>

                        <button
                            type="button"
                            title="Repeat"
                            :disabled="isRepeatBusy || !hasTrack"
                            :class="[
                                'grid size-8 place-items-center rounded-full transition-colors',
                                data?.repeat_state &&
                                data.repeat_state !== 'off'
                                    ? 'text-accent'
                                    : 'text-muted-foreground hover:text-foreground',
                                isRepeatBusy || !hasTrack
                                    ? 'cursor-not-allowed opacity-40'
                                    : 'cursor-pointer',
                            ]"
                            @click="onToggleRepeat"
                        >
                            <Repeat1
                                v-if="data?.repeat_state === 'track'"
                                class="size-3.5"
                            />
                            <Repeat v-else class="size-3.5" />
                        </button>
                    </div>

                    <div class="flex w-full max-w-md items-center gap-2">
                        <span
                            class="text-[10px] text-muted-foreground tabular-nums"
                            >{{ formatDuration(progressMs) }}</span
                        >
                        <div class="relative h-4 flex-1">
                            <div
                                class="absolute inset-x-0 top-1/2 h-1 -translate-y-1/2 overflow-hidden rounded-full bg-secondary"
                            >
                                <div
                                    class="bg-gradient-primary h-full transition-[width] duration-150 ease-linear"
                                    :style="{
                                        width: `${displayedProgressPct}%`,
                                    }"
                                />
                            </div>
                            <div
                                class="pointer-events-none absolute top-1/2 z-10 size-2 -translate-y-1/2 rounded-full bg-accent shadow"
                                :style="{
                                    left: `calc(${displayedProgressPct}% - 4px)`,
                                }"
                            />
                            <input
                                type="range"
                                min="0"
                                max="100"
                                step="0.1"
                                :value="displayedProgressPct"
                                :disabled="!hasTrack || seekBusy"
                                aria-label="Seek position"
                                class="absolute inset-0 z-20 h-full w-full cursor-pointer opacity-0 disabled:cursor-not-allowed"
                                @input="onSeekInput"
                                @change="onSeekChange"
                            />
                        </div>
                        <span
                            class="text-[10px] text-muted-foreground tabular-nums"
                            >{{
                                hasTrack && data?.track?.duration_ms
                                    ? formatDuration(data.track.duration_ms)
                                    : '--:--'
                            }}</span
                        >
                    </div>
                </div>

                <div
                    class="ml-auto hidden items-center justify-end gap-2 md:flex"
                >
                    <button
                        type="button"
                        :disabled="!hasTrack"
                        title="AI Track Insights"
                        :class="[
                            'grid size-8 shrink-0 place-items-center rounded-md transition-colors disabled:opacity-50',
                            insightsOpen
                                ? 'text-purple-400'
                                : 'text-muted-foreground hover:text-foreground',
                            !hasTrack ? 'cursor-not-allowed' : 'cursor-pointer',
                        ]"
                        aria-label="AI Track Insights"
                        @click="insightsOpen = !insightsOpen"
                    >
                        <Sparkles class="size-4" />
                    </button>

                    <button
                        type="button"
                        :disabled="lyrics.lyricsHttp.processing"
                        :title="
                            lyrics.lyricsOpen.value
                                ? 'Hide lyrics'
                                : 'Show lyrics'
                        "
                        :class="[
                            'grid size-8 shrink-0 place-items-center rounded-md transition-colors disabled:opacity-50',
                            lyrics.lyricsOpen.value
                                ? 'text-primary'
                                : 'text-muted-foreground hover:text-foreground',
                            lyrics.lyricsHttp.processing
                                ? 'cursor-not-allowed'
                                : 'cursor-pointer',
                        ]"
                        @click="
                            lyrics.lyricsOpen.value = !lyrics.lyricsOpen.value
                        "
                    >
                        <MicVocal class="size-4" />
                    </button>

                    <div class="hidden items-center justify-end gap-2 lg:flex">
                        <div class="relative">
                            <button
                                type="button"
                                title="Choose playback device"
                                :disabled="
                                    devices.devicesHttp.processing ||
                                    devices.transferBusy.value
                                "
                                :class="[
                                    'grid size-8 place-items-center rounded-md transition-colors disabled:opacity-50',
                                    devices.devicesOpen.value
                                        ? 'text-primary'
                                        : 'text-muted-foreground hover:text-foreground',
                                    devices.devicesHttp.processing ||
                                    devices.transferBusy.value
                                        ? 'cursor-not-allowed'
                                        : 'cursor-pointer',
                                ]"
                                @click="
                                    devices.devicesOpen.value =
                                        !devices.devicesOpen.value;
                                    if (devices.devicesOpen.value)
                                        devices.refreshDevices(true);
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

                                <div
                                    class="max-h-52 space-y-1 overflow-auto pr-1"
                                >
                                    <button
                                        v-for="device in devices
                                            .selectableDevices.value"
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
                                            devices.transferBusy.value ||
                                            !device.id
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

                        <VolumeControl
                            :initial-volume="data?.volume_percent ?? null"
                            :local-player="webPlayer.localPlayer.value"
                            :local-playback-active="
                                webPlayer.localPlaybackActive.value
                            "
                            orientation="horizontal"
                            :show-icon="true"
                        />
                    </div>
                </div>
            </div>

            <p
                v-if="localStatus && hasTrack"
                class="mx-auto max-w-7xl px-4 pb-2 text-[11px] text-muted-foreground sm:px-6"
            >
                {{ localStatus }}
            </p>
        </div>

        <Transition
            enter-active-class="transition-all duration-300 ease-out"
            enter-from-class="opacity-0 translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition-all duration-200 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-1"
        >
            <div
                v-if="isPlayingExternally"
                class="flex items-center justify-center gap-2 border-t border-border/40 bg-secondary/60 px-4 py-1.5 text-[11px] text-muted-foreground backdrop-blur-sm"
            >
                <span class="flex items-center gap-1.5">
                    <span class="relative flex size-1.5">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-accent opacity-60" />
                        <span class="relative inline-flex size-1.5 rounded-full bg-accent" />
                    </span>
                    Playing on
                    <span class="font-medium text-foreground">{{ activeDeviceName ?? 'external device' }}</span>
                </span>
                <span class="text-border">·</span>
                <button
                    type="button"
                    class="font-medium text-accent transition-colors hover:text-accent/80"
                    @click="transferToLocalPlayer"
                >
                    Play here
                </button>
            </div>
        </Transition>
    </div>

    <TrackInsightsPanel
        :open="insightsOpen"
        :track="data?.track ?? null"
        @close="insightsOpen = false"
    />
</template>
