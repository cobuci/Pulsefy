import { computed, ref } from 'vue';
import TrackInsightsController from '@/actions/App/Http/Controllers/Player/TrackInsightsController';
import TrackInsightsRegenerateController from '@/actions/App/Http/Controllers/Player/TrackInsightsRegenerateController';
import TrackInsightsStatusController from '@/actions/App/Http/Controllers/Player/TrackInsightsStatusController';
import type { NowPlaying, TrackInsightsData, TrackInsightStatus, TrackInsightsResponse, TrackInsightsUpdatedEvent } from '@/types/spotify';
import { getCsrfToken } from '@/utils/csrf';

export type InsightsLanguage = 'en' | 'pt';

export function useTrackInsights() {
    const status = ref<TrackInsightStatus | null>(null);
    const insights = ref<TrackInsightsData | null>(null);
    const errorMessage = ref<string | null>(null);
    const isBusy = ref(false);
    const language = ref<InsightsLanguage>('en');

    const isLoading = computed(() => status.value === 'queued' || status.value === 'processing');
    const isReady = computed(() => status.value === 'ready');
    const hasFailed = computed(() => status.value === 'failed');

    const localizedInsights = computed(() => {
        if (!insights.value) {
            return null;
        }

        const d = insights.value;
        const pt = language.value === 'pt';

        return {
            summary: pt ? d.summary_pt : d.summary,
            mood: pt ? d.mood_pt : d.mood,
            meaning: pt ? d.meaning_pt : d.meaning,
            themes: pt ? d.themes_pt : d.themes,
            trivia: pt ? d.trivia_pt : d.trivia,
            similar: pt ? d.similar_pt : d.similar,
        };
    });

    function toggleLanguage(): void {
        language.value = language.value === 'en' ? 'pt' : 'en';
    }

    function applyResponse(data: TrackInsightsResponse): void {
        status.value = data.status;
        insights.value = data.insights;
        errorMessage.value = data.error_message;
    }

    async function fetchStatus(trackId: string): Promise<void> {
        try {
            const response = await fetch(
                TrackInsightsStatusController.url({ query: { track_id: trackId } }),
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
            );

            if (!response.ok) {
                return;
            }

            const data = (await response.json()) as TrackInsightsResponse;
            applyResponse(data);
        } catch {}
    }

    async function open(track: NowPlaying['track']): Promise<void> {
        await fetchStatus(track.id);

        if (status.value === null) {
            await request(track);
        }
    }

    async function request(track: NowPlaying['track']): Promise<void> {
        if (isBusy.value) {
            return;
        }

        isBusy.value = true;

        try {
            const response = await fetch(TrackInsightsController.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    track_id: track.id,
                    track_name: track.name,
                    artist_name: track.artists.map((a) => a.name).join(', '),
                    album_name: track.album.name,
                }),
            });

            if (!response.ok) {
                return;
            }

            const data = (await response.json()) as TrackInsightsResponse;
            applyResponse(data);
        } finally {
            isBusy.value = false;
        }
    }

    async function regenerate(track: NowPlaying['track']): Promise<void> {
        if (isBusy.value) {
            return;
        }

        isBusy.value = true;
        insights.value = null;
        status.value = 'queued';
        errorMessage.value = null;

        try {
            const response = await fetch(TrackInsightsRegenerateController.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    track_id: track.id,
                    track_name: track.name,
                    artist_name: track.artists.map((a) => a.name).join(', '),
                    album_name: track.album.name,
                }),
            });

            if (!response.ok) {
                await fetchStatus(track.id);

                return;
            }

            const data = (await response.json()) as TrackInsightsResponse;
            applyResponse(data);
        } finally {
            isBusy.value = false;
        }
    }

    function handleBroadcast(trackId: string, event: TrackInsightsUpdatedEvent): void {
        if (event.trackId !== trackId) {
            return;
        }

        status.value = event.status;
        insights.value = event.insights;
        errorMessage.value = event.errorMessage;
    }

    function reset(): void {
        status.value = null;
        insights.value = null;
        errorMessage.value = null;
        isBusy.value = false;
    }

    function listenForUpdates(trackId: string): () => void {
        const channel = window.Echo.channel(`track-insights.${trackId}`);

        channel.listen('.TrackInsights.Updated', (event: TrackInsightsUpdatedEvent) => {
            handleBroadcast(trackId, event);
        });

        return () => {
            channel.stopListening('.TrackInsights.Updated');
        };
    }

    return {
        status,
        insights,
        localizedInsights,
        language,
        toggleLanguage,
        errorMessage,
        isLoading,
        isReady,
        hasFailed,
        isBusy,
        open,
        request,
        regenerate,
        reset,
        listenForUpdates,
    };
}
