<?php

namespace Laravel\Pulse\Recorders;

use Illuminate\Config\Repository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Pulse\Entry;
use Laravel\Pulse\Events\SharedBeat;
use RuntimeException;

/**
 * @internal
 */
class SystemStats
{
    /**
     * The events to listen for.
     *
     * @var class-string
     */
    public string $listen = SharedBeat::class;

    /**
     * Create a new recorder instance.
     */
    public function __construct(
        protected Repository $config,
    ) {
        //
    }

    /**
     * Record the system stats.
     *
     * @return ?list<\Laravel\Pulse\Entry>
     */
    public function record(SharedBeat $event): ?array
    {
        if ($event->time->second % 15 !== 0) {
            return null;
        }

        $server = $this->config->get('pulse.recorders.'.self::class.'.server_name');
        $slug = Str::slug($server);

        $memoryTotal = match (PHP_OS_FAMILY) {
            'Darwin' => intval(`sysctl hw.memsize | grep -Eo '[0-9]+'` / 1024 / 1024),
            'Linux' => intval(`cat /proc/meminfo | grep MemTotal | grep -E -o '[0-9]+'` / 1024),
            default => throw new RuntimeException('The pulse:check command does not currently support '.PHP_OS_FAMILY),
        };

        $memoryUsed = match (PHP_OS_FAMILY) {
            'Darwin' => $memoryTotal - intval(intval(`vm_stat | grep 'Pages free' | grep -Eo '[0-9]+'`) * intval(`pagesize`) / 1024 / 1024), // MB
            'Linux' => $memoryTotal - intval(`cat /proc/meminfo | grep MemAvailable | grep -E -o '[0-9]+'` / 1024), // MB
            default => throw new RuntimeException('The pulse:check command does not currently support '.PHP_OS_FAMILY),
        };

        $cpu = match (PHP_OS_FAMILY) {
            'Darwin' => (int) `top -l 1 | grep -E "^CPU" | tail -1 | awk '{ print $3 + $5 }'`,
            'Linux' => (int) `top -bn1 | grep '%Cpu(s)' | tail -1 | grep -Eo '[0-9]+\.[0-9]+' | head -n 4 | tail -1 | awk '{ print 100 - $1 }'`,
            default => throw new RuntimeException('The pulse:check command does not currently support '.PHP_OS_FAMILY),
        };

        // TODO: figure out to send values..
        DB::table('pulse_values')->updateOrInsert([
            'key' => 'system:'.$slug,
        ], ['value' => json_encode([
            'name' => $server,
            'timestamp' => $event->time->timestamp,
            'memory_used' => $memoryUsed,
            'memory_total' => $memoryTotal,
            'cpu' => $cpu,
            'storage' => collect($this->config->get('pulse.recorders.'.self::class.'.directories')) // @phpstan-ignore argument.templateType argument.templateType
                ->map(fn (string $directory) => [
                    'directory' => $directory,
                    'total' => $total = intval(round(disk_total_space($directory) / 1024 / 1024)), // MB
                    'used' => intval(round($total - (disk_free_space($directory) / 1024 / 1024))), // MB
                ])
                ->toArray(),
        ])]);

        return [
            (new Entry(
                timestamp: (int) $event->time->timestamp,
                type: 'cpu',
                key: $slug,
                value: $cpu,
            ))->avg(),

            (new Entry(
                timestamp: (int) $event->time->timestamp,
                type: 'memory',
                key: $slug,
                value: $memoryUsed,
            ))->avg(),
        ];
    }
}
