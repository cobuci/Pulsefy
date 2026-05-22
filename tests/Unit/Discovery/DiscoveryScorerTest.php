<?php

use App\Services\Discovery\DiscoveryScorer;

beforeEach(function (): void {
    $this->scorer = new DiscoveryScorer;
});

test('score without penalization uses affinity and recency', function (): void {
    $candidate = [
        'artist_name' => 'radiohead',
        'artist_affinity' => 100.0,
        'recent_play_days_ago' => null,
    ];

    expect($this->scorer->score($candidate))->toBe(70);
});

test('score penalizes artist with active skip by 80 percent', function (): void {
    $candidate = [
        'artist_name' => 'radiohead',
        'artist_affinity' => 100.0,
        'recent_play_days_ago' => null,
    ];

    $penalized = ['radiohead' => true];

    $normal = $this->scorer->score($candidate);
    $penalizedScore = $this->scorer->score($candidate, $penalized);

    expect($penalizedScore)->toBeLessThan($normal)
        ->and($penalizedScore)->toBe(14); // 70 * 0.2
});

test('score is case insensitive for artist name', function (): void {
    $candidate = [
        'artist_name' => 'Radiohead',
        'artist_affinity' => 100.0,
        'recent_play_days_ago' => null,
    ];

    $penalized = ['radiohead' => true];

    expect($this->scorer->score($candidate, $penalized))->toBe(14);
});

test('score without artist name is not penalized', function (): void {
    $candidate = [
        'artist_name' => '',
        'artist_affinity' => 100.0,
        'recent_play_days_ago' => null,
    ];

    $penalized = ['radiohead' => true];

    expect($this->scorer->score($candidate, $penalized))->toBe(70);
});

test('score with no penalized artists returns normal score', function (): void {
    $candidate = [
        'artist_name' => 'radiohead',
        'artist_affinity' => 80.0,
        'recent_play_days_ago' => null,
    ];

    expect($this->scorer->score($candidate, []))->toBe(56);
});

test('recency contributes to score when track was recently played', function (): void {
    $candidate = [
        'artist_name' => 'radiohead',
        'artist_affinity' => 0.0,
        'recent_play_days_ago' => 0.0,
    ];

    expect($this->scorer->score($candidate))->toBe(30);
});

test('lastfm match contributes when artist affinity is zero', function (): void {
    $candidate = [
        'artist_name' => 'iron maiden',
        'artist_affinity' => 0.0,
        'lastfm_match' => 90.0,
        'recent_play_days_ago' => null,
    ];

    expect($this->scorer->score($candidate))->toBe(63);
});

test('uses the higher of artist affinity and lastfm match', function (): void {
    $candidate = [
        'artist_name' => 'metallica',
        'artist_affinity' => 40.0,
        'lastfm_match' => 85.0,
        'recent_play_days_ago' => null,
    ];

    expect($this->scorer->score($candidate))->toBe(60);
});
