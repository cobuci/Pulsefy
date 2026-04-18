<?php

test('lastfm callback returns ok payload', function () {
    $this->getJson(route('lastfm.callback'))
        ->assertOk()
        ->assertJson([
            'ok' => true,
        ]);
});
