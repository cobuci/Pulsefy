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
    external_urls: { spotify: string };
}

export interface SpotifyTrack {
    id: string;
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
