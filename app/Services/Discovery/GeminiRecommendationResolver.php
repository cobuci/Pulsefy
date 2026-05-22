<?php

namespace App\Services\Discovery;

use App\Ai\Agents\DiscoveryRecommendationAgent;
use App\Models\Track;
use App\Models\User;
use App\Services\Spotify\Client\SpotifySearchClient;
use App\Services\Spotify\SpotifyTokenService;

final class GeminiRecommendationResolver
{
    private const int RECOMMENDATION_COUNT = 25;

    public function __construct(
        private readonly SpotifyTokenService $tokenService,
        private readonly DiscoveryRecommendationAgent $agent,
    ) {}

    public function resolve(
        User $user,
        array $affinityMap,
        array $similarArtists,
        array $topTrackNames,
        array $exclusionSet,
    ): array {
        $prompt = $this->buildPrompt($affinityMap, $similarArtists, $topTrackNames);

        try {
            $response = $this->agent->prompt($prompt);
            $tracks = $response['tracks'] ?? [];
        } catch (\Throwable) {
            return [];
        }

        if (! is_array($tracks) || $tracks === []) {
            return [];
        }

        $token = $this->tokenService->ensureFreshToken($user);
        $searchClient = new SpotifySearchClient($token);

        $affinityLower = array_combine(
            array_map('mb_strtolower', array_keys($affinityMap)),
            array_values($affinityMap),
        ) ?: [];

        $similarLower = array_combine(
            array_map('mb_strtolower', array_keys($similarArtists)),
            array_values($similarArtists),
        ) ?: [];

        $candidates = [];

        foreach ($tracks as $suggestion) {
            $trackName = (string) ($suggestion['track'] ?? '');
            $artistName = (string) ($suggestion['artist'] ?? '');

            if ($trackName === '' || $artistName === '') {
                continue;
            }

            $result = $searchClient->searchTrack($trackName, $artistName);

            if ($result === null) {
                continue;
            }

            $spotifyId = $result['spotify_id'];

            if (isset($exclusionSet[$spotifyId]) || isset($candidates[$spotifyId])) {
                continue;
            }

            $attributes = ['name' => $result['name']];
            if ($result['image_url'] !== null) {
                $attributes['image_url'] = $result['image_url'];
            }

            $track = Track::query()->updateOrCreate(
                ['spotify_id' => $spotifyId],
                $attributes,
            );

            $artistKey = mb_strtolower($artistName);
            $lastfmRaw = (float) ($similarLower[$artistKey] ?? 0);
            $lastfmMatch = $lastfmRaw <= 1.0 ? $lastfmRaw * 100.0 : min(100.0, $lastfmRaw);

            $candidates[$spotifyId] = [
                'track_id' => $track->id,
                'artist_name' => $artistKey,
                'artist_affinity' => $affinityLower[$artistKey] ?? 0.0,
                'lastfm_match' => $lastfmMatch,
                'seed_track_match' => 0.0,
                'recent_play_days_ago' => null,
            ];
        }

        return $candidates;
    }

    /**
     * @param  array<string, float>  $affinityMap
     * @param  array<string, float>  $similarArtists
     * @param  string[]  $topTrackNames
     */
    private function buildPrompt(array $affinityMap, array $similarArtists, array $topTrackNames): string
    {
        $topArtistsList = implode(', ', array_slice(array_keys($affinityMap), 0, 15));
        $topTracksList = implode(', ', array_slice($topTrackNames, 0, 10));
        $similarArtistsList = implode(', ', array_slice(array_keys($similarArtists), 0, 20));

        return implode("\n", [
            "Top Artists: {$topArtistsList}",
            "Top Tracks: {$topTracksList}",
            "Similar Artists (from Last.fm): {$similarArtistsList}",
            'Requested count: '.self::RECOMMENDATION_COUNT,
        ]);
    }
}
