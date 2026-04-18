<?php

test('spotify redirect requests full required scope set', function () {
    $response = $this->get('/api/spotify/redirect');

    $response->assertRedirect();

    $location = (string) $response->headers->get('Location');

    $scopeParam = collect(explode('&', parse_url($location, PHP_URL_QUERY) ?: ''))
        ->first(fn (string $pair): bool => str_starts_with($pair, 'scope='));

    expect($scopeParam)->not->toBeNull();

    $decodedScope = urldecode((string) str($scopeParam)->after('scope='));
    $requestedScopes = collect(explode(' ', $decodedScope))
        ->filter(fn (string $scope): bool => $scope !== '')
        ->values()
        ->all();

    $expected = [
        'app-remote-control',
        'playlist-modify-private',
        'playlist-modify-public',
        'playlist-read-collaborative',
        'playlist-read-private',
        'streaming',
        'ugc-image-upload',
        'user-follow-modify',
        'user-follow-read',
        'user-library-modify',
        'user-library-read',
        'user-modify-playback-state',
        'user-read-currently-playing',
        'user-read-email',
        'user-read-playback-position',
        'user-read-playback-state',
        'user-read-private',
        'user-read-recently-played',
        'user-top-read',
    ];

    expect($requestedScopes)->toEqualCanonicalizing($expected)
        ->and(count($requestedScopes))->toBe(count($expected));
});
