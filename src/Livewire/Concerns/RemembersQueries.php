<?php

namespace Laravel\Pulse\Livewire\Concerns;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Support\Benchmark;
use Illuminate\Support\Facades\App;
use Laravel\Pulse\Support\CacheStoreResolver;

trait RemembersQueries
{
    /**
     * Remember the query for the current period.
     *
     * @return array{0: mixed, 1: float, 2: string}
     */
    public function remember(callable $query, string $key = '', int $ttl = 5): array
    {
        return App::make(CacheStoreResolver::class)->store()->remember('laravel:pulse:'.static::class.':'.$key.':'.$this->period, CarbonInterval::seconds($ttl), function () use ($query) {
            $start = CarbonImmutable::now()->toDateTimeString();

            [$value, $duration] = Benchmark::value(fn () => $query($this->periodAsInterval()));

            return [$value, $duration, $start];
        });
    }
}
