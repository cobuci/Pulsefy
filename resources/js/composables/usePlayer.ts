import type { ComputedRef, Ref } from 'vue';
import { computed, ref } from 'vue';
import {
    nowPlaying as nowPlayingRoute,
    play as playRoute,
} from '@/routes/player';
import type { NowPlaying } from '@/types/spotify';
import { getCsrfToken } from '@/utils/csrf';

export type UsePlayerReturn = {
    nowPlayingData: Ref<NowPlaying | null>;
    isPlayingTrack: ComputedRef<(trackId: string) => boolean>;
    fetchNowPlaying: () => Promise<void>;
    playTrack: (
        spotifyUri: string,
        options?: { uris?: string[]; offsetPosition?: number },
    ) => Promise<void>;
};

const nowPlayingData = ref<NowPlaying | null>(null);

const isPlayingTrack = computed(
    () => (trackId: string) =>
        !!(
            nowPlayingData.value?.is_playing &&
            nowPlayingData.value.track?.id === trackId
        ),
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

const playerStore: UsePlayerReturn = {
    nowPlayingData,
    isPlayingTrack,
    fetchNowPlaying,
    playTrack,
};

export function usePlayer(): UsePlayerReturn {
    return playerStore;
}
