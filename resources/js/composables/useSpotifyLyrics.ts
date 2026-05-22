import { useHttp } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { lyrics } from '@/routes/player';
import { romanize as romanizeLyrics, translate as translateLyrics } from '@/routes/player/lyrics';
import type { LyricsResponse, LyricsPronunciationStatus, LyricsTranslationStatus, SpotifyTrack } from '@/types/spotify';
import { getCsrfToken } from '@/utils/csrf';

export type DisplayLyricLine = {
    sourceIndex: number;
    text: string;
    timeMs: number | null;
};

const LRC_TIMESTAMP_PATTERN = /^\[(\d{1,2}):(\d{2})(?:\.(\d{1,3}))?\]\s*(.*)$/;

function parseTimestampedLine(line: string, sourceIndex: number): DisplayLyricLine | null {
    const match = line.match(LRC_TIMESTAMP_PATTERN);

    if (!match) {
        return null;
    }

    const minutes = Number(match[1]);
    const seconds = Number(match[2]);
    const fraction = (match[3] ?? '0').padEnd(3, '0').slice(0, 3);
    const milliseconds = Number(fraction);
    const text = (match[4] ?? '').trim();

    return {
        sourceIndex,
        text,
        timeMs: minutes * 60_000 + seconds * 1_000 + milliseconds,
    };
}

function parseLyricsLines(rawLyrics: string, synced: boolean): DisplayLyricLine[] {
    const lines = rawLyrics.split('\n');

    if (synced) {
        return lines
            .map((line, sourceIndex) => parseTimestampedLine(line, sourceIndex))
            .filter((line): line is DisplayLyricLine => line !== null && line.text !== '')
            .sort((a, b) => (a.timeMs ?? 0) - (b.timeMs ?? 0));
    }

    return lines
        .map((line, sourceIndex) => ({
            sourceIndex,
            text: line.trim(),
            timeMs: null,
        }))
        .filter((line) => line.text !== '');
}

export function useSpotifyLyrics(
    currentTrack: () => SpotifyTrack | undefined,
    progressMs: () => number,
) {
    const lyricsHttp = useHttp<LyricsResponse>();
    const lyricsOpen = ref(false);
    const translationRequestBusy = ref(false);
    const romanizationRequestBusy = ref(false);

    const lyricsData = computed<LyricsResponse | null>(() => {
        return (lyricsHttp.response as LyricsResponse | null) ?? null;
    });

    const lyricsAreSynced = computed(() => lyricsData.value?.synced === true);

    const displayLyricLines = computed<DisplayLyricLine[]>(() => {
        const payload = lyricsData.value;

        if (!payload?.lyrics || payload.type === 'none') {
            return [];
        }

        return parseLyricsLines(payload.lyrics, payload.synced);
    });

    const hasLyricsContent = computed(() => displayLyricLines.value.length > 0);

    const translatedLinesByIndex = computed(() => {
        const lines = lyricsData.value?.translation?.translated_lines ?? [];

        return new Map(lines.map((line) => [line.index, line]));
    });

    const romanizedLinesByIndex = computed(() => {
        const lines = lyricsData.value?.romanization?.romanized_lines ?? [];

        return new Map(lines.map((line) => [line.index, line]));
    });

    async function fetchLyrics(forceRefresh: boolean) {
        const track = currentTrack();

        if (!track) {
            return;
        }

        const artist = track.artists.map((item) => item.name).join(', ');

        try {
            await lyricsHttp.get(
                lyrics.url({
                    query: {
                        track_id: track.id,
                        artist,
                        track_name: track.name,
                        album_name: track.album.name,
                        duration: track.duration_ms,
                        force_refresh: forceRefresh,
                    },
                }),
            );
        } catch {}
    }

    function retryLyricsFetch() {
        void fetchLyrics(true);
    }

    function fetchLyricsForCurrentTrack() {
        void fetchLyrics(false);
    }

    async function requestTranslation(): Promise<void> {
        const track = currentTrack();

        if (!track || translationRequestBusy.value) {
            return;
        }

        const artist = track.artists.map((item) => item.name).join(', ');

        translationRequestBusy.value = true;

        try {
            const response = await fetch(translateLyrics.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    track_id: track.id,
                    artist,
                    track_name: track.name,
                    album_name: track.album.name,
                    duration: track.duration_ms,
                }),
            });

            if (!response.ok) {
                return;
            }

            await fetchLyrics(true);

            const status = (lyricsData.value?.translation?.status ?? null) as LyricsTranslationStatus | null;

            if (status === 'queued' || status === 'processing') {
                const maxAttempts = 18;

                for (let attempt = 0; attempt < maxAttempts; attempt += 1) {
                    await new Promise((resolve) => setTimeout(resolve, 1000));
                    await fetchLyrics(true);

                    const currentStatus = (lyricsData.value?.translation?.status ?? null) as LyricsTranslationStatus | null;

                    if (currentStatus === 'ready' || currentStatus === 'failed') {
                        break;
                    }
                }
            }
        } finally {
            translationRequestBusy.value = false;
        }
    }

    async function requestRomanization(): Promise<void> {
        const track = currentTrack();

        if (!track || romanizationRequestBusy.value) {
            return;
        }

        const artist = track.artists.map((item) => item.name).join(', ');

        romanizationRequestBusy.value = true;

        try {
            const response = await fetch(romanizeLyrics.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    track_id: track.id,
                    artist,
                    track_name: track.name,
                    album_name: track.album.name,
                    duration: track.duration_ms,
                }),
            });

            if (!response.ok) {
                return;
            }

            await fetchLyrics(true);

            const status = (lyricsData.value?.romanization?.status ?? null) as LyricsPronunciationStatus | null;

            if (status === 'queued' || status === 'processing') {
                const maxAttempts = 18;

                for (let attempt = 0; attempt < maxAttempts; attempt += 1) {
                    await new Promise((resolve) => setTimeout(resolve, 1000));
                    await fetchLyrics(true);

                    const currentStatus = (lyricsData.value?.romanization?.status ?? null) as LyricsPronunciationStatus | null;

                    if (currentStatus === 'ready' || currentStatus === 'failed') {
                        break;
                    }
                }
            }
        } finally {
            romanizationRequestBusy.value = false;
        }
    }

    const activeLyricLineIndex = computed(() => {
        if (!lyricsAreSynced.value || !displayLyricLines.value.length) {
            return -1;
        }

        let index = -1;

        for (let i = 0; i < displayLyricLines.value.length; i += 1) {
            const line = displayLyricLines.value[i];

            if (line.timeMs !== null && progressMs() >= line.timeMs) {
                index = i;
            } else {
                break;
            }
        }

        return index;
    });

    return {
        lyricsHttp,
        translationRequestBusy,
        romanizationRequestBusy,
        lyricsOpen,
        lyricsData,
        lyricsAreSynced,
        hasLyricsContent,
        translatedLinesByIndex,
        romanizedLinesByIndex,
        displayLyricLines,
        activeLyricLineIndex,
        fetchLyricsForCurrentTrack,
        retryLyricsFetch,
        requestTranslation,
        requestRomanization,
    };
}
