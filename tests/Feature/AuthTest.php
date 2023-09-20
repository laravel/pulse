<?php

use Illuminate\Foundation\Auth\User;
use Laravel\Pulse\Pulse;

it('authorizes dashboard access', function ($environment, $status) {
    $this->app['env'] = $environment;

    $this->get('/pulse')->assertStatus($status);
})->with([
    'local' => ['local', 200],
    'other' => ['other', 403],
]);

it('authorizes dashboard access with a callback', function ($email, $status) {
    $this->app[Pulse::class]->authorizeUsing(function ($request) {
        return $request->user()?->email === 'taylor@laravel.com';
    });

    $this
        ->actingAs(User::make(['email' => $email]))
        ->get('/pulse')
        ->assertStatus($status);
})->with([
    'allowed' => ['taylor@laravel.com', 200],
    'denied' => ['bad@example.com', 403],
]);

it('requires authentication on livewire requests', function () {
    $authCount = 0;

    $this->app[Pulse::class]->authorizeUsing(function () use (&$authCount) {
        $authCount++;

        return true;
    });

    $response = $this
        ->get('/pulse')
        ->assertOk();

    $this->assertSame(1, $authCount);

    preg_match_all('/wire:snapshot="([^"]+)"/', $response->content(), $matches);
    $component = collect($matches[1])
        ->map(fn ($match) => json_decode(html_entity_decode($match)))
        ->first(fn ($component) => $component->memo->name === 'pulse.servers');

    $this
        ->post('/livewire/update', [
            '_token' => csrf_token(),
            'components' => [
                [
                    'calls' => [],
                    'snapshot' => json_encode($component),
                    'updates' => [],
                ],
            ],
        ])
        ->assertOk();

    $this->assertSame(2, $authCount);
});

it('doesnt use pulse middleware on other livewire requests', function () {
    $this->app[Pulse::class]->authorizeUsing(fn () => false);

    $this
        ->post('/livewire/update', [
            '_token' => csrf_token(),
            'components' => [],
        ])
        ->assertOk();
});
