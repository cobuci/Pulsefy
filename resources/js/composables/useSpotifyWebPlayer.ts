import { ref } from 'vue';
import { deviceToken } from '@/routes/player';
import { useHttp } from '@inertiajs/vue3';
import type {
    SpotifyPlayer,
    SpotifyWebPlaybackState,
} from '@/types/spotify-web-playback';

export type UseSpotifyWebPlayerReturn = {
    localPlayer: ReturnType<typeof ref<SpotifyPlayer | null>>;
    localDeviceId: ReturnType<typeof ref<string | null>>;
    localPlayerReady: ReturnType<typeof ref<boolean>>;
    localPlaybackActive: ReturnType<typeof ref<boolean>>;
    localPlayerInitializing: ReturnType<typeof ref<boolean>>;
    localPlayerSupported: ReturnType<typeof ref<boolean>>;
    initLocalPlayer: () => Promise<void>;
    syncFromLocalState: (
        state: SpotifyWebPlaybackState | null,
        onTrackChanged: () => void,
        trackId: () => string | undefined,
        onProgress: (pct: number) => void,
        duration: () => number,
    ) => void;
    onPlayerStatus: (message: string) => void;
};

export function useSpotifyWebPlayer(
    onStatus: (message: string) => void,
    onReady: () => void,
) {
    const localPlayer = ref<SpotifyPlayer | null>(null);
    const localDeviceId = ref<string | null>(null);
    const localPlayerReady = ref(false);
    const localPlaybackActive = ref(false);
    const localPlayerInitializing = ref(false);
    const localPlayerSupported = ref(true);

    const deviceTokenHttp = useHttp<{ token: string }>();

    async function fetchDeviceToken(): Promise<string | null> {
        try {
            await deviceTokenHttp.get(deviceToken.url());

            return (
                (deviceTokenHttp.response as { token?: string } | undefined)
                    ?.token ?? null
            );
        } catch {
            return null;
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
            script.onerror = () =>
                reject(new Error('Failed to load Spotify SDK'));
            document.body.appendChild(script);
        });
    }

    async function initLocalPlayer(
        onStateChanged: (state: SpotifyWebPlaybackState | null) => void,
    ) {
        if (localPlayer.value || localPlayerInitializing.value) {
            return;
        }

        localPlayerInitializing.value = true;

        try {
            if (!('mediaCapabilities' in navigator)) {
                localPlayerSupported.value = false;
                onStatus(
                    'Your browser may not fully support secure playback capabilities for Spotify Web Player.',
                );

                return;
            }

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

            player.addListener(
                'ready',
                ({ device_id }: { device_id: string }) => {
                    localDeviceId.value = device_id;
                    localPlayerReady.value = true;
                    onReady();
                },
            );

            player.addListener('not_ready', () => {
                localPlayerReady.value = false;
                localPlaybackActive.value = false;
            });

            player.addListener('player_state_changed', (state) => {
                onStateChanged(state as SpotifyWebPlaybackState | null);
            });

            player.addListener('initialization_error', () => {
                localPlayerReady.value = false;
                localPlayerSupported.value = false;
                onStatus(
                    'Spotify Web Playback failed to initialize. Browser DRM support may be restricted.',
                );
            });

            player.addListener('authentication_error', () => {
                localPlayerReady.value = false;
                onStatus(
                    'Spotify Web Playback permission denied. Reconnect Spotify with streaming scope.',
                );
            });

            player.addListener('account_error', () => {
                localPlayerReady.value = false;
                onStatus(
                    'Spotify Premium is required for browser playback (Web Playback SDK).',
                );
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

    function syncFromLocalState(
        state: SpotifyWebPlaybackState | null,
        onTrackChanged: () => void,
        currentTrackId: () => string | undefined,
        onProgress: (pct: number) => void,
        duration: () => number,
    ) {
        if (!state) {
            localPlaybackActive.value = false;

            return;
        }

        localPlaybackActive.value = true;

        const dur = state.duration || duration() || 0;

        if (dur > 0) {
            onProgress((state.position / dur) * 100);
        }

        if (currentTrackId() !== state.track_window.current_track.id) {
            onTrackChanged();
        }
    }

    function disconnect() {
        if (localPlayer.value) {
            localPlayer.value.disconnect();
            localPlayer.value = null;
        }
    }

    return {
        localPlayer,
        localDeviceId,
        localPlayerReady,
        localPlaybackActive,
        localPlayerInitializing,
        localPlayerSupported,
        initLocalPlayer,
        syncFromLocalState,
        disconnect,
    };
}
