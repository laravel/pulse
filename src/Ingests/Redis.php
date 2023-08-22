<?php

namespace Laravel\Pulse\Ingests;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Config\Repository;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Collection;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Entries\Entry;
use Laravel\Pulse\Entries\Update;
use Laravel\Pulse\Redis as RedisAdapter;

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
        protected RedisManager $manager,
    ) {
        //
    }

    /**
     * Ingest the entries and updates.
     *
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entries\Entry|\Laravel\Pulse\Entries\Update>  $items
     */
    public function ingest(Collection $items): void
    {
        if ($items->isEmpty()) {
            return;
        }

        $this->connection()->pipeline(function (RedisAdapter $pipeline) use ($items) {
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
        $this->connection()->xtrim($this->stream, 'MINID', '~', (new CarbonImmutable)->subSeconds((int) $this->trimAfter()->totalSeconds)->getTimestampMs());
    }

    /**
     * Store the ingested entries.
     */
    public function store(Storage $storage, int $count): int
    {
        $entries = collect($this->connection()->xrange($this->stream, '-', '+', $count));

        if ($entries->isEmpty()) {
            return 0;
        }

        $keys = $entries->keys();

        $storage->store(
            $entries->map(fn (array $payload): Entry|Update => unserialize($payload['data']))->values()
        );

        $this->connection()->xdel($this->stream, $keys);

        return $entries->count();
    }

    /**
     * The interval to trim the storage to.
     */
    protected function trimAfter(): Interval
    {
        return new Interval($this->config->get('pulse.retain') ?? 'P7D');
    }

    /**
     * Get the redis connection.
     */
    protected function connection(): RedisAdapter
    {
        return new RedisAdapter($this->config, $this->manager->connection($this->config->get('pulse.ingest.redis.connection')));
    }
}
