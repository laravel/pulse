<?php

namespace Laravel\Pulse\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

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
     * Handle the command.
     *
     * @return void
     */
    public function handle()
    {
        $lastSnapshotAt = now()->floorSeconds(15);

        while (true) {
            $now = now()->toImmutable();

            if ($now->subSeconds(15)->lessThan($lastSnapshotAt)) {
                sleep(1);

                continue;
            }

            $lastSnapshotAt = $now->floorSeconds(15);

            $stats = [
                'date' => $lastSnapshotAt->toDateTimeString(),
                'server' => config('pulse.server_name'),
                ...$this->getStats(),
                'storage' => collect(config('pulse.directories'))->map(fn ($directory) => [
                    'directory' => $directory,
                    'used_percent' => round((disk_total_space($directory) - disk_free_space($directory)) / disk_total_space($directory) * 100),
                ])->toJson(),
            ];

            DB::table('pulse_servers')->insert($stats);

            $this->line(json_encode($stats));
        }
    }

    protected function getStats()
    {
        return match (PHP_OS_FAMILY) {
            'Darwin' => $this->getDarwinStats(),
            'Linux' => $this->getLinuxStats(),
            default => throw new RuntimeException('The pulse:check command does not currently support '.PHP_OS_FAMILY),
        };
    }

    protected function getDarwinStats()
    {
        $memoryTotal = intval(`sysctl hw.memsize | grep -Eo '[0-9]+'` / 1024 / 1024); // MB
        $memoryUsed = $memoryTotal - intval(intval(`vm_stat | grep 'Pages free' | grep -Eo '[0-9]+'`) * intval(`pagesize`) / 1024 / 1024); // MB

        return [
            'cpu_percent' => (int) `top -l 1 | grep -E "^CPU" | tail -1 | awk '{ print $3 + $5 }'`,
            'memory_percent' => round($memoryUsed / $memoryTotal * 100),
            // 'memory_total' => $memoryTotal = intval(`sysctl hw.memsize | grep -Eo '[0-9]+'` / 1024 / 1024), // MB
            // 'memory_used' => $memoryTotal - intval(intval(`vm_stat | grep 'Pages free' | grep -Eo '[0-9]+'`) * intval(`pagesize`) / 1024 / 1024), // MB
        ];
    }

    protected function getLinuxStats()
    {
        $memoryTotal = intval(`cat /proc/meminfo | grep MemTotal | grep -E -o '[0-9]+'` / 1024); // MB
        $memoryUsed = $memoryTotal - intval(`cat /proc/meminfo | grep MemAvailable | grep -E -o '[0-9]+'` / 1024); // MB

        return [
            'cpu_percent' => (int) `top -bn1 | grep '%Cpu(s)' | tail -1 | grep -Eo '[0-9]+\.[0-9]+' | head -n 4 | tail -1 | awk '{ print 100 - $1 }'`,
            'memory_percent' => round($memoryUsed / $memoryTotal * 100),
            // 'memory_total' => $memTotal = intval(`cat /proc/meminfo | grep MemTotal | grep -E -o '[0-9]+'` / 1024), // MB
            // 'memory_used' => $memTotal - intval(`cat /proc/meminfo | grep MemAvailable | grep -E -o '[0-9]+'` / 1024), // MB
        ];
    }
}
