<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('guests are redirected to login', function () {
    $this->get(route('discovery.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users see the discovery page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('discovery.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Discovery/Index')
        );
});

test('recommendations prop is deferred on initial load', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('discovery.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Discovery/Index')
            ->missing('recommendations')
        );
});

test('deferred recommendations prop resolves after load', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('discovery.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Discovery/Index')
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->has('recommendations')
            )
        );
});
