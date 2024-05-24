<?php

namespace Laravel\Pulse\Ingests;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Collection;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Entry;
use Laravel\Pulse\Support\RedisAdapter;
use Laravel\Pulse\Value;

/**
 * @internal
 */
class RedisIngest implements Ingest
{
    /**
     * The redis stream.
     */
    protected string $stream = 'laravel:pulse:ingest';

    /**
     * Create a new Redis Ingest instance.
     */
    public function __construct(
        protected RedisManager $redis,
        protected Repository $config,
    ) {
        //
    }

    /**
     * Ingest the items.
     *
     * @param  \Illuminate\Support\Collection<int, \Laravel\Pulse\Entry|\Laravel\Pulse\Value>  $items
     */
    public function ingest(Collection $items): void
    {
        if ($items->isEmpty()) {
            return;
        }

        $this->connection()->pipeline(function (RedisAdapter $pipeline) use ($items) {
            $items->each(fn (Entry|Value $entry) => $pipeline->xadd($this->stream, [
                'data' => serialize($entry),
            ]));
        });
    }

    /**
     * Trim the ingest.
     */
    public function trim(): void
    {
        $keep = $this->config->get('pulse.ingest.trim.keep');

        $this->connection()->xtrim(
            $this->stream,
            is_int($keep) ? 'MAXLEN' : 'MINID',
            '~',
            is_int($keep)
                ? $keep
                : CarbonImmutable::now()->subMilliseconds(
                    (int) CarbonInterval::fromString($keep)->totalMilliseconds
                )->getTimestampMs(),
        );
    }

    /**
     * Digest the ingested items.
     */
    public function digest(Storage $storage): int
    {
        $total = 0;

        while (true) {
            $entries = collect($this->connection()->xrange(
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
                $entries->map(fn (array $payload): Entry|Value => unserialize($payload['data']))->values()
            );

            $this->connection()->xdel($this->stream, $keys);

            if ($entries->count() < $chunk) {
                return $total + $entries->count();
            }

            $total = $total + $entries->count();
        }
    }

    /**
     * Resolve the redis connection.
     */
    protected function connection(): RedisAdapter
    {
        return new RedisAdapter($this->redis->connection(
            $this->config->get('pulse.ingest.redis.connection')
        ), $this->config);
    }
}
