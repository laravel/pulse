<?php

namespace Laravel\Pulse\Recorders;

use Carbon\CarbonImmutable;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Config\Repository;
use Laravel\Pulse\Pulse;

/**
 * @internal
 */
class CacheInteractions
{
    use Concerns\Ignores, Concerns\Sampling;

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
    public function record(CacheHit|CacheMissed $event): void
    {
        [$timestamp, $class, $key] = [
            CarbonImmutable::now()->getTimestamp(),
            $event::class,
            $event->key,
        ];

        $this->pulse->lazy(function () use ($timestamp, $class, $key) {
            if (! $this->shouldSample() || $this->shouldIgnore($key)) {
                return;
            }

            return $this->pulse->record(
                type: match ($class) { // @phpstan-ignore match.unhandled
                    CacheHit::class => 'cache_hit',
                    CacheMissed::class => 'cache_miss',
                },
                key: $this->group($key),
                timestamp: $timestamp,
            )->count();
        });
    }

    /**
     * The grouped value.
     */
    public function group(string $value): string
    {
        foreach ($this->config->get('pulse.recorders.'.self::class.'.groups') as $pattern => $replacement) {
            $group = preg_replace($pattern, $replacement, $value, count: $count);

            if ($count > 0 && $group !== null) {
                return $group;
            }
        }

        return $value;
    }
}
