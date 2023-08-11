<?php

namespace Laravel\Pulse\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'pulse:check')]
class CheckCommand extends Command
{
    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'pulse:check';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Take a snapshot of the current server\'s pulse';

    /**
     * The interval, in seconds, to check for new stats.
     */
    protected int $interval = 15;

    /**
     * Handle the command.
     */
    public function handle(): int
    {
        $lastRestart = Cache::get('illuminate:pulse:restart');

        $lastSnapshotAt = (new CarbonImmutable)->floorSeconds($this->interval);

        while (true) {
            if (Cache::get('illuminate:pulse:restart') !== $lastRestart) {
                $this->comment('Pulse restart requested. Exiting at '.$now->toDateTimeString());

                return self::SUCCESS;
            }

            $now = new CarbonImmutable();

            if ($now->subSeconds($this->interval)->lessThan($lastSnapshotAt)) {
                $this->comment('Sleeping for a second at '.$now->toDateTimeString());

                Sleep::for(1)->second();

                continue;
            }

            $lastSnapshotAt = $now->floorSeconds($this->interval);

            /*
             * Check system stats.
             */

            $stats = [
                'date' => $lastSnapshotAt->toDateTimeString(),
                'server' => config('pulse.server_name'),
                ...$this->getStats(),
                'storage' => collect(config('pulse.directories'))->map(fn ($directory) => [
                    'directory' => $directory,
                    'total' => $total = intval(round(disk_total_space($directory) / 1024 / 1024)), // MB
                    'used' => intval(round($total - (disk_free_space($directory) / 1024 / 1024))), // MB
                ])->toJson(),
            ];

            // TODO: do this via the injest
            DB::table('pulse_servers')->insert($stats);

            $this->line('<fg=gray>[system stats]</> '.json_encode($stats));

            /*
             * Check queue sizes.
             */

            if (Cache::lock("illuminate:pulse:check-queue-sizes:{$lastSnapshotAt->timestamp}", $this->interval)->get()) {
                $sizes = collect(config('pulse.queues'))
                    ->map(fn ($queue) => [
                        'date' => $lastSnapshotAt->toDateTimeString(),
                        'queue' => $queue,
                        'size' => Queue::size($queue),
                        'failed' => collect(app('queue.failer')->all())
                            ->filter(fn ($job) => $job->queue === $queue)
                            ->count(),
                    ]);

                DB::table('pulse_queue_sizes')->insert($sizes->all());

                $this->line('<fg=gray>[queue sizes]</> '.$sizes->toJson());
            }

            $this->comment('Stats and queue sizes checked at: '.$now->toDateTimeString());
        }
    }

    /**
     * Collect stats.
     */
    protected function getStats(): array
    {
        return match (PHP_OS_FAMILY) {
            'Darwin' => $this->getDarwinStats(),
            'Linux' => $this->getLinuxStats(),
            default => throw new RuntimeException('The pulse:check command does not currently support '.PHP_OS_FAMILY),
        };
    }

    /**
     * Collect stats for "Darwin" based systems.
     */
    protected function getDarwinStats(): array
    {
        return [
            'cpu_percent' => (int) `top -l 1 | grep -E "^CPU" | tail -1 | awk '{ print $3 + $5 }'`,
            'memory_total' => $memoryTotal = intval(`sysctl hw.memsize | grep -Eo '[0-9]+'` / 1024 / 1024), // MB
            'memory_used' => $memoryTotal - intval(intval(`vm_stat | grep 'Pages free' | grep -Eo '[0-9]+'`) * intval(`pagesize`) / 1024 / 1024), // MB
        ];
    }

    /**
     * Collect stats for "Linux" based systems.
     */
    protected function getLinuxStats(): array
    {
        return [
            'cpu_percent' => (int) `top -bn1 | grep '%Cpu(s)' | tail -1 | grep -Eo '[0-9]+\.[0-9]+' | head -n 4 | tail -1 | awk '{ print 100 - $1 }'`,
            'memory_total' => $memoryTotal = intval(`cat /proc/meminfo | grep MemTotal | grep -E -o '[0-9]+'` / 1024), // MB
            'memory_used' => $memoryTotal - intval(`cat /proc/meminfo | grep MemAvailable | grep -E -o '[0-9]+'` / 1024), // MB
        ];
    }
}
