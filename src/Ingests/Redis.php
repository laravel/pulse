<?php

namespace Laravel\Pulse\Ingests;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Collection;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Entries\Entry;
use Laravel\Pulse\Entries\Table;
use Laravel\Pulse\Entries\Update;
use Laravel\Pulse\Redis as RedisAdapter;

class Redis implements Ingest
{
    /**
     * The redis stream.
     */
    protected string $stream = 'illuminate:pulse:entries';

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
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entries\Entry>  $entries
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entries\Update>  $updates
     */
    public function ingest(Collection $entries, Collection $updates): void
    {
        if ($entries->isEmpty() && $updates->isEmpty()) {
            return;
        }

        $this->connection()->pipeline(function ($pipeline) use ($entries, $updates) {
            $entries->groupBy('table.value')
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

        [$inserts, $updates] = $entries
            ->values()
            ->partition(fn ($entry) => $entry['type'] !== 'pulse_update');

        $inserts = $inserts->map(fn ($data) => with(json_decode($data['data'], true, flags: JSON_THROW_ON_ERROR), function ($data) {
            return new Entry(Table::from($data['table']), $data['attributes']);
        }));

        $updates = $updates->map(fn ($data): Update => unserialize($data['data']));

        $storage->store($inserts, $updates);

        $this->connection()->xdel($this->stream, $keys);

        return $entries->count();
    }

    /**
     * The interval to trim the storage to.
     */
    protected function trimAfter(): Interval
    {
        return new Interval($this->config->get('pulse.retain' ?? 'P7D'));
    }

    /**
     * Get the redis connection.
     */
    protected function connection(): RedisAdapter
    {
        return new RedisAdapter($this->config, $this->manager->connection($this->config->get(
            "pulse.ingest.{$this->config->get('pulse.ingest.driver')}.connection"
        )));
    }
}
