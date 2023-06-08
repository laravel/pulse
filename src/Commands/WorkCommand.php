<?php

namespace Laravel\Pulse\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Pulse\Redis;

class WorkCommand extends Command
{
    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'pulse:work';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Process the data from the stream.';

    /**
     * Handle the command.
     *
     * @return int
     */
    public function handle(Redis $redis)
    {
        // Database may have nothing or may have existing records.
        // Stream may have nothing or may have existing records.
        // Need to backfill database from stream.
        // Need to make sure we have a full 5 seconds.
        // TODO: Add test for millisecond boundaries

        $redisNow = $redis->now();

        dump('redisNow: '.$redisNow->format('Y-m-d H:i:s v'));

        // Get the latest date from the database
        $lastDate = DB::table('pulse_requests')
            ->where('resolution', 5)
            ->max('date');

        dump('lastDate: '.$lastDate);

        // dd($redis->xrange('pulse_requests', '-', '+'));

        // Back fill the database from the stream

        // If there is nothing in the database, start from the oldest record in the stream, or 7 days ago, which ever is closest.
        if ($lastDate === null) {
            dump('No last date, getting oldest stream key...');

            $oldestStreamDate = $redis->oldestStreamEntryDate('pulse_requests');

            if ($oldestStreamDate) {
                dump('oldestStreamDate: '.$oldestStreamDate->format('Y-m-d H:i:s v'));
                $from = $redisNow->subDays(7)->max($oldestStreamDate);
                dump('from: '.$from->format('Y-m-d H:i:s v'));
            } else {
                dd('no data in the stream');
            }
        } else {
            dump('lastDate found');
            $from = CarbonImmutable::parse($lastDate, 'UTC')->addSeconds(5);
            dump('from: '.$from->format('Y-m-d H:i:s v'));
        }

        $from = $from->ceilSeconds(5); // 20:00:00 000

        $to = $from->addSeconds(4)->endOfSecond(); // 20:00:04 999

        $aggregates = collect([]);
        while ($to->lte($redisNow->floorSeconds(5))) {
            $aggregates = $aggregates->merge($this->getAggregates($from, $to));
            // $aggregates->merge(dump($this->getAggregates($from, $to)));
            $from = $from->addSeconds(5);
            $to = $to->addSeconds(5);
        }

        dump('count: '.$aggregates->count());

        if ($aggregates->count() > 0) {
            dump('inserting records...');
            foreach ($aggregates->chunk(1000) as $chunk) {
                DB::table('pulse_requests')->insert($chunk->toArray());
            }
        }

        // DB::table('pulse_requests')->insert(array_merge(...$allAggregates));

        //         foreach ($allAggregates as $aggregate) {
        //             dump($aggregate);
        //         }

        // $latestStream = array_key_first($redis->xrevrange('pulse_requests', '+', '-', 1));
        // dd($oldestStream, $latestStream);

        // $allRequests = collect($redis->xrange('pulse_requests', '-', '+'));
        // dump($allRequests->keys()->first(), $allRequests->keys()->last(), $allRequests->count());

    }

    protected function getAggregates($from, $to)
    {
        $requests = collect($redis->xrange('pulse_requests', $from->getTimestampMs(), $to->getTimestampMs()));

        $counts = [];
        foreach ($requests as $request) {
            $counts[$request['route']][$request['user_id'] ?: '0'][] = $request['duration'];
        }

        $aggregates = [];
        foreach ($counts as $route => $userDurations) {
            foreach ($userDurations as $user => $durations) {
                $durations = collect($durations);

                $aggregates[] = [
                    'date' => $from->format('Y-m-d H:i:s'),
                    'resolution' => 5,
                    'route' => $route,
                    'user_id' => $user ?: null,
                    'volume' => $durations->count(),
                    'average' => $durations->average(),
                    'slowest' => (int) $durations->max(),
                ];
            }
        }

        return $aggregates;
    }
}
