import { h } from 'vue';
import { toast } from 'vue-sonner';
import type { QueuedTrackPreview, SpotifyTrack } from '@/types/spotify';
import type { SpotifyWebPlaybackTrack } from '@/types/spotify-web-playback';

export const PLAYER_TRACK_TOAST_POSITION = 'top-right' as const;

/** Max playback position to treat a track as "just started" (now playing toast). */
export const NOW_PLAYING_STARTED_MAX_PROGRESS_MS = 5_000;

/** Auto-dismiss duration for the now playing started toast. */
export const NOW_PLAYING_STARTED_TOAST_DURATION_MS = 5_000;

export const UPCOMING_TRACK_THRESHOLD_MS = 15_000;

export const PLAYER_TRACK_TOAST_CLASS = 'player-track-toast';

export function mapSpotifyTrackToPreview(track: SpotifyTrack): QueuedTrackPreview {
    return {
        id: track.id,
        name: track.name,
        artists: track.artists.map((artist) => ({ id: artist.id, name: artist.name })),
        album: { images: track.album.images },
        duration_ms: track.duration_ms,
        external_urls: track.external_urls,
    };
}

export function mapWebPlaybackTrackToPreview(
    track: SpotifyWebPlaybackTrack,
): QueuedTrackPreview | null {
    if (!track.id) {
        return null;
    }

    return {
        id: track.id,
        name: track.name,
        artists: track.artists.map((artist) => ({ name: artist.name })),
        album: { images: track.album.images },
        duration_ms: track.duration_ms,
    };
}

function primaryArtistName(track: QueuedTrackPreview): string {
    return track.artists[0]?.name ?? 'Unknown artist';
}

function renderPlayerTrackToastContent(label: string, track: QueuedTrackPreview) {
    const artistName = primaryArtistName(track);
    const coverUrl = track.album?.images?.[0]?.url;

    const artwork = coverUrl
        ? h('img', {
              src: coverUrl,
              alt: '',
              class: 'size-11 shrink-0 rounded-lg object-cover ring-1 ring-border/70',
          })
        : h(
              'div',
              {
                  class: 'flex size-11 shrink-0 items-center justify-center rounded-lg bg-muted ring-1 ring-border/70',
                  'aria-hidden': 'true',
              },
              [h('span', { class: 'text-xs font-semibold text-muted-foreground' }, '♪')],
          );

    return h('div', { class: 'flex min-w-[272px] max-w-[340px]' }, [
        h('div', {
            class: 'w-1 shrink-0 bg-primary',
            'aria-hidden': 'true',
        }),
        h('div', { class: 'flex min-w-0 flex-1 items-center gap-3 py-3 pl-3.5 pr-10' }, [
            artwork,
            h('div', { class: 'flex min-w-0 flex-1 flex-col gap-1' }, [
                h(
                    'span',
                    {
                        class: 'text-[10px] font-semibold tracking-[0.18em] text-muted-foreground uppercase',
                    },
                    label,
                ),
                h(
                    'p',
                    {
                        class: 'truncate text-sm leading-tight font-semibold text-card-foreground',
                        title: track.name,
                    },
                    track.name,
                ),
                h(
                    'p',
                    {
                        class: 'truncate text-xs leading-tight text-muted-foreground',
                        title: artistName,
                    },
                    artistName,
                ),
            ]),
        ]),
    ]);
}

type ShowPlayerTrackToastOptions = {
    label: string;
    track: QueuedTrackPreview;
    toastClass: string;
    durationMs: number;
    closeButton?: boolean;
    onDismiss?: () => void;
};

export function showPlayerTrackToast({
    label,
    track,
    toastClass,
    durationMs,
    closeButton = true,
    onDismiss,
}: ShowPlayerTrackToastOptions): string | number {
    return toast.custom(() => renderPlayerTrackToastContent(label, track), {
        duration: durationMs,
        position: PLAYER_TRACK_TOAST_POSITION,
        unstyled: true,
        closeButton,
        classes: {
            toast: toastClass,
        },
        style: {
            backgroundColor: 'var(--card)',
            color: 'var(--card-foreground)',
            border: '1px solid var(--border)',
            borderRadius: '0.875rem',
            padding: '0',
            overflow: 'hidden',
            boxShadow: '0 12px 32px rgb(0 0 0 / 0.28)',
        },
        onDismiss,
    });
}

export function dismissPlayerTrackToast(toastId: string | number | null): void {
    if (toastId !== null) {
        toast.dismiss(toastId);
    }
}
