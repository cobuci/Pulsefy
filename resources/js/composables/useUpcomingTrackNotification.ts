import { onScopeDispose, ref, toValue, watch } from 'vue';
import type { MaybeRefOrGetter } from 'vue';
import {
    dismissPlayerTrackToast,
    PLAYER_TRACK_TOAST_CLASS,
    showPlayerTrackToast,
    UPCOMING_TRACK_THRESHOLD_MS,
} from '@/composables/playerTrackToast';
import { queue as playerQueueRoute } from '@/routes/player';
import type { PlayerQueueResponse, QueuedTrackPreview } from '@/types/spotify';

const UPCOMING_TOAST_CLASS = `${PLAYER_TRACK_TOAST_CLASS} upcoming-track-toast`;

const UPCOMING_TOAST_DURATION_MS = Number.POSITIVE_INFINITY;

type UseUpcomingTrackNotificationOptions = {
    progressMs: MaybeRefOrGetter<number>;
    durationMs: MaybeRefOrGetter<number>;
    isPlaying: MaybeRefOrGetter<boolean>;
    currentTrackId: MaybeRefOrGetter<string | null>;
    localNextTrack: MaybeRefOrGetter<QueuedTrackPreview | null>;
    preferApiQueue: MaybeRefOrGetter<boolean>;
};

function isWithinUpcomingWindow(
    progressMs: number,
    durationMs: number,
): boolean {
    if (durationMs <= 0) {
        return false;
    }

    return durationMs - progressMs <= UPCOMING_TRACK_THRESHOLD_MS;
}

export function useUpcomingTrackNotification(
    options: UseUpcomingTrackNotificationOptions,
): void {
    const notifiedForTrackId = ref<string | null>(null);
    const cachedNextTrack = ref<QueuedTrackPreview | null>(null);
    const queueFetchInFlight = ref(false);
    const activeToastId = ref<string | number | null>(null);

    function dismissUpcomingToast(): void {
        dismissPlayerTrackToast(activeToastId.value);
        activeToastId.value = null;
    }

    function resetUpcomingState(): void {
        dismissUpcomingToast();
        notifiedForTrackId.value = null;
        cachedNextTrack.value = null;
    }

    async function fetchNextTrackFromApi(): Promise<QueuedTrackPreview | null> {
        if (queueFetchInFlight.value) {
            return cachedNextTrack.value;
        }

        if (cachedNextTrack.value) {
            return cachedNextTrack.value;
        }

        queueFetchInFlight.value = true;

        try {
            const response = await fetch(playerQueueRoute.url(), {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                return null;
            }

            const payload = (await response.json()) as PlayerQueueResponse;
            const nextTrack = payload.next_track;

            if (!nextTrack?.id) {
                cachedNextTrack.value = null;

                return null;
            }

            cachedNextTrack.value = nextTrack;

            return nextTrack;
        } catch {
            return null;
        } finally {
            queueFetchInFlight.value = false;
        }
    }

    async function resolveNextTrack(): Promise<QueuedTrackPreview | null> {
        if (!toValue(options.preferApiQueue)) {
            const localNext = toValue(options.localNextTrack);

            if (localNext?.id) {
                return localNext;
            }
        }

        return fetchNextTrackFromApi();
    }

    function showUpcomingToast(nextTrack: QueuedTrackPreview): void {
        dismissUpcomingToast();

        activeToastId.value = showPlayerTrackToast({
            label: 'Up next',
            track: nextTrack,
            toastClass: UPCOMING_TOAST_CLASS,
            durationMs: UPCOMING_TOAST_DURATION_MS,
            onDismiss: () => {
                activeToastId.value = null;
            },
        });
    }

    async function maybeNotifyUpcomingTrack(): Promise<void> {
        const trackId = toValue(options.currentTrackId);
        const durationMs = toValue(options.durationMs);
        const progressMs = toValue(options.progressMs);

        if (!trackId || !toValue(options.isPlaying)) {
            return;
        }

        if (!isWithinUpcomingWindow(progressMs, durationMs)) {
            return;
        }

        if (notifiedForTrackId.value === trackId) {
            return;
        }

        const nextTrack = await resolveNextTrack();

        if (toValue(options.currentTrackId) !== trackId) {
            return;
        }

        if (!nextTrack?.id) {
            return;
        }

        notifiedForTrackId.value = trackId;
        showUpcomingToast(nextTrack);
    }

    watch(
        () => toValue(options.currentTrackId),
        (trackId, previousTrackId) => {
            if (trackId !== previousTrackId) {
                resetUpcomingState();
            }
        },
    );

    watch(
        () => ({
            nearEnd: isWithinUpcomingWindow(
                toValue(options.progressMs),
                toValue(options.durationMs),
            ),
            trackId: toValue(options.currentTrackId),
            playing: toValue(options.isPlaying),
            localNextId: toValue(options.localNextTrack)?.id ?? null,
            preferApiQueue: toValue(options.preferApiQueue),
        }),
        (state) => {
            if (!state.nearEnd || !state.trackId || !state.playing) {
                return;
            }

            void maybeNotifyUpcomingTrack();
        },
    );

    onScopeDispose(() => {
        resetUpcomingState();
    });
}
