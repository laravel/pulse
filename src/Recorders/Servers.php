<?php

namespace Laravel\Pulse\Recorders;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;
use Laravel\Pulse\Events\SharedBeat;
use Laravel\Pulse\Pulse;
use RuntimeException;

/**
 * @internal
 */
class Servers
{
    use Concerns\Throttling;

    /**
     * Callback to detect CPU usage.
     *
     * @var null|(callable(): int)
     */
    protected static $detectCpuUsing;

    /**
     * Callback to detect memory.
     *
     * @var null|(callable(): array{total: int, used: int})
     */
    protected static $detectMemoryUsing;

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
     * Detect CPU via the given callback.
     *
     * @param  null|(callable(): int)  $callback
     */
    public static function detectCpuUsing(?callable $callback): void
    {
        self::$detectCpuUsing = $callback;
    }

    /**
     * Detect memory via the given callback.
     *
     * @param  null|(callable(): array{total: int, used: int})  $callback
     */
    public static function detectMemoryUsing(?callable $callback): void
    {
        self::$detectMemoryUsing = $callback;
    }

    /**
     * Record the system stats.
     */
    public function record(SharedBeat $event): void
    {
        $this->throttle(15, $event, function ($event) {
            $server = $this->config->get('pulse.recorders.'.self::class.'.server_name');
            $slug = Str::slug($server);

            ['total' => $memoryTotal, 'used' => $memoryUsed] = $this->memory();
            $cpu = $this->cpu();

            $this->pulse->record('cpu', $slug, $cpu, $event->time)->avg()->onlyBuckets();
            $this->pulse->record('memory', $slug, $memoryUsed, $event->time)->avg()->onlyBuckets();
            $this->pulse->set('system', $slug, json_encode([
                'name' => $server,
                'cpu' => $cpu,
                'memory_used' => $memoryUsed,
                'memory_total' => $memoryTotal,
                'storage' => collect($this->config->get('pulse.recorders.'.self::class.'.directories')) // @phpstan-ignore argument.templateType, argument.templateType
                    ->filter(fn (string $directory) => ($this->pulse->rescue(fn () => disk_total_space($directory)) ?? false) !== false)
                    ->map(fn (string $directory) => [
                        'directory' => $directory,
                        'total' => $total = intval(round(disk_total_space($directory) / 1024 / 1024)), // MB
                        'used' => intval(round($total - (disk_free_space($directory) / 1024 / 1024))), // MB
                    ])
                    ->all(),
                'booted_at' => $this->bootedAt(),
            ], flags: JSON_THROW_ON_ERROR), $event->time);
        });
    }

    /**
     * CPU usage.
     */
    protected function cpu(): int
    {
        if (self::$detectCpuUsing) {
            return (self::$detectCpuUsing)();
        }

        return match (PHP_OS_FAMILY) {
            'Darwin' => (int) shell_exec("top -l 1 | grep -E \"^CPU\" | tail -1 | awk '{ print $3 + $5 }'"),
            'Linux' => (int) shell_exec("top -bn1 | grep -E '^(%Cpu|CPU)' | awk '{ print $2 + $4 }'"),
            'Windows' => (int) trim((string) shell_exec('wmic cpu get loadpercentage | more +1')),
            'BSD' => (int) shell_exec("top -b -d 2| grep 'CPU: ' | tail -1 | awk '{print$10}' | grep -Eo '[0-9]+\.[0-9]+' | awk '{ print 100 - $1 }'"),
            default => throw new RuntimeException('The pulse:check command does not currently support '.PHP_OS_FAMILY),
        };
    }

    /**
     * Server boot timestamp (Unix epoch seconds).
     */
    protected function bootedAt(): ?int
    {
        return match (PHP_OS_FAMILY) {
            'Linux' => $this->linuxBootedAt(),
            'Darwin', 'BSD' => $this->darwinBsdBootedAt(),
            'Windows' => $this->windowsBootedAt(),
            default => null,
        };
    }

    /**
     * Boot time on Linux via /proc/stat (btime), falling back to /proc/uptime.
     */
    protected function linuxBootedAt(): ?int
    {
        if (is_readable('/proc/stat')) {
            foreach (file('/proc/stat') ?: [] as $line) {
                if (str_starts_with($line, 'btime ')) {
                    return (int) trim(substr($line, 6));
                }
            }
        }

        if (is_readable('/proc/uptime')) {
            $contents = file_get_contents('/proc/uptime');
            if ($contents !== false) {
                return (int) round(time() - (float) explode(' ', $contents)[0]);
            }
        }

        return null;
    }

    /**
     * Boot time on Darwin/BSD via sysctl kern.boottime.
     */
    protected function darwinBsdBootedAt(): ?int
    {
        $output = shell_exec('sysctl kern.boottime');

        if ($output && preg_match('/sec = (\d+)/', $output, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Boot time on Windows via wmic os get LastBootUpTime.
     */
    protected function windowsBootedAt(): ?int
    {
        $output = shell_exec('wmic os get LastBootUpTime /value');

        if ($output && preg_match('/LastBootUpTime=(\d{14})/', $output, $matches)) {
            $dt = \DateTime::createFromFormat('YmdHis', $matches[1]);

            return $dt ? $dt->getTimestamp() : null;
        }

        return null;
    }

    /**
     * Memory usage.
     *
     * @return array{total: int, used: int}
     */
    protected function memory(): array
    {
        if (self::$detectMemoryUsing) {
            return (self::$detectMemoryUsing)();
        }

        $memoryTotal = match (PHP_OS_FAMILY) {
            'Darwin' => intval(intval(shell_exec("sysctl hw.memsize | grep -Eo '[0-9]+'")) / 1024 / 1024),
            'Linux' => intval(intval(shell_exec("cat /proc/meminfo | grep MemTotal | grep -E -o '[0-9]+'")) / 1024),
            'Windows' => intval(((int) trim((string) shell_exec('wmic ComputerSystem get TotalPhysicalMemory | more +1'))) / 1024 / 1024),
            'BSD' => intval(intval(shell_exec("sysctl hw.physmem | grep -Eo '[0-9]+'")) / 1024 / 1024),
            default => throw new RuntimeException('The pulse:check command does not currently support '.PHP_OS_FAMILY),
        };

        $memoryUsed = match (PHP_OS_FAMILY) {
            'Darwin' => $memoryTotal - intval(intval(shell_exec("vm_stat | grep 'Pages free' | grep -Eo '[0-9]+'")) * intval(shell_exec('pagesize')) / 1024 / 1024), // MB
            'Linux' => $memoryTotal - intval(intval(shell_exec("cat /proc/meminfo | grep MemAvailable | grep -E -o '[0-9]+'")) / 1024), // MB
            'Windows' => $memoryTotal - intval(((int) trim((string) shell_exec('wmic OS get FreePhysicalMemory | more +1'))) / 1024), // MB
            'BSD' => intval(intval(shell_exec("( sysctl vm.stats.vm.v_cache_count | grep -Eo '[0-9]+' ; sysctl vm.stats.vm.v_inactive_count | grep -Eo '[0-9]+' ; sysctl vm.stats.vm.v_active_count | grep -Eo '[0-9]+' ) | awk '{s+=$1} END {print s}'")) * intval(shell_exec('pagesize')) / 1024 / 1024), // MB
            default => throw new RuntimeException('The pulse:check command does not currently support '.PHP_OS_FAMILY),
        };

        return [
            'total' => $memoryTotal,
            'used' => $memoryUsed,
        ];
    }
}
