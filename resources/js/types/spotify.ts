export interface SpotifyImage {
    url: string;
    height: number | null;
    width: number | null;
}

export interface SpotifyArtist {
    id: string;
    name: string;
    images: SpotifyImage[];
    genres: string[];
    popularity: number;
    external_urls: { spotify: string };
}

export interface SpotifyAlbum {
    id: string;
    name: string;
    images: SpotifyImage[];
    release_date: string;
    album_type?: string;
    total_tracks?: number;
    external_urls: { spotify: string };
}

export interface SpotifyArtistAlbum extends SpotifyAlbum {
    album_group?: string;
}

export interface SpotifyTrack {
    id: string;
    uri?: string;
    name: string;
    artists: Pick<SpotifyArtist, 'id' | 'name' | 'external_urls'>[];
    album: SpotifyAlbum;
    duration_ms: number;
    popularity: number;
    preview_url: string | null;
    external_urls: { spotify: string };
}

export interface RecentPlayContext {
    type: string;
    href: string;
    external_urls: { spotify: string };
    uri: string;
}

export interface RecentPlay {
    track: SpotifyTrack;
    played_at: string;
    context: RecentPlayContext | null;
}

export type TimeRange = 'short_term' | 'medium_term' | 'long_term';

export interface NowPlaying {
    is_playing: boolean;
    shuffle_state: boolean;
    repeat_state: 'off' | 'track' | 'context';
    progress_ms: number;
    volume_percent: number | null;
    track: SpotifyTrack;
    is_saved: boolean;
}

export type LyricsTranslationStatus = 'queued' | 'processing' | 'ready' | 'failed';

export type LyricsPronunciationStatus = 'queued' | 'processing' | 'ready' | 'failed';

export interface LyricsResponse {
    track_id: string;
    type: 'synced' | 'plain' | 'none';
    lyrics: string | null;
    synced: boolean;
    translation: {
        status: LyricsTranslationStatus | null;
        translated_lines: LyricTranslatedLine[] | null;
        provider: string | null;
        model: string | null;
        error_message: string | null;
    };
    romanization: {
        status: LyricsPronunciationStatus | null;
        romanized_lines: LyricRomanizedLine[] | null;
        provider: string | null;
        model: string | null;
        error_message: string | null;
    };
}

export interface LyricTranslatedLine {
    index: number;
    timestamp: string | null;
    text: string;
    source_lang: 'en' | 'pt-BR' | 'other' | 'mixed';
    pt_br: string | null;
    en: string | null;
}

export interface LyricRomanizedLine {
    index: number;
    timestamp: string | null;
    pt_br: string | null;
    en: string | null;
}

export interface LyricsTranslationUpdatedEvent {
    trackId: string;
    status: LyricsTranslationStatus;
    translatedLines: LyricTranslatedLine[] | null;
    errorMessage: string | null;
}

export interface LyricsPronunciationUpdatedEvent {
    trackId: string;
    status: LyricsPronunciationStatus;
    romanizedLines: LyricRomanizedLine[] | null;
    errorMessage: string | null;
}

export interface SpotifyDevice {
    id: string | null;
    is_active: boolean;
    is_private_session: boolean;
    is_restricted: boolean;
    name: string;
    type: string;
    volume_percent: number | null;
    supports_volume: boolean;
}

export type TrackInsightStatus = 'queued' | 'processing' | 'ready' | 'failed';

export interface TrackInsightsData {
    summary: string;
    summary_pt: string;
    mood: string;
    mood_pt: string;
    meaning: string;
    meaning_pt: string;
    themes: string[];
    themes_pt: string[];
    trivia: string[];
    trivia_pt: string[];
    similar: string[];
    similar_pt: string[];
}

export interface TrackInsightsResponse {
    track_id: string;
    status: TrackInsightStatus | null;
    insights: TrackInsightsData | null;
    error_message: string | null;
}

export interface TrackInsightsUpdatedEvent {
    trackId: string;
    status: TrackInsightStatus;
    insights: TrackInsightsData | null;
    errorMessage: string | null;
}
