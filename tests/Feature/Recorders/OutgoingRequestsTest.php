<?php

use Illuminate\Auth\AuthManager;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Pulse as PulseInstance;
use Laravel\Pulse\Recorders\OutgoingRequests;

it('ingests outgoing http requests', function () {
    Carbon::setTestNow('2000-01-02 03:04:05');
    Http::fake(['https://laravel.com' => Http::response('ok')]);

    Http::get('https://laravel.com');
    Pulse::store(app(Ingest::class));

    $requests = Pulse::ignore(fn () => DB::table('pulse_outgoing_requests')->get());
    expect($requests)->toHaveCount(1);
    expect($requests[0])->toHaveProperties([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'uri' => 'GET https://laravel.com',
        'duration' => 0,
    ]);
});

it('captures failed requests', function () {
    Carbon::setTestNow('2000-01-02 03:04:05');
    Http::fake(['https://laravel.com' => Http::response('error', status: 500)]);

    Http::get('https://laravel.com');
    Pulse::store(app(Ingest::class));

    $requests = Pulse::ignore(fn () => DB::table('pulse_outgoing_requests')->get());
    expect($requests)->toHaveCount(1);
    expect($requests[0])->toHaveProperties([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'uri' => 'GET https://laravel.com',
        'duration' => 0,
    ]);
});

it('captures the authenticated user', function () {
    Auth::login(User::make(['id' => '567']));
    Http::fake(['https://laravel.com' => Http::response('ok')]);

    Http::get('https://laravel.com');
    Pulse::store(app(Ingest::class));

    $requests = Pulse::ignore(fn () => DB::table('pulse_outgoing_requests')->get());
    expect($requests)->toHaveCount(1);
    expect($requests[0]->user_id)->toBe('567');
});

it('captures the authenticated user if they login after the request', function () {
    Http::fake(['https://laravel.com' => Http::response('ok')]);

    Http::get('https://laravel.com');
    Auth::login(User::make(['id' => '567']));
    Pulse::store(app(Ingest::class));

    $requests = Pulse::ignore(fn () => DB::table('pulse_outgoing_requests')->get());
    expect($requests)->toHaveCount(1);
    expect($requests[0]->user_id)->toBe('567');
});

it('captures the authenticated user if they logout after the request', function () {
    Http::fake(['https://laravel.com' => Http::response('ok')]);
    Auth::login(User::make(['id' => '567']));

    Http::get('https://laravel.com');
    Auth::logout();
    Pulse::store(app(Ingest::class));

    $requests = Pulse::ignore(fn () => DB::table('pulse_outgoing_requests')->get());
    expect($requests)->toHaveCount(1);
    expect($requests[0]->user_id)->toBe('567');
});

it('does not trigger an inifite loop when retriving the authenticated user from the database', function () {
    Http::fake(['https://laravel.com' => Http::response('ok')]);
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

    Http::get('https://laravel.com');
    Pulse::store(app(Ingest::class));

    $requests = Pulse::ignore(fn () => DB::table('pulse_outgoing_requests')->get());
    expect($requests)->toHaveCount(1);
    expect($requests[0]->user_id)->toBe(null);
});

it('quietly fails if an exception is thrown while preparing the entry payload', function () {
    Http::fake(['https://laravel.com' => Http::response('ok')]);
    App::forgetInstance(PulseInstance::class);
    Facade::clearResolvedInstance(PulseInstance::class);
    App::when(PulseInstance::class)
        ->needs(AuthManager::class)
        ->give(fn (Application $app) => new class($app) extends AuthManager
        {
            public function hasUser()
            {
                throw new RuntimeException('Error checking for user.');
            }
        });

    Http::get('https://laravel.com');
    Pulse::store(app(Ingest::class));

    Pulse::ignore(fn () => expect(DB::table('pulse_outgoing_requests')->count())->toBe(0));
});

it('handles multiple users being logged in', function () {
    Http::fake(['https://laravel.com' => Http::response('ok')]);

    Pulse::withUser(null, fn () => Http::get('https://laravel.com'));
    Auth::login(User::make(['id' => '567']));
    Http::get('https://laravel.com');
    Auth::login(User::make(['id' => '789']));
    Http::get('https://laravel.com');
    Pulse::store(app(Ingest::class));

    $requests = Pulse::ignore(fn () => DB::table('pulse_outgoing_requests')->get());
    expect($requests)->toHaveCount(3);
    expect($requests[0]->user_id)->toBe(null);
    expect($requests[1]->user_id)->toBe('567');
    expect($requests[2]->user_id)->toBe('789');
});

it('stores the original URI by default', function () {
    Carbon::setTestNow('2000-01-02 03:04:05');
    Http::fake(['https://laravel.com*' => Http::response('ok')]);

    Http::get('https://laravel.com?foo=123');
    Pulse::store(app(Ingest::class));

    $requests = Pulse::ignore(fn () => DB::table('pulse_outgoing_requests')->get());
    expect($requests)->toHaveCount(1);
    expect($requests[0])->toHaveProperties([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'uri' => 'GET https://laravel.com?foo=123',
        'duration' => 0,
    ]);
});

it('can normalize URI', function () {
    Carbon::setTestNow('2000-01-02 03:04:05');
    Http::fake(fn () => Http::response('ok'));

    Config::set('pulse.recorders.'.OutgoingRequests::class.'.groups', [
        '#^https://github\.com/([^/]+)/([^/]+)/commits/([^/]+)$#' => 'github.com/{user}/{repo}/commits/{branch}',
    ]);
    Http::get('https://github.com/laravel/pulse/commits/1.x');
    Pulse::store(app(Ingest::class));

    $requests = Pulse::ignore(fn () => DB::table('pulse_outgoing_requests')->get());
    expect($requests)->toHaveCount(1);
    expect($requests[0])->toHaveProperties([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'uri' => 'GET github.com/{user}/{repo}/commits/{branch}',
        'duration' => 0,
    ]);
});

it('can use back references in normalized URI', function () {
    Carbon::setTestNow('2000-01-02 03:04:05');
    Http::fake(fn () => Http::response('ok'));

    Config::set('pulse.recorders.'.OutgoingRequests::class.'.groups', [
        '#^https?://([^/]+).*$#' => '\1/*',
    ]);
    Http::get('https://github.com/laravel/pulse/commits/1.x');
    Pulse::store(app(Ingest::class));

    $requests = Pulse::ignore(fn () => DB::table('pulse_outgoing_requests')->get());
    expect($requests)->toHaveCount(1);
    expect($requests[0])->toHaveProperties([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'uri' => 'GET github.com/*',
        'duration' => 0,
    ]);
});

it('can provide regex flags in normalization key', function () {
    Carbon::setTestNow('2000-01-02 03:04:05');
    Http::fake(fn () => Http::response('ok'));

    Config::set('pulse.recorders.'.OutgoingRequests::class.'.groups', [
        '/parameter/i' => 'lowercase-parameter',
        '/PARAMETER/i' => 'uppercase-parameter',
    ]);
    Http::get('https://github.com?PARAMETER=123');
    Pulse::store(app(Ingest::class));

    $requests = Pulse::ignore(fn () => DB::table('pulse_outgoing_requests')->get());
    expect($requests)->toHaveCount(1);
    expect($requests[0])->toHaveProperties([
        'date' => '2000-01-02 03:04:05',
        'user_id' => null,
        'uri' => 'GET https://github.com?lowercase-parameter=123',
        'duration' => 0,
    ]);
});

it('can ignore outgoing requests', function () {
    Http::fake(fn () => Http::response('ok'));
    Config::set('pulse.recorders.'.OutgoingRequests::class.'.ignore', [
        '#^http://127\.0\.0\.1:13714#', // Inertia SSR
    ]);

    Http::get('http://127.0.0.1:13714/render');

    expect(Pulse::entries())->toHaveCount(0);
});

it('can sample', function () {
    Http::fake(fn () => Http::response('ok'));
    Config::set('pulse.recorders.'.OutgoingRequests::class.'.sample_rate', 0.1);

    Http::get('http://example.com');
    Http::get('http://example.com');
    Http::get('http://example.com');
    Http::get('http://example.com');
    Http::get('http://example.com');
    Http::get('http://example.com');
    Http::get('http://example.com');
    Http::get('http://example.com');
    Http::get('http://example.com');
    Http::get('http://example.com');

    expect(count(Pulse::entries()))->toEqualWithDelta(1, 4);

    Pulse::flushEntries();
});
