<?php

namespace Laravel\Pulse\Queries;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Carbon\CarbonInterval;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Collection;
use Laravel\Pulse\Concerns\InteractsWithDatabaseConnection;
use Laravel\Pulse\Concerns\InteractsWithRedisConnection;
use Laravel\Pulse\Pulse;
use Laravel\Pulse\Redis;
use Predis\Connection\ConnectionException;
use RedisException;
use stdClass;

/**
 * @internal
 */
class Usage
{
    use InteractsWithDatabaseConnection, InteractsWithRedisConnection;

    /**
     * Create a new query instance.
     */
    public function __construct(
        protected Repository $config,
        protected DatabaseManager $db,
        protected RedisManager $redis,
        protected Pulse $pulse,
    ) {
        //
    }

    /**
     * Run the query.
     *
     * @param  'request_counts'|'slow_endpoint_counts'|'dispatched_job_counts'  $type
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
        $top10 = $this->top10($interval, $type);

        $users = $this->pulse->resolveUsers($top10->pluck('user_id'));

        return $top10->map(function (stdClass $row) use ($users) {
            $user = $users->firstWhere('id', $row->user_id);

            return [
                'count' => (int) $row->count,
                'user' => [
                    'name' => $user['name'] ?? 'Unknown',
                    'extra' => $user['extra'] ?? $user['email'] ?? '',
                    'avatar' => $user['avatar'] ?? (($user['email'] ?? false) ? "https://unavatar.io/{$user['email']}?fallback=".rawurlencode("https://source.boringavatars.com/marble/120/{$user['email']}?colors=2f2bad,ad2bad,e42692,f71568,f7db15") : null),
                ],
            ];
        });
    }

    /**
     * Retrieve the top 10 users and their counts.
     *
     * @param  'request_counts'|'slow_endpoint_counts'|'dispatched_job_counts'  $type
     * @return \Illuminate\Support\Collection<int, object{user_id: mixed, count: int}&stdClass>
     */
    protected function top10(Interval $interval, string $type): Collection
    {
        $now = new CarbonImmutable;

        try {
            if ($this->redis()->get('laravel:pulse:usage:warm') === '1') {
                $results = $this->redis()->zrange(match ((int) $interval->totalHours) { // @phpstan-ignore match.unhandled
                    1 => "laravel:pulse:usage:{$type}:1_hour",
                    6 => "laravel:pulse:usage:{$type}:6_hours",
                    24 => "laravel:pulse:usage:{$type}:24_hours",
                    168 => "laravel:pulse:usage:{$type}:7_days",
                }, start: 0, stop: 9, reversed: true, withScores: true);

                return collect($results)->chunk(2)->mapSpread(fn ($member, $score) => (object) [ // @phpstan-ignore argument.type
                    'user_id' => $member,
                    'count' => (int) $score,
                ]);
            }
        } catch (RedisException|ConnectionException) {
            //
        }

        return $this->query($type, $now->subSeconds((int) $interval->totalSeconds), $now)
            ->orderByDesc('count')
            ->limit(10)
            ->get();
    }

    /**
     * Warm the usage stats.
     */
    public function warm(CarbonImmutable $now, ?CarbonImmutable $lastWarmedAt): void
    {
        if ($lastWarmedAt !== null && $now->diffInDays($lastWarmedAt) >= 6) {
            $lastWarmedAt = null;
        }

        try {
            $this->redis()->pipeline(function (Redis $redis) use ($now, $lastWarmedAt) {
                foreach (['request_counts', 'slow_endpoint_counts', 'dispatched_job_counts'] as $type) {
                    if ($lastWarmedAt === null) {
                        $this->clearUsage($redis, $type);
                    }

                    foreach ($lastWarmedAt === null ? [
                        [$now->subHour(), ['1_hour']],
                        [$now->subHours(6), ['6_hours']],
                        [$now->subHours(24), ['24_hours']],
                        [$now->subDays(7), ['7_days']],
                    ] : [
                        [$lastWarmedAt, ['1_hour', '6_hours', '24_hours', '7_days']],
                    ] as [$from, $periods]) {
                        $this->incrementUsage($redis, $type, $from, $now, $periods);
                    }

                    if ($lastWarmedAt !== null) {
                        foreach ([
                            [$lastWarmedAt->subHours(1), $now->subHours(1), '1_hour'],
                            [$lastWarmedAt->subHours(6), $now->subHours(6), '6_hours'],
                            [$lastWarmedAt->subHours(24), $now->subHours(24), '24_hours'],
                            [$lastWarmedAt->subDays(7), $now->subDays(7), '7_days'],
                        ] as [$from, $till, $period]) {
                            $this->decrementUsage($redis, $type, $from, $till, $period);
                        }
                    }

                    foreach (['1_hour', '6_hours', '24_hours', '7_days'] as $period) {
                        $redis->expire("laravel:pulse:usage:{$type}:{$period}", CarbonInterval::days(7));
                    }
                }

                $redis->set('laravel:pulse:usage:warm', '1', Interval::seconds(30));
            });

        } catch (RedisException|ConnectionException) {
            //
        }
    }

    /**
     * The base query.
     *
     * @param  'request_counts'|'slow_endpoint_counts'|'dispatched_job_counts'  $type
     */
    protected function query(string $type, CarbonImmutable $from, CarbonImmutable $till): Builder
    {
        return $this->db()->query()
            ->when($type === 'dispatched_job_counts',
                fn (Builder $query) => $query->from('pulse_jobs')
                    ->where('queued_at', '>=', $from->toDateTimeString())
                    ->where('queued_at', '<', $till->toDateTimeString()),
                fn (Builder $query) => $query->from('pulse_requests')
                    ->where('date', '>=', $from->toDateTimeString())
                    ->where('date', '<', $till->toDateTimeString()))
            ->selectRaw('`user_id`, COUNT(*) AS `count`')
            ->whereNotNull('user_id')
            ->when($type === 'slow_endpoint_counts', fn (Builder $query) => $query->where('slow', true))
            ->groupBy('user_id');
    }

    /**
     * Clear any usage records in Redis.
     *
     * @param  'request_counts'|'slow_endpoint_counts'|'dispatched_job_counts'  $type
     */
    protected function clearUsage(Redis $redis, string $type): void
    {
        $redis->del([
            "laravel:pulse:usage:{$type}:1_hour",
            "laravel:pulse:usage:{$type}:6_hours",
            "laravel:pulse:usage:{$type}:24_hours",
            "laravel:pulse:usage:{$type}:7_days",
        ]);
    }

    /**
     * Increment the usage.
     *
     * @param  'request_counts'|'slow_endpoint_counts'|'dispatched_job_counts'  $type
     * @param  list<string>  $periods
     */
    protected function incrementUsage(Redis $redis, string $type, CarbonImmutable $from, CarbonImmutable $till, array $periods): void
    {
        $this->query($type, $from, $till)
            ->orderBy('user_id')
            ->each(function ($record) use ($redis, $type, $periods) {
                foreach ($periods as $period) {
                    $redis->zincrby("laravel:pulse:usage:{$type}:{$period}", $record->count, $record->user_id);
                }
            });
    }

    /**
     * Decrement the usage.
     *
     * @param  'request_counts'|'slow_endpoint_counts'|'dispatched_job_counts'  $type
     */
    protected function decrementUsage(Redis $redis, string $type, CarbonImmutable $from, CarbonImmutable $till, string $period): void
    {
        $this->query($type, $from, $till)
            ->orderBy('user_id')
            ->each(function ($record) use ($redis, $type, $period) {
                $redis->zincrby("laravel:pulse:usage:{$type}:{$period}", $record->count * -1, $record->user_id);
            });
    }
}
