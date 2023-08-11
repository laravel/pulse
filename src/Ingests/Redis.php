<?php

namespace Laravel\Pulse\Ingests;

use Carbon\CarbonImmutable;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Entries\Update;
use Laravel\Pulse\Redis as RedisConnection;
use Laravel\Pulse\Storage\Database;

class Redis implements Ingest
{
    /**
     * The redis stream name.
     */
    protected string $stream = 'illuminate:pulse:entries';

    /**
     * Create a new Redis ingest instance.
     */
    public function __construct(protected RedisConnection $connection)
    {
        //
    }

    /**
     * Ingest the entries and updates.
     */
    public function ingest(array $entries, array $updates): void
    {
        if ($entries === [] && $updates === []) {
            return;
        }

        $this->connection->pipeline(function (RedisConnection $pipeline) use ($entries, $updates) {
            collect($entries)->each(fn ($rows, $table) => collect($rows)
                ->each(fn ($data) => $pipeline->xadd($this->stream, [
                    'type' => $table,
                    'data' => json_encode($data),
                ])));

            collect($updates)->each(fn ($update) => $pipeline->xadd($this->stream, [
                'type' => 'pulse_update',
                'data' => serialize($update),
            ]));
        });
    }

    /**
     * Trim the ingested entries.
     */
    public function trim(CarbonImmutable $oldest): void
    {
        $this->connection->xtrim($this->stream, 'MINID', '~', $this->connection->streamIdAt($oldest));
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

        $inserts = $inserts
            ->groupBy('type')
            ->map->map(fn ($data): array => json_decode($data['data'], true));

        $updates = $updates
            ->map(fn ($data): Update => unserialize($data['data']));

        $storage->store($inserts->all(), $updates->all());

        $this->connection->xdel($this->stream, $keys->all());

        return $entries->count();
    }
}
