<?php

namespace App\Services\Discovery;

use App\Ai\Agents\DiscoveryRecommendationAgent;
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

    /**
     * @param  array<string, float>  $affinityMap
     * @param  array<string, float>  $similarArtists
     * @param  string[]  $topTrackNames
     * @param  array<string, mixed>  $exclusionSet
     * @return array<string, array<string, mixed>>
     */
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

        $candidates = [];

        foreach ($tracks as $suggestion) {
            $trackName = (string) ($suggestion['track'] ?? '');
            $artistName = (string) ($suggestion['artist'] ?? '');

            if ($trackName === '' || $artistName === '') {
                continue;
            }

            $result = $searchClient->searchTrack($trackName, $artistName);

            if ($result === null || isset($exclusionSet[$result['spotify_id']])) {
                continue;
            }

            $id = $result['spotify_id'];

            if (isset($candidates[$id])) {
                continue;
            }

            $candidates[$id] = array_merge($result, [
                'artist_affinity' => $affinityMap[$artistName] ?? 0.0,
                'lastfm_match' => 0.0,
                'seed_track_match' => 0.0,
                'recent_play_days_ago' => null,
            ]);
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
