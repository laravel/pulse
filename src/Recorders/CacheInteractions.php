<?php

namespace Laravel\Pulse\Recorders;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Config\Repository;
use Laravel\Pulse\Contracts\Groupable;
use Laravel\Pulse\Entry;
use Laravel\Pulse\Pulse;

/**
 * @internal
 */
class CacheInteractions implements Groupable
{
    use Concerns\Ignores;
    use Concerns\Sampling;

    /**
     * The table to record to.
     */
    public string $table = 'pulse_cache_interactions';

    /**
     * The events to listen for.
     *
     * @var list<class-string>
     */
    public array $listen = [
        CacheHit::class,
        CacheMissed::class,
    ];

    /**
     * Create a new recorder instance.
     */
    public function __construct(
        protected Pulse $pulse,
        protected Repository $config,
    ) {
        //
    }

    /**
     * Record the cache interaction.
     */
    public function record(CacheHit|CacheMissed $event): ?Entry
    {
        $now = new CarbonImmutable();

        if (! $this->shouldSample() || $this->shouldIgnore($event->key)) {
            return null;
        }

        return new Entry($this->table, [
            'date' => $now->toDateTimeString(),
            'hit' => $event instanceof CacheHit,
            'key' => $this->group($event->key),
            'user_id' => $this->pulse->authenticatedUserIdResolver(),
        ]);
    }

    /**
     * Return a closure that groups the given value.
     *
     * @return Closure(): string
     */
    public function group(string $value): Closure
    {
        return function () use ($value) {
            foreach ($this->config->get('pulse.recorders.'.self::class.'.groups') as $pattern => $replacement) {
                $group = preg_replace($pattern, $replacement, $value, count: $count);

                if ($count > 0 && $group !== null) {
                    return $group;
                }
            }

            return $value;
        };
    }

    /**
     * Return the column that grouping should be applied to.
     */
    public function groupColumn(): string
    {
        return 'key';
    }
}
