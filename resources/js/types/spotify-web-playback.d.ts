declare global {
    interface Window {
        onSpotifyWebPlaybackSDKReady?: () => void;
        Spotify?: {
            Player: new (options: {
                name: string;
                getOAuthToken: (callback: (token: string) => void) => void;
                volume?: number;
            }) => SpotifyPlayer;
        };
    }
}

export interface SpotifyWebPlaybackTrack {
    id: string | null;
    uri: string;
    name: string;
    duration_ms: number;
    album: {
        name: string;
        uri: string;
        images: Array<{ url: string }>;
    };
    artists: Array<{ name: string; uri: string }>;
}

export interface SpotifyWebPlaybackState {
    paused: boolean;
    position: number;
    duration: number;
    shuffle: boolean;
    track_window: {
        current_track: SpotifyWebPlaybackTrack;
    };
}

export interface SpotifyPlayer {
    connect(): Promise<boolean>;
    disconnect(): void;
    addListener(
        event:
            | 'ready'
            | 'not_ready'
            | 'player_state_changed'
            | 'autoplay_failed'
            | 'initialization_error'
            | 'authentication_error'
            | 'account_error'
            | 'playback_error',
        callback: (payload: any) => void,
    ): boolean;
    removeListener(event: string, callback?: (payload: any) => void): boolean;
    pause(): Promise<void>;
    resume(): Promise<void>;
    seek(positionMs: number): Promise<void>;
    setVolume(volume: number): Promise<void>;
    getVolume(): Promise<number>;
    previousTrack(): Promise<void>;
    nextTrack(): Promise<void>;
    activateElement(): Promise<void>;
}

export {};
