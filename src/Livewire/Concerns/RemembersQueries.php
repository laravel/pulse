<?php

namespace Laravel\Pulse\Livewire\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Support\Benchmark;
use Illuminate\Support\Facades\Cache;

trait RemembersQueries
{
    /**
     * Remember the query for the current period.
     *
     * @return array{0: mixed, 1: float, 2: string}
     */
    public function remember(callable $query, string $key = ''): array
    {
        return Cache::remember('laravel:pulse:'.static::class.':'.$key.':'.$this->period, $this->periodCacheDuration(), function () use ($query) {
            $now = new CarbonImmutable;

            [$value, $duration] = Benchmark::value(fn () => $query($this->periodAsInterval()));

            return [$value, $duration, $now->toDateTimeString()];
        });
    }
}
