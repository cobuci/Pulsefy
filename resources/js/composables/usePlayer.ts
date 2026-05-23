import type { ComputedRef, Ref } from 'vue';
import { computed, ref } from 'vue';
import {
    favorite as favoriteRoute,
    nowPlaying as nowPlayingRoute,
    pause as pauseRoute,
    play as playRoute,
} from '@/routes/player';
import type { NowPlaying } from '@/types/spotify';
import { getCsrfToken } from '@/utils/csrf';

export type UsePlayerReturn = {
    nowPlayingData: Ref<NowPlaying | null>;
    isPlayingTrack: ComputedRef<(trackId: string) => boolean>;
    isCurrentTrackSaved: ComputedRef<boolean>;
    fetchNowPlaying: () => Promise<void>;
    pausePlayback: () => Promise<void>;
    playTrack: (
        spotifyUri: string,
        options?: { uris?: string[]; offsetPosition?: number },
    ) => Promise<void>;
    toggleSaveTrack: () => Promise<void>;
};

const nowPlayingData = ref<NowPlaying | null>(null);

const isPlayingTrack = computed(
    () => (trackId: string) =>
        !!(
            nowPlayingData.value?.is_playing &&
            nowPlayingData.value.track?.id === trackId
        ),
);

const isCurrentTrackSaved = computed(
    () => nowPlayingData.value?.is_saved ?? false,
);

async function fetchNowPlaying(): Promise<void> {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        const response = await fetch(nowPlayingRoute.url(), {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (response.status === 204 || !response.ok) {
            nowPlayingData.value = null;

            return;
        }

        nowPlayingData.value = (await response.json()) as NowPlaying;
    } catch {
        nowPlayingData.value = null;
    }
}

async function playTrack(
    spotifyUri: string,
    options?: { uris?: string[]; offsetPosition?: number },
): Promise<void> {
    try {
        const uris = options?.uris?.filter((value) => value !== '') ?? [];
        const offsetPosition = options?.offsetPosition;

        const response = await fetch(playRoute.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(
                uris.length > 0
                    ? {
                          uris,
                          offset_position: offsetPosition,
                      }
                    : { uri: spotifyUri },
            ),
        });

        const body = await response.json();

        if (!body.ok) {
            return;
        }

        await new Promise((resolve) => setTimeout(resolve, 600));
        await fetchNowPlaying();
    } catch {}
}

async function pausePlayback(): Promise<void> {
    try {
        await fetch(pauseRoute.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        await new Promise((resolve) => setTimeout(resolve, 250));
        await fetchNowPlaying();
    } catch {}
}

async function toggleSaveTrack(): Promise<void> {
    const trackId = nowPlayingData.value?.track?.id;

    if (!trackId || !nowPlayingData.value) {
        return;
    }

    const currentlySaved = nowPlayingData.value.is_saved;
    const nextSaved = !currentlySaved;

    nowPlayingData.value = { ...nowPlayingData.value, is_saved: nextSaved };

    try {
        const response = await fetch(favoriteRoute.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ track_id: trackId, favorite: nextSaved }),
        });

        const payload = (await response.json()) as {
            ok?: boolean;
            favorite?: boolean;
        };

        if (!payload.ok && nowPlayingData.value) {
            nowPlayingData.value = {
                ...nowPlayingData.value,
                is_saved:
                    typeof payload.favorite === 'boolean'
                        ? payload.favorite
                        : currentlySaved,
            };
        }
    } catch {
        if (nowPlayingData.value) {
            nowPlayingData.value = {
                ...nowPlayingData.value,
                is_saved: currentlySaved,
            };
        }
    }
}

const playerStore: UsePlayerReturn = {
    nowPlayingData,
    isPlayingTrack,
    isCurrentTrackSaved,
    fetchNowPlaying,
    pausePlayback,
    playTrack,
    toggleSaveTrack,
};

export function usePlayer(): UsePlayerReturn {
    return playerStore;
}
