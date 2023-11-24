<?php

namespace Laravel\Pulse\Ingests;

use Carbon\CarbonImmutable;
use Illuminate\Config\Repository;
use Illuminate\Support\Collection;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Entry;
use Laravel\Pulse\Redis as RedisAdapter;
use Laravel\Pulse\Support\RedisConnectionResolver;

class Redis implements Ingest
{
    /**
     * The redis stream.
     */
    protected string $stream = 'laravel:pulse:entries';

    /**
     * Create a new Redis Ingest instance.
     */
    public function __construct(
        protected Repository $config,
        protected RedisConnectionResolver $redis,
    ) {
        //
    }

    /**
     * Ingest the entries and updates.
     *
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entry>  $items
     */
    public function ingest(Collection $items): void
    {
        if ($items->isEmpty()) {
            return;
        }

        $this->redis->connection()->pipeline(function (RedisAdapter $pipeline) use ($items) {
            $items->each(fn (Entry $entry) => $pipeline->xadd($this->stream, [
                'data' => serialize($entry),
            ]));
        });
    }

    /**
     * Trim the ingest.
     */
    public function trim(): void
    {
        $this->redis->connection()->xtrim(
            $this->stream,
            'MINID',
            '~',
            CarbonImmutable::now()->subWeek()->getTimestampMs()
        );
    }

    /**
     * Store the ingested entries.
     */
    public function store(Storage $storage): int
    {
        $total = 0;

        while (true) {
            $entries = collect($this->redis->connection()->xrange(
                $this->stream,
                '-',
                '+',
                $chunk = $this->config->get('pulse.ingest.redis.chunk')
            ));

            if ($entries->isEmpty()) {
                return $total;
            }

            $keys = $entries->keys();

            $storage->store(
                $entries->map(fn (array $payload): Entry => unserialize($payload['data']))->values()
            );

            $this->redis->connection()->xdel($this->stream, $keys);

            if ($entries->count() < $chunk) {
                return $total + $entries->count();
            }

            $total = $total + $entries->count();
        }
    }
}
