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
}

export type LyricsTranslationStatus = 'queued' | 'processing' | 'ready' | 'failed';

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
}

export interface LyricTranslatedLine {
    index: number;
    timestamp: string | null;
    text: string;
    source_lang: 'en' | 'pt-BR' | 'other' | 'mixed';
    pt_br: string | null;
    en: string | null;
}

export interface LyricsTranslationUpdatedEvent {
    trackId: string;
    status: LyricsTranslationStatus;
    translatedLines: LyricTranslatedLine[] | null;
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
