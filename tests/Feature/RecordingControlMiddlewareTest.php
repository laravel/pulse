<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Laravel\Pulse\Http\Middleware\PulseRecording;

use function Pest\Laravel\get;

it('can stop control recording via middleware', function () {
    Route::get('test-route', fn () => 'ok')->middleware([
        MyMiddleware::class.':first',
        PulseRecording::stop(),
        MyMiddleware::class.':second',
        PulseRecording::start(),
        MyMiddleware::class.':third',
        PulseRecording::stop(),
        MyMiddleware::class.':fourth',
        PulseRecording::start(),
        MyMiddleware::class.':fifth',
        PulseRecording::stop(),
        MyMiddleware::class.':sixth',
    ]);

    $response = get('test-route');

    $response->assertOk();
    $interactions = Pulse::ignore(fn () => DB::table('pulse_cache_interactions')->get());
    expect($interactions->pluck('key')->all())->toBe([
        'first',
        'third',
        'fifth',
    ]);
});

class MyMiddleware
{
    public function handle($request, $next, $key)
    {
        Cache::get($key);

        return $next($request);
    }
}
