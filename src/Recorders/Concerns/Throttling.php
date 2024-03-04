<?php

namespace Laravel\Pulse\Recorders\Concerns;

use Carbon\CarbonImmutable;
use DateInterval;
use Illuminate\Support\Facades\App;
use Illuminate\Support\InteractsWithTime;
use Laravel\Pulse\Events\IsolatedBeat;
use Laravel\Pulse\Events\SharedBeat;
use Laravel\Pulse\Support\CacheStoreResolver;

trait Throttling
{
    use InteractsWithTime;

    /**
     * Determine if the recorder is ready to record another snapshot.
     */
    protected function throttle(DateInterval|int $interval, SharedBeat|IsolatedBeat $event, callable $callback, ?string $key = null): void
    {
        $key ??= static::class;

        if ($event instanceof SharedBeat) {
            $key = $event->instance.":{$key}";
        }

        $cache = App::make(CacheStoreResolver::class);

        $key = 'laravel:pulse:throttle:'.$key;

        $lastRunAt = $cache->store()->get($key);

        if ($lastRunAt !== null && CarbonImmutable::createFromTimestamp($lastRunAt)->addSeconds($this->secondsUntil($interval))->isFuture()) {
            return;
        }

        $callback($event);

        $cache->store()->put($key, $event->time->getTimestamp(), $interval);
    }
}
