<?php

namespace App\Services;

use App\Models\Lyric;
use Illuminate\Support\Facades\Http;

class LyricsService
{
    private const string LRCLIB_BASE = 'https://lrclib.net/api';

    /**
     * Get lyrics for a track, returning from cache when available.
     *
     * @return array{track_id: string, type: 'synced'|'plain'|'none', lyrics: ?string, synced: bool}
     */
    public function getLyrics(
        string $trackId,
        string $artist,
        string $trackName,
        ?string $albumName = null,
        ?float $duration = null,
        bool $forceRefresh = false,
    ): array {
        $cached = Lyric::where('track_id', $trackId)->first();

        if ($cached !== null && ! $forceRefresh) {
            if ($this->shouldRetryLegacyNegativeCache($cached)) {
                return $this->refreshLyrics($trackId, $artist, $trackName, $albumName, $duration);
            }

            return $this->formatResponse($cached);
        }

        return $this->refreshLyrics($trackId, $artist, $trackName, $albumName, $duration);
    }

    /**
     * @return array{track_id: string, type: 'synced'|'plain'|'none', lyrics: ?string, synced: bool}
     */
    private function refreshLyrics(
        string $trackId,
        string $artist,
        string $trackName,
        ?string $albumName,
        ?float $duration,
    ): array {
        $normalizedArtist = $this->normalize($artist);
        $normalizedTrack = $this->normalize($trackName);
        $normalizedAlbum = $albumName !== null ? $this->normalize($albumName) : null;

        $fetched = $this->fetchWithFallbacks(
            $artist,
            $trackName,
            $normalizedArtist,
            $normalizedTrack,
            $normalizedAlbum,
            $duration,
        );

        $lyric = Lyric::updateOrCreate(['track_id' => $trackId], [
            'artist_name' => $artist,
            'track_name' => $trackName,
            'synced_lyrics' => $fetched['syncedLyrics'] ?? null,
            'plain_lyrics' => $fetched['plainLyrics'] ?? null,
            'is_synced' => ! empty($fetched['syncedLyrics']),
            'source' => 'lrclib',
            'fetched_at' => now(),
        ]);

        return $this->formatResponse($lyric);
    }

    /**
     * @return ?array{syncedLyrics?: ?string, plainLyrics?: ?string}
     */
    private function fetchWithFallbacks(
        string $rawArtist,
        string $rawTrack,
        string $normalizedArtist,
        string $normalizedTrack,
        ?string $normalizedAlbum,
        ?float $duration,
    ): ?array {
        $artistCandidates = $this->artistCandidates($rawArtist, $normalizedArtist);
        $trackCandidates = $this->trackCandidates($rawTrack, $normalizedTrack);
        $durationSeconds = $duration !== null ? (int) round($duration / 1000) : null;

        foreach ($artistCandidates as $artist) {
            foreach ($trackCandidates as $track) {
                $result = $this->fetchFromLrclib($artist, $track, $normalizedAlbum, $durationSeconds);

                if ($this->hasAnyLyrics($result)) {
                    return $result;
                }

                $result = $this->searchFromLrclib($artist, $track);

                if ($this->hasAnyLyrics($result)) {
                    return $result;
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function artistCandidates(string $rawArtist, string $normalizedArtist): array
    {
        $candidates = [$normalizedArtist];

        $artistParts = preg_split('/\s*,\s*/', $rawArtist) ?: [];
        $firstRawArtist = trim($artistParts[0] ?? '');
        $firstNormalizedArtist = $this->normalize($firstRawArtist);

        if ($firstNormalizedArtist !== '') {
            $candidates[] = $firstNormalizedArtist;
        }

        return $this->uniqueNonEmpty($candidates);
    }

    /**
     * @return list<string>
     */
    private function trackCandidates(string $rawTrack, string $normalizedTrack): array
    {
        $candidates = [$normalizedTrack];

        $trackParts = preg_split('/\s*[-–—]\s*/u', $rawTrack) ?: [];
        $baseRawTrack = trim($trackParts[0] ?? '');
        $baseNormalizedTrack = $this->normalize($baseRawTrack);

        if ($baseNormalizedTrack !== '') {
            $candidates[] = $baseNormalizedTrack;
        }

        return $this->uniqueNonEmpty($candidates);
    }

    /**
     * @param  ?array{syncedLyrics?: ?string, plainLyrics?: ?string}  $payload
     */
    private function hasAnyLyrics(?array $payload): bool
    {
        return ! empty($payload['syncedLyrics']) || ! empty($payload['plainLyrics']);
    }

    private function shouldRetryLegacyNegativeCache(Lyric $cached): bool
    {
        return $cached->source === 'lrclib'
            && $cached->synced_lyrics === null
            && $cached->plain_lyrics === null
            && $cached->fetched_at !== null
            && $cached->fetched_at->lt(now()->subDays(7));
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function uniqueNonEmpty(array $values): array
    {
        return array_values(array_unique(array_filter($values, fn (string $value) => $value !== '')));
    }

    /**
     * Strip featured artist annotations and common suffixes before querying LRCLIB.
     */
    public function normalize(string $value): string
    {
        $value = preg_replace('/\s*\(feat\.?[^)]*\)/i', '', $value) ?? $value;
        $value = preg_replace('/\s*\(ft\.?[^)]*\)/i', '', $value) ?? $value;
        $value = preg_replace('/\s*\(with[^)]*\)/i', '', $value) ?? $value;

        $suffixes = ['Remastered', 'Live', 'Remix', 'Edit', 'Version', 'Demo', 'Acoustic', 'Radio Edit'];
        $pattern = '/\s*[\(\[]\s*(?:'.implode('|', array_map('preg_quote', $suffixes)).')[^\)\]]*[\)\]]/i';
        $value = preg_replace($pattern, '', $value) ?? $value;

        $dashPattern = '/\s+-\s+(?:'.implode('|', array_map('preg_quote', $suffixes)).').*/i';
        $value = preg_replace($dashPattern, '', $value) ?? $value;

        $value = preg_replace('/[\s\-–—_:;,.|]+$/u', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * Fetch lyrics from the LRCLIB API.
     *
     * @return ?array{syncedLyrics?: ?string, plainLyrics?: ?string}
     */
    private function fetchFromLrclib(
        string $artist,
        string $track,
        ?string $albumName,
        ?int $durationSeconds,
    ): ?array {
        try {
            if ($albumName === null || $durationSeconds === null) {
                return null;
            }

            $response = Http::timeout(8)
                ->connectTimeout(5)
                ->retry(2, 200)
                ->get(self::LRCLIB_BASE.'/get', [
                    'artist_name' => $artist,
                    'track_name' => $track,
                    'album_name' => $albumName,
                    'duration' => $durationSeconds,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Fetch best-match lyrics from LRCLIB search endpoint.
     *
     * @return ?array{syncedLyrics?: ?string, plainLyrics?: ?string}
     */
    private function searchFromLrclib(string $artist, string $track): ?array
    {
        try {
            $response = Http::timeout(8)
                ->connectTimeout(5)
                ->retry(2, 200)
                ->get(self::LRCLIB_BASE.'/search', [
                    'track_name' => $track,
                    'artist_name' => $artist,
                ]);

            if (! $response->successful()) {
                return null;
            }

            /** @var array<int, array<string, mixed>> $results */
            $results = $response->json() ?? [];
            $first = $results[0] ?? null;

            if (! is_array($first)) {
                return null;
            }

            return [
                'syncedLyrics' => $first['syncedLyrics'] ?? null,
                'plainLyrics' => $first['plainLyrics'] ?? null,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{track_id: string, type: 'synced'|'plain'|'none', lyrics: ?string, synced: bool}
     */
    private function formatResponse(Lyric $lyric): array
    {
        if ($lyric->is_synced && ! empty($lyric->synced_lyrics)) {
            return [
                'track_id' => $lyric->track_id,
                'type' => 'synced',
                'lyrics' => $lyric->synced_lyrics,
                'synced' => true,
            ];
        }

        if (! empty($lyric->plain_lyrics)) {
            return [
                'track_id' => $lyric->track_id,
                'type' => 'plain',
                'lyrics' => $lyric->plain_lyrics,
                'synced' => false,
            ];
        }

        return [
            'track_id' => $lyric->track_id,
            'type' => 'none',
            'lyrics' => null,
            'synced' => false,
        ];
    }
}
