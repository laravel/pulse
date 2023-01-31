<?php

namespace Laravel\Pulse\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Laravel\Pulse\RedisAdapter;
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
     * @return int
     */
    public function handle()
    {
        $slug = Str::slug(config('pulse.server_name'));
        RedisAdapter::hset('pulse_servers', $slug, config('pulse.server_name'));

        while (true) {
            $stats = [
                'timestamp' => now()->timestamp,
                ...$this->getStats(),
                'storage' => collect(config('pulse.directories'))->map(fn ($directory) => [
                    'directory' => $directory,
                    'total' => $total = intval(round(disk_total_space($directory) / 1024 / 1024)), // MB
                    'used' => intval(round($total - (disk_free_space($directory) / 1024 / 1024))), // MB
                ])->toJson(),
            ];

            RedisAdapter::xadd("pulse_servers:{$slug}", $stats);
            RedisAdapter::xtrim("pulse_servers:{$slug}", 60);

            $this->line(json_encode($stats));

            sleep(2);
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
        return [
            'cpu' => (int) `top -l 1 | grep -E "^CPU" | tail -1 | awk '{ print $3 + $5 }'`,
            'memory_total' => $memoryTotal = intval(`sysctl hw.memsize | grep -Eo '[0-9]+'` / 1024 / 1024), // MB
            'memory_used' => $memoryTotal - intval(intval(`vm_stat | grep 'Pages free' | grep -Eo '[0-9]+'`) * intval(`pagesize`) / 1024 / 1024), // MB
        ];
    }

    protected function getLinuxStats()
    {
        return [
            'cpu' => (int) `top -bn1 | grep '%Cpu(s)' | tail -1 | grep -Eo '[0-9]+\.[0-9]+' | head -n 4 | tail -1 | awk '{ print 100 - $1 }'`,
            'memory_total' => $memTotal = intval(`cat /proc/meminfo | grep MemTotal | grep -E -o '[0-9]+'` / 1024), // MB
            'memory_used' => $memTotal - intval(`cat /proc/meminfo | grep MemAvailable | grep -E -o '[0-9]+'` / 1024), // MB
        ];
    }
}
