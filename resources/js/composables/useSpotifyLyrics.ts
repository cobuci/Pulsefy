import { useHttp } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { lyrics } from '@/routes/player';
import type { LyricsResponse, SpotifyTrack } from '@/types/spotify';

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

    const lyricsData = computed<LyricsResponse | null>(() => {
        return (lyricsHttp.response as LyricsResponse | null) ?? null;
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
        lyricsOpen,
        lyricsData,
        parsedSyncedLyrics,
        activeLyricLineIndex,
        fetchLyricsForCurrentTrack,
        retryLyricsFetch,
    };
}
