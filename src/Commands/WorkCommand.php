<?php

namespace Laravel\Pulse\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Benchmark;
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
     * @todo
     *  - Roll up into 30 seconds, etc.
     *  - Handle other streams, not just the requests.
     *  - Trimming the streams.
     *
     * @return int
     */
    public function handle(Redis $redis)
    {
        $redisNow = $redis->now();

        dump('redisNow: '.$redisNow->format('Y-m-d H:i:s v'));

        // Get the latest date from the database
        $lastDate = DB::table('pulse_requests')
            ->where('resolution', 5)
            ->max('date');

        dump('lastDate: '.$lastDate);

        if ($lastDate !== null) {
            dump('lastDate found');
            $from = CarbonImmutable::parse($lastDate)->addSeconds(5);
        } else {
            dump('No last date, starting 7 days ago from redisNow');
            $from = $redisNow->subDays(7)->floorSeconds(5);
        }

        dump('from: '.$from->format('Y-m-d H:i:s v'));
        $from = $from->getTimestampMs();

        $requests = collect();
        while (true) {
            $redisNow = $redis->now();
            $newRequests = collect($redis->xrange('pulse_requests', $from, '+', 1000));
            echo '.';
            $requests = $requests->merge($newRequests);

            if ($requests->count() > 0) {
                $from = '('.$requests->keys()->last();
            }

            $aggregates = collect();
            while ($requests->count() > 0) {
                $firstKey = $requests->keys()->first();
                $bucketStart = CarbonImmutable::createFromTimestampMs(Str::before($firstKey, '-'))->floorSeconds(5);
                $maxKey = $bucketStart->addSeconds(4)->endOfSecond()->getTimestampMs();
                // dump($firstKey, $lastKey);

                $bucket = $requests->takeWhile(function ($item, $key) use ($maxKey) {
                    $time = Str::before($key, '-');

                    return $time <= $maxKey;
                });

                if ($bucket->count() === $requests->count() && $redisNow->getTimestampMs() < $maxKey) {
                    break;
                }

                $aggregates = $aggregates->merge($this->getAggregates($bucketStart, $bucket));
                $requests = $requests->skip($bucket->count());
                dump("saving bucket of {$bucket->count()} requests");
            }

            if ($aggregates->count() > 0) {
                dump('inserting records...');
                foreach ($aggregates->chunk(1000) as $chunk) {
                    DB::table('pulse_requests')->insert($chunk->all());
                }
            }

            //

            if ($newRequests->count() < 1000) {
                dump('agging 60...');
                $latest60Date = DB::table('pulse_requests')->where('resolution', 60)->latest('date')->value('date');
                dump('latest60Date: '.$latest60Date);
                if ($latest60Date) {
                    $latest60Date = CarbonImmutable::parse($latest60Date)->addMinute()->format('Y-m-d H:i:s');
                }
                $dateSql = $latest60Date ? "AND date >= '{$latest60Date}'" : '';
                $dateSql .= " AND date < '{$redisNow->startOfMinute()->format('Y-m-d H:i:s')}'";
                dump($dateSql);

                dump(Benchmark::measure(fn () => DB::statement(<<<"SQL"
                    INSERT INTO pulse_requests (date, resolution, user_id, route, volume, average, slowest)
                    SELECT bucket, 60, user_id, route, volume, average, slowest
                    FROM (
                      SELECT
                        bucket,
                        user_id,
                        route,
                        SUM(`volume`) as `volume`,
                        AVG(`average`) as `average`,
                        MAX(`slowest`) as `slowest`
                      FROM (
                        SELECT
                          *,
                          DATE_FORMAT(`date`, '%Y-%m-%d %H:%i:00') as `bucket`
                        FROM `pulse_requests`
                        WHERE resolution = 5
                        {$dateSql}
                      ) as `sub`
                      GROUP BY `bucket` ,`user_id` ,`route`
                      ORDER BY `bucket` ASC
                    ) AS agged
                SQL)));

                dump('agging 600...');
                $latest600Date = DB::table('pulse_requests')->where('resolution', 600)->latest('date')->value('date');
                dump('latest600Date: '.$latest600Date);
                if ($latest600Date) {
                    $latest600Date = CarbonImmutable::parse($latest600Date)->addMinute(10)->format('Y-m-d H:i:s');
                }
                $dateSql = $latest600Date ? "AND date >= '{$latest600Date}'" : '';
                $dateSql .= " AND date < '{$redisNow->startOfMinute()->floorMinute(10)->format('Y-m-d H:i:s')}'";
                dump($dateSql);

                dump(Benchmark::measure(fn () => DB::statement(<<<"SQL"
                    INSERT INTO pulse_requests (date, resolution, user_id, route, volume, average, slowest)
                    SELECT bucket, 600, user_id, route, volume, average, slowest
                    FROM (
                      SELECT
                        bucket,
                        user_id,
                        route,
                        SUM(`volume`) as `volume`,
                        AVG(`average`) as `average`,
                        MAX(`slowest`) as `slowest`
                      FROM (
                        SELECT
                          *,
                          DATE_ADD(DATE_FORMAT(date, '%Y-%m-%d %H:00:00'), INTERVAL MINUTE(date) - MOD(MINUTE(date), 10) MINUTE) as `bucket`
                        FROM `pulse_requests`
                        WHERE resolution = 60
                        {$dateSql}
                      ) as `sub`
                      GROUP BY `bucket` ,`user_id` ,`route`
                      ORDER BY `bucket` ASC
                    ) AS agged
                SQL)));

                sleep(5);
            }
        }
    }

    /**
     * Handle the command.
     *
     * @return int
     */
    public function handlex(Redis $redis)
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
            $from = CarbonImmutable::parse($lastDate)->addSeconds(5);
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

        return 0;
    }

    protected function getAggregates($from, $requests)
    {
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
