import type { ContextMenuItem } from '@/components/ui/context-menu';
import { useContextMenu } from '@/composables/useContextMenu';
import { favorite as favoriteRoute } from '@/routes/player';
import type { SpotifyTrack } from '@/types/spotify';
import { getCsrfToken } from '@/utils/csrf';

async function toggleSaveTrack(trackId: string, save: boolean): Promise<boolean> {
    try {
        const response = await fetch(favoriteRoute.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ track_id: trackId, favorite: save }),
        });

        const payload = (await response.json()) as { ok?: boolean; favorite?: boolean };

        return payload.ok === true && payload.favorite === save;
    } catch {
        return false;
    }
}

export function useTrackContextMenu() {
    const contextMenu = useContextMenu();

    function buildTrackMenuItems(
        track: SpotifyTrack,
        options: {
            isSaved?: boolean;
            onSaveToggled?: (saved: boolean) => void;
        } = {},
    ): ContextMenuItem[] {
        const items: ContextMenuItem[] = [];

        if (track.id) {
            const isSaved = options.isSaved ?? false;

            items.push({
                key: `track-save-${track.id}`,
                label: isSaved ? 'Remove from Liked Songs' : 'Save to Liked Songs',
                onSelect: async () => {
                    const success = await toggleSaveTrack(track.id, !isSaved);

                    if (success) {
                        options.onSaveToggled?.(!isSaved);
                    }
                },
            });
        }

        if (track.external_urls?.spotify) {
            if (items.length > 0) {
                items.push({ key: `track-sep-${track.id}`, separator: true });
            }

            items.push({
                key: `track-open-spotify-${track.id}`,
                label: 'Open in Spotify',
                onSelect: () => {
                    window.open(track.external_urls.spotify, '_blank', 'noopener,noreferrer');
                },
            });
        }

        return items;
    }

    function openTrackContextMenu(
        event: MouseEvent,
        track: SpotifyTrack,
        options: {
            isSaved?: boolean;
            onSaveToggled?: (saved: boolean) => void;
        } = {},
    ): void {
        contextMenu.open(event, buildTrackMenuItems(track, options));
    }

    return {
        openTrackContextMenu,
        buildTrackMenuItems,
    };
}
