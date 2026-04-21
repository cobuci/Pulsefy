import type { ContextMenuItem } from '@/components/ui/context-menu';
import { useContextMenu } from '@/composables/useContextMenu';
import { check as checkFavoriteRoute } from '@/routes/player/favorite';
import { favorite as favoriteRoute } from '@/routes/player';
import type { SpotifyTrack } from '@/types/spotify';
import { getCsrfToken } from '@/utils/csrf';
import { Heart, HeartOff } from 'lucide-vue-next';

async function fetchIsSaved(trackId: string): Promise<boolean> {
    try {
        const url = checkFavoriteRoute.url({ query: { track_id: trackId } });
        const response = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const payload = (await response.json()) as { ok?: boolean; saved?: boolean };
        return payload.ok === true && payload.saved === true;
    } catch {
        return false;
    }
}

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

function buildSaveItem(
    trackId: string,
    isSaved: boolean,
    onSaveToggled?: (saved: boolean) => void,
): ContextMenuItem {
    return {
        key: `track-save-${trackId}`,
        label: isSaved ? 'Remove from Liked Songs' : 'Save to Liked Songs',
        icon: isSaved ? HeartOff : Heart,
        onSelect: async () => {
            const success = await toggleSaveTrack(trackId, !isSaved);

            if (success) {
                onSaveToggled?.(!isSaved);
            }
        },
    };
}

export function useTrackContextMenu() {
    const contextMenu = useContextMenu();

    async function openTrackContextMenu(
        event: MouseEvent,
        track: SpotifyTrack,
        options: {
            onSaveToggled?: (saved: boolean) => void;
        } = {},
    ): Promise<void> {
        if (!track.id) {
            return;
        }

        contextMenu.open(event, [{ key: `track-save-loading-${track.id}`, loading: true }]);

        const isSaved = await fetchIsSaved(track.id);

        contextMenu.updateItems([buildSaveItem(track.id, isSaved, options.onSaveToggled)]);
    }

    return {
        openTrackContextMenu,
    };
}
