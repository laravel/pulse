<?php

namespace Laravel\Pulse\Queries;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Laravel\Pulse\Pulse;
use Laravel\Pulse\Support\DatabaseConnectionResolver;
use Laravel\Pulse\Support\RedisConnectionResolver;
use stdClass;

/**
 * @internal
 */
class Usage
{
    /**
     * Create a new query instance.
     */
    public function __construct(
        protected DatabaseConnectionResolver $db,
        protected RedisConnectionResolver $redis,
        protected Pulse $pulse,
    ) {
        //
    }

    /**
     * Run the query.
     *
     * @param  'requests'|'slow_requests'|'slow_jobs'  $type
     * @return \Illuminate\Support\Collection<int, array{
     *     count: int,
     *     user: array{
     *         name: string,
     *         extra: string,
     *         avatar: string|null
     *     }
     * }>
     */
    public function __invoke(Interval $interval, string $type): Collection
    {
        $now = new CarbonImmutable();

        $windowStart = (int) $now->timestamp - $interval->totalSeconds + 1;
        $currentBucket = (int) floor((int) $now->timestamp / 60) * 60; // TODO: Fix for all periods
        $oldestBucket = $currentBucket - $interval->totalSeconds + 60; // TODO: fix for all periods
        $tailStart = $windowStart;
        $tailEnd = $oldestBucket - 1;

        $type = match ($type) {
            'requests' => 'user_request',
            'slow_requests' => 'user_slow_request',
            'slow_jobs' => 'user_slow_job',
        };

        $top10 = $this->db->connection()->query()
            ->select('key', $this->db->connection()->raw('sum(`value`) as `value`'))
            ->fromSub(fn (Builder $query) => $query
                // tail
                ->select('key', $this->db->connection()->raw('count(*) as `value`'))
                ->from('pulse_entries')
                ->where('type', $type)
                ->where('timestamp', '>=', $tailStart)
                ->where('timestamp', '<=', $tailEnd)
                ->groupBy('key')
                // buckets
                ->unionAll(fn (Builder $query) => $query
                    ->select('key', $this->db->connection()->raw('sum(`value`) as `value`'))
                    ->from('pulse_aggregates')
                    ->where('period', $interval->totalSeconds / 60)
                    ->where('type', $type.':count')
                    ->where('bucket', '>=', $oldestBucket)
                    ->groupBy('key')
                ), as: 'child'
            )
            ->groupBy('key')
            ->orderByDesc('value')
            ->limit(10)
            ->get();

        $users = $this->pulse->resolveUsers($top10->pluck('key'));

        return $top10->map(function (stdClass $row) use ($users) {
            $user = $users->firstWhere('id', $row->key);

            return [
                'count' => (int) $row->value,
                'user' => [
                    'name' => $user['name'] ?? 'Unknown',
                    'extra' => $user['extra'] ?? $user['email'] ?? '',
                    'avatar' => $user['avatar'] ?? (($user['email'] ?? false) ? "https://unavatar.io/{$user['email']}?fallback=".rawurlencode("https://source.boringavatars.com/marble/120/{$user['email']}?colors=2f2bad,ad2bad,e42692,f71568,f7db15") : null),
                ],
            ];
        });
    }
}
