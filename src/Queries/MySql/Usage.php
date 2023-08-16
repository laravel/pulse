<?php

namespace Laravel\Pulse\Queries\MySql;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval as Interval;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Laravel\Pulse\Entries\Table;

/**
 * @interval
 */
class Usage
{
    public function __invoke(Connection $connection, Interval $interval, string $type, callable $userResolver, int $slowEndpointsThreshold): Collection
    {
        $top10 = $connection->query()
            ->when($type === 'dispatched_job_counts', function ($query) {
                $query->from(Table::Job->value);
            }, function ($query) {
                $query->from(Table::Request->value);
            })
            ->selectRaw('user_id, COUNT(*) as count')
            ->whereNotNull('user_id')
            ->where('date', '>=', (new CarbonImmutable)->subSeconds((int) $interval->totalSeconds)->toDateTimeString())
            ->when($type === 'slow_endpoint_counts', fn ($query) => $query->where('duration', '>=', $slowEndpointsThreshold))
            ->groupBy('user_id')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $users = $userResolver($top10->pluck('user_id'));

        return $top10->map(function ($row) use ($users) {
            $user = $users->firstWhere('id', $row->user_id);

            return $user ? [
                'count' => $row->count,
                'user' => [
                    'name' => $user['name'],
                    // "extra" rather than 'email'
                    // avatar for pretty-ness?
                    'email' => $user['email'] ?? null,
                ],
            ] : null;
        })
        ->filter()
        ->values();
    }
}
