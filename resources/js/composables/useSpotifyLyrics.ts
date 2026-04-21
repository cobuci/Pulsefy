import { useHttp } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { lyrics } from '@/routes/player';
import { romanize as romanizeLyrics, translate as translateLyrics } from '@/routes/player/lyrics';
import type { LyricsResponse, LyricsPronunciationStatus, LyricsTranslationStatus, SpotifyTrack } from '@/types/spotify';
import { getCsrfToken } from '@/utils/csrf';

export type ParsedLyricLine = {
    timeMs: number;
    text: string;
    translation: string | null;
};

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
                await fetchLyrics(true);

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
                await fetchLyrics(true);

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

    const parsedSyncedLyrics = computed<ParsedLyricLine[]>(() => {
        if (lyricsData.value?.type !== 'synced' || !lyricsData.value.lyrics) {
            return [];
        }

        const lines = lyricsData.value.lyrics.split('\n');

        const parsedLines: Array<ParsedLyricLine | null> = lines.map(
            (line: string): ParsedLyricLine | null => {
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
                    translation: null,
                };
            },
        );

        return parsedLines
            .filter((line: ParsedLyricLine | null): line is ParsedLyricLine => line !== null)
            .sort((a: ParsedLyricLine, b: ParsedLyricLine) => a.timeMs - b.timeMs);
    });

    const activeLyricLineIndex = computed(() => {
        if (!parsedSyncedLyrics.value.length) {
            return -1;
        }

        let index = -1;

        for (let i = 0; i < parsedSyncedLyrics.value.length; i += 1) {
            if (progressMs() >= parsedSyncedLyrics.value[i].timeMs) {
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
        translatedLinesByIndex,
        romanizedLinesByIndex,
        parsedSyncedLyrics,
        activeLyricLineIndex,
        fetchLyricsForCurrentTrack,
        retryLyricsFetch,
        requestTranslation,
        requestRomanization,
    };
}
