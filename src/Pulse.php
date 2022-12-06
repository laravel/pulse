<?php

namespace Laravel\Pulse;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Redis;

class Pulse
{
    public function data()
    {
        return [
            'stats' => collect(Redis::xRange('stats:server-1', '-', '+'))->values(),
            'hits' => $this->hits(),
        ];
    }

    protected function hits()
    {
        // TODO: We don't need to rebuild this on every request - maybe once per hour?
        Redis::zUnionStore(
            'hits:7day',
            collect(range(0, 6))
                ->map(fn ($days) => 'hits:' . now()->subDays($days)->format('Ymd'))
                ->toArray()
        );

        $scores = collect(Redis::zRevRange('hits:7day', 0, 9, ['WITHSCORES' => true]));

        $minutesElapsed = now()->diffInMinutes(now()->startOfDay());
        $days = 6 + ($minutesElapsed / (24 * 60));

        $users = User::findMany($scores->keys());

        return collect($scores)
            ->map(function ($score, $userId) use ($users, $days) {
                $user = $users->firstWhere('id', $userId);

                return $user ? [
                    'avg' => floor($score / $days),
                    'user' => $user->setVisible(['name', 'email']),
                ] : null;
            })
            ->filter();
    }
}
