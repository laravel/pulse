<?php

use Illuminate\Foundation\Auth\User;
use Laravel\Pulse\Livewire\Servers;
use Laravel\Pulse\Pulse;
use Livewire\Livewire;

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
    Livewire::test(Servers::class)->assertForbidden();
});
