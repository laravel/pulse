<?php

namespace Laravel\Pulse\Recorders;

use Illuminate\Config\Repository;
use Illuminate\Support\Str;
use Laravel\Pulse\Events\SharedBeat;
use Laravel\Pulse\Pulse;
use RuntimeException;

/**
 * @internal
 */
class Servers
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
        protected Pulse $pulse,
        protected Repository $config
    ) {
        //
    }

    /**
     * Record the system stats.
     */
    public function record(SharedBeat $event): void
    {
        if ($event->time->second % 15 !== 0) {
            return;
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

        $this->pulse->record('cpu', $slug, $cpu, $event->time)->avg()->onlyBuckets();
        $this->pulse->record('memory', $slug, $memoryUsed, $event->time)->avg()->onlyBuckets();
        $this->pulse->set('system', $slug, json_encode([
            'name' => $server,
            'cpu' => $cpu,
            'memory_used' => $memoryUsed,
            'memory_total' => $memoryTotal,
            'storage' => collect($this->config->get('pulse.recorders.'.self::class.'.directories')) // @phpstan-ignore argument.templateType argument.templateType
                ->map(fn (string $directory) => [
                    'directory' => $directory,
                    'total' => $total = intval(round(disk_total_space($directory) / 1024 / 1024)), // MB
                    'used' => intval(round($total - (disk_free_space($directory) / 1024 / 1024))), // MB
                ])
                ->all(),
        ], flags: JSON_THROW_ON_ERROR), $event->time);
    }
}
