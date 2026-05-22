import { onScopeDispose, ref, toValue, watch } from 'vue';
import type { MaybeRefOrGetter } from 'vue';
import {
    dismissPlayerTrackToast,
    mapSpotifyTrackToPreview,
    NOW_PLAYING_STARTED_MAX_PROGRESS_MS,
    NOW_PLAYING_STARTED_TOAST_DURATION_MS,
    PLAYER_TRACK_TOAST_CLASS,
    showPlayerTrackToast,
} from '@/composables/playerTrackToast';
import type { SpotifyTrack } from '@/types/spotify';

const NOW_PLAYING_TOAST_CLASS = `${PLAYER_TRACK_TOAST_CLASS} now-playing-track-toast`;

type UseNowPlayingStartedNotificationOptions = {
    currentTrackId: MaybeRefOrGetter<string | null>;
    isPlaying: MaybeRefOrGetter<boolean>;
    progressMs: MaybeRefOrGetter<number>;
    currentTrack: MaybeRefOrGetter<SpotifyTrack | null | undefined>;
};

export function useNowPlayingStartedNotification(
    options: UseNowPlayingStartedNotificationOptions,
): void {
    const announcedTrackId = ref<string | null>(null);
    const activeToastId = ref<string | number | null>(null);

    function dismissNowPlayingToast(): void {
        dismissPlayerTrackToast(activeToastId.value);
        activeToastId.value = null;
    }

    function maybeNotifyTrackJustStarted(): void {
        const trackId = toValue(options.currentTrackId);
        const track = toValue(options.currentTrack);
        const playing = toValue(options.isPlaying);
        const progressMs = toValue(options.progressMs);

        if (!trackId || !track || !playing) {
            return;
        }

        if (progressMs > NOW_PLAYING_STARTED_MAX_PROGRESS_MS) {
            return;
        }

        if (announcedTrackId.value === trackId) {
            return;
        }

        announcedTrackId.value = trackId;

        activeToastId.value = showPlayerTrackToast({
            label: 'Now playing',
            track: mapSpotifyTrackToPreview(track),
            toastClass: NOW_PLAYING_TOAST_CLASS,
            durationMs: NOW_PLAYING_STARTED_TOAST_DURATION_MS,
            closeButton: false,
            onDismiss: () => {
                activeToastId.value = null;
            },
        });
    }

    watch(
        () => toValue(options.currentTrackId),
        (trackId, previousTrackId) => {
            if (trackId === previousTrackId) {
                return;
            }

            dismissNowPlayingToast();
            announcedTrackId.value = null;
        },
    );

    watch(
        () => ({
            trackId: toValue(options.currentTrackId),
            playing: toValue(options.isPlaying),
            progressMs: toValue(options.progressMs),
            trackPresent: !!toValue(options.currentTrack),
        }),
        (state) => {
            if (!state.trackId || !state.playing || !state.trackPresent) {
                return;
            }

            maybeNotifyTrackJustStarted();
        },
    );

    onScopeDispose(() => {
        dismissNowPlayingToast();
    });
}
