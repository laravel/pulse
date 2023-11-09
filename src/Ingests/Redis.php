<?php

namespace Laravel\Pulse\Ingests;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Config\Repository;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Collection;
use Laravel\Pulse\Concerns\InteractsWithRedisConnection;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Entry;
use Laravel\Pulse\Redis as RedisAdapter;
use Laravel\Pulse\Update;

class Redis implements Ingest
{
    use InteractsWithRedisConnection;

    /**
     * The redis stream.
     */
    protected string $stream = 'laravel:pulse:entries';

    /**
     * Create a new Redis Ingest instance.
     */
    public function __construct(
        protected Repository $config,
        protected RedisManager $redis,
    ) {
        //
    }

    /**
     * Ingest the entries and updates.
     *
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entry|\Laravel\Pulse\Update>  $items
     */
    public function ingest(Collection $items): void
    {
        if ($items->isEmpty()) {
            return;
        }

        $this->redis()->pipeline(function (RedisAdapter $pipeline) use ($items) {
            $items->each(fn (Entry|Update $entry) => $pipeline->xadd($this->stream, [
                'data' => serialize($entry),
            ]));
        });
    }

    /**
     * Trim the ingested entries.
     */
    public function trim(): void
    {
        $this->redis()->xtrim(
            $this->stream,
            'MINID',
            '~',
            (new CarbonImmutable)->subSeconds((int) $this->trimAfter()->totalSeconds)->getTimestampMs()
        );
    }

    /**
     * Store the ingested entries.
     */
    public function store(Storage $storage): int
    {
        $count = 0;

        while (true) {
            $entries = collect($this->redis()->xrange(
                $this->stream,
                '-',
                '+',
                $this->config->get('pulse.ingest.redis.chunk')
            ));

            if ($entries->isEmpty()) {
                return $count;
            }

            $keys = $entries->keys();

            $storage->store(
                $entries->map(fn (array $payload): Entry|Update => unserialize($payload['data']))->values()
            );

            $this->redis()->xdel($this->stream, $keys);

            $count = $count + $entries->count();
        }
    }

    /**
     * The interval to trim the storage to.
     */
    protected function trimAfter(): Interval
    {
        return new Interval($this->config->get('pulse.retain'));
    }
}
