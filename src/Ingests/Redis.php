<?php

namespace Laravel\Pulse\Ingests;

use Carbon\CarbonInterval as Interval;
use Illuminate\Support\Collection;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Entries\Entry;
use Laravel\Pulse\Entries\Update;
use Laravel\Pulse\Redis as RedisConnection;

class Redis implements Ingest
{
    /**
     * The redis stream name.
     */
    protected string $stream = 'illuminate:pulse:entries';

    /**
     * Create a new Redis Ingest instance.
     *
     * @param  array<string, mixed>  $config
     */
    public function __construct(protected array $config, protected RedisConnection $connection)
    {
        //
    }

    /**
     * Ingest the entries and updates.
     *
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entries\Entry>  $entries
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entries\Update>  $updates
     */
    public function ingest(Collection $entries, Collection $updates): void
    {
        if ($entries->isEmpty() && $updates->isEmpty()) {
            return;
        }

        $this->connection->pipeline(function (RedisConnection $pipeline) use ($entries, $updates) {
            $entries->groupBy('table')
                ->each(fn ($rows, $table) => $rows->each(fn ($data) => $pipeline->xadd($this->stream, [
                    'type' => $table,
                    'data' => json_encode($data, flags: JSON_THROW_ON_ERROR),
                ])));

            $updates->each(fn ($update) => $pipeline->xadd($this->stream, [
                'type' => 'pulse_update',
                'data' => serialize($update),
            ]));
        });
    }

    /**
     * Trim the ingested entries.
     */
    public function retain(Interval $interval): void
    {
        $this->connection->xtrim($this->stream, 'MINID', '~', $this->connection->streamIdAt($interval->copy()->invert()));
    }

    /**
     * Store the ingested entries.
     */
    public function store(Storage $storage, int $count): int
    {
        $entries = collect($this->connection->xrange($this->stream, '-', '+', $count));

        if ($entries->isEmpty()) {
            return 0;
        }

        $keys = $entries->keys();

        [$inserts, $updates] = $entries
            ->values()
            ->partition(fn ($entry) => $entry['type'] !== 'pulse_update');

        $inserts = $inserts->map(fn ($data) => with(json_decode($data['data'], true, flags: JSON_THROW_ON_ERROR), function ($data) {
            return new Entry($data['table'], $data['attributes']);
        }));

        $updates = $updates->map(fn ($data): Update => unserialize($data['data']));

        $storage->store($inserts, $updates);

        $this->connection->xdel($this->stream, $keys->all());

        return $entries->count();
    }
}
