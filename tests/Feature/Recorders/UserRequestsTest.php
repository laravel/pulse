<?php

it('ingests slow unauthenticated requests', function () {
    Config::set('pulse.recorders.'.UserRequests::class.'.threshold', 0);
    Carbon::setTestNow('2000-01-02 03:04:05');
    Route::get('users', fn () => []);

    get('users');

    expect(Pulse::queue())->toHaveCount(0);
    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toHaveProperties([
        'timestamp' => now()->timestamp,
        'type' => 'slow_request',
        'key' => 'GET /users',
        'value' => 0,
    ]);
    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->orderBy('period')->get());
    expect($aggregates)->toHaveCount(8);
    expect($aggregates[0])->toHaveProperties([
        'bucket' => (int) (floor(now()->timestamp / 60) * 60),
        'period' => 60,
        'type' => 'slow_request:count',
        'key' => 'GET /users',
        'value' => 1,
    ]);
    expect($aggregates[1])->toHaveProperties([
        'bucket' => (int) (floor(now()->timestamp / 60) * 60),
        'period' => 60,
        'type' => 'slow_request:max',
        'key' => 'GET /users',
        'value' => 0,
    ]);
});

it('ignores unauthenticated requests under the slow endpoint threshold', function () {
    Config::set('pulse.recorders.'.UserRequests::class.'.threshold', PHP_INT_MAX);
    Route::get('users', fn () => []);

    get('users');

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(0);
});

it('captures authenticated requests under the slow endpoint threshold', function () {
    Route::get('users', fn () => []);

    actingAs(User::make(['id' => '567']))->get('users');

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toHaveProperties([
        'timestamp' => now()->timestamp,
        'type' => 'user_request',
        'key' => '567',
        'value' => null,
    ]);
    $aggregates = Pulse::ignore(fn () => DB::table('pulse_aggregates')->orderBy('period')->get());
    expect($aggregates)->toHaveCount(4);
    expect($aggregates[0])->toHaveProperties([
        'bucket' => (int) (floor(now()->timestamp / 60) * 60),
        'period' => 60,
        'type' => 'user_request:count',
        'key' => '567',
        'value' => 1,
    ]);
});

it('captures the authenticated user if they login during the request', function () {
    Route::post('login', fn () => Auth::login(User::make(['id' => '567'])));

    post('login');

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0]->key)->toBe('567');
});

it('captures the authenticated user if they logout during the request', function () {
    Route::post('logout', fn () => Auth::logout());

    actingAs(User::make(['id' => '567']))->post('logout');

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0]->user_id)->toBe('567');
});

it('does not trigger an infinite loop when retrieving the authenticated user from the database', function () {
    Route::get('users', fn () => []);
    Config::set('auth.guards.db', ['driver' => 'db']);
    Auth::extend('db', fn () => new class implements Guard
    {
        use GuardHelpers;

        public function validate(array $credentials = [])
        {
            return true;
        }

        public function user()
        {
            static $count = 0;

            if (++$count > 5) {
                throw new RuntimeException('Infinite loop detected.');
            }

            return User::first();
        }
    })->shouldUse('db');

    get('users');

    $entries = Pulse::ignore(fn () => DB::table('pulse_entries')->get());
    expect($entries)->toHaveCount(1);
    expect($entries[0]->user_id)->toBe(null);
});
