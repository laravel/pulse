<?php

namespace Laravel\Pulse\Checks;

use Carbon\CarbonImmutable;
use Illuminate\Config\Repository;
use Laravel\Pulse\Entries\Entry;
use RuntimeException;

/**
 * @internal
 */
class SystemStats
{
    public function __construct(
        protected Repository $config,
    ) {
        //
    }

    /**
     * Resolve the systems stats.
     */
    public function __invoke(CarbonImmutable $now): ?Entry
    {
        if ($now->second % 15 !== 0) {
            return null;
        }

        return new Entry('pulse_servers', [
            'date' => $now->toDateTimeString(),
            'server' => $this->config->get('pulse.server_name'),
            ...match (PHP_OS_FAMILY) {
                'Darwin' => [
                    'cpu_percent' => (int) `top -l 1 | grep -E "^CPU" | tail -1 | awk '{ print $3 + $5 }'`,
                    'memory_total' => $memoryTotal = intval(`sysctl hw.memsize | grep -Eo '[0-9]+'` / 1024 / 1024), // MB
                    'memory_used' => $memoryTotal - intval(intval(`vm_stat | grep 'Pages free' | grep -Eo '[0-9]+'`) * intval(`pagesize`) / 1024 / 1024), // MB
                ],
                'Linux' => [
                    'cpu_percent' => (int) `top -bn1 | grep '%Cpu(s)' | tail -1 | grep -Eo '[0-9]+\.[0-9]+' | head -n 4 | tail -1 | awk '{ print 100 - $1 }'`,
                    'memory_total' => $memoryTotal = intval(`cat /proc/meminfo | grep MemTotal | grep -E -o '[0-9]+'` / 1024), // MB
                    'memory_used' => $memoryTotal - intval(`cat /proc/meminfo | grep MemAvailable | grep -E -o '[0-9]+'` / 1024), // MB
                ],
                default => throw new RuntimeException('The pulse:check command does not currently support '.PHP_OS_FAMILY),
            },
            'storage' => collect($this->config->get('pulse.directories'))->map(fn (string $directory) => [
                'directory' => $directory,
                'total' => $total = intval(round(disk_total_space($directory) / 1024 / 1024)), // MB
                'used' => intval(round($total - (disk_free_space($directory) / 1024 / 1024))), // MB
            ])->toJson(),
        ]);
    }
}
