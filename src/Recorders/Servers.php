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
     * Callback to detect if running in a container.
     *
     * @var null|(callable(): bool)
     */
    protected static $detectContainerUsing;

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
     * Detect if running in a container via the given callback.
     *
     * @param  null|(callable(): bool)  $callback
     */
    public static function detectContainerUsing(?callable $callback): void
    {
        self::$detectContainerUsing = $callback;
    }

    /**
     * Detect if running in a container.
     */
    protected function isContainer(): bool
    {
        if (self::$detectContainerUsing) {
            return (self::$detectContainerUsing)();
        }

        if (file_exists('/.dockerenv') || file_exists('/.dockerinit')) {
            return true;
        }

        if (getenv('KUBERNETES_SERVICE_HOST') !== false) {
            return true;
        }

        if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/1/cgroup')) {
            $content = file_get_contents('/proc/1/cgroup');
            if ($content && (str_contains($content, 'docker') ||
                            strpos($content, 'kubepods') !== false ||
                    str_contains($content, 'containerd'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Record the system stats.
     */
    public function record(SharedBeat $event): void
    {
        $this->throttle(15, $event, function ($event) {
            $server = $this->config->get('pulse.recorders.'.self::class.'.server_name');

            if ($this->isContainer()) {
                $containerInfo = '';

                if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/1/cgroup')) {
                    $content = file_get_contents('/proc/1/cgroup');
                    if ($content) {
                        preg_match('/[0-9a-f]{12}/', $content, $matches);
                        if (!empty($matches)) {
                            $containerInfo = ' [container: ' . substr($matches[0], 0, 12) . ']';
                        } else {
                            $containerInfo = ' [containerized]';
                        }
                    }
                } else {
                    $containerInfo = ' [containerized]';
                }

                $server .= $containerInfo;
            }

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

        if ($this->isContainer() && PHP_OS_FAMILY === 'Linux') {
            try {
                if (file_exists('/sys/fs/cgroup/cpu.stat')) {
                    $stat = file_get_contents('/sys/fs/cgroup/cpu.stat');
                    if ($stat !== false) {
                        preg_match('/usage_usec\s+(\d+)/', $stat, $matches);
                        if (!empty($matches)) {
                            $usage1 = (int) $matches[1];
                            usleep(100000); // 100ms
                            preg_match('/usage_usec\s+(\d+)/', $stat, $matches);
                            if (!empty($matches)) {
                                $usage2 = (int) $matches[1];
                                $diff = $usage2 - $usage1;
                                return min(100, (int) ($diff / 1000 / 100));
                            }
                        }
                    }
                }

                if (file_exists('/sys/fs/cgroup/cpu/cpuacct.usage') || file_exists('/sys/fs/cgroup/cpuacct/cpuacct.usage')) {
                    $file = file_exists('/sys/fs/cgroup/cpu/cpuacct.usage')
                        ? '/sys/fs/cgroup/cpu/cpuacct.usage'
                        : '/sys/fs/cgroup/cpuacct/cpuacct.usage';

                    $usage1 = (int) file_get_contents($file);
                    usleep(100000); // 100ms
                    $usage2 = (int) file_get_contents($file);
                    $diff = $usage2 - $usage1;
                    return min(100, (int) ($diff / 1000000 / 100));
                }
            } catch (\Exception) {
                throw new RuntimeException('The pulse:check was unable to determine CPU usage in a containerized environment. Please ensure the cgroup files are accessible and valid.');
            }
        }

        return match (PHP_OS_FAMILY) {
            'Darwin' => (int) `top -l 1 | grep -E "^CPU" | tail -1 | awk '{ print $3 + $5 }'`,
            'Linux' => (int) `top -bn1 | grep -E '^(%Cpu|CPU)' | awk '{ print $2 + $4 }'`,
            'Windows' => (int) trim(`wmic cpu get loadpercentage | more +1`),
            'BSD' => (int) `top -b -d 2| grep 'CPU: ' | tail -1 | awk '{print$10}' | grep -Eo '[0-9]+\.[0-9]+' | awk '{ print 100 - $1 }'`,
            default => throw new RuntimeException('The pulse:check command does not currently support '.PHP_OS_FAMILY),
        };
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

        if ($this->isContainer() && PHP_OS_FAMILY === 'Linux') {
            try {
                if (file_exists('/sys/fs/cgroup/memory.max') && file_exists('/sys/fs/cgroup/memory.current')) {
                    $memoryLimit = (int) file_get_contents('/sys/fs/cgroup/memory.max');
                    $memoryCurrent = (int) file_get_contents('/sys/fs/cgroup/memory.current');

                    if ($memoryLimit === 0 || $memoryLimit === PHP_INT_MAX || $memoryLimit === -1) {
                        $memoryLimit = intval(`cat /proc/meminfo | grep MemTotal | grep -E -o '[0-9]+'` * 1024);
                    }

                    return [
                        'total' => intval($memoryLimit / 1024 / 1024),
                        'used' => intval($memoryCurrent / 1024 / 1024),
                    ];
                }

                if (file_exists('/sys/fs/cgroup/memory/memory.limit_in_bytes') && file_exists('/sys/fs/cgroup/memory/memory.usage_in_bytes')) {
                    $memoryLimit = (int) file_get_contents('/sys/fs/cgroup/memory/memory.limit_in_bytes');
                    $memoryUsage = (int) file_get_contents('/sys/fs/cgroup/memory/memory.usage_in_bytes');

                    if ($memoryLimit === 0 || $memoryLimit === PHP_INT_MAX || $memoryLimit === -1 || $memoryLimit > 10 * 1024 * 1024 * 1024 * 1024) { // > 10TB
                        $memoryLimit = intval(`cat /proc/meminfo | grep MemTotal | grep -E -o '[0-9]+'` * 1024);
                    }

                    return [
                        'total' => intval($memoryLimit / 1024 / 1024),
                        'used' => intval($memoryUsage / 1024 / 1024),
                    ];
                }
            } catch (\Exception) {
                throw new RuntimeException('The pulse:check was unable to determine memory usage in a containerized environment. Please ensure the cgroup files are accessible and valid.');
            }
        }

        $memoryTotal = match (PHP_OS_FAMILY) {
            'Darwin' => intval(`sysctl hw.memsize | grep -Eo '[0-9]+'` / 1024 / 1024),
            'Linux' => intval(`cat /proc/meminfo | grep MemTotal | grep -E -o '[0-9]+'` / 1024),
            'Windows' => intval(((int) trim(`wmic ComputerSystem get TotalPhysicalMemory | more +1`)) / 1024 / 1024),
            'BSD' => intval(`sysctl hw.physmem | grep -Eo '[0-9]+'` / 1024 / 1024),
            default => throw new RuntimeException('The pulse:check command does not currently support '.PHP_OS_FAMILY),
        };

        $memoryUsed = match (PHP_OS_FAMILY) {
            'Darwin' => $memoryTotal - intval(intval(`vm_stat | grep 'Pages free' | grep -Eo '[0-9]+'`) * intval(`pagesize`) / 1024 / 1024), // MB
            'Linux' => $memoryTotal - intval(`cat /proc/meminfo | grep MemAvailable | grep -E -o '[0-9]+'` / 1024), // MB
            'Windows' => $memoryTotal - intval(((int) trim(`wmic OS get FreePhysicalMemory | more +1`)) / 1024), // MB
            'BSD' => intval(intval(`( sysctl vm.stats.vm.v_cache_count | grep -Eo '[0-9]+' ; sysctl vm.stats.vm.v_inactive_count | grep -Eo '[0-9]+' ; sysctl vm.stats.vm.v_active_count | grep -Eo '[0-9]+' ) | awk '{s+=$1} END {print s}'`) * intval(`pagesize`) / 1024 / 1024), // MB
            default => throw new RuntimeException('The pulse:check command does not currently support '.PHP_OS_FAMILY),
        };

        return [
            'total' => $memoryTotal,
            'used' => $memoryUsed,
        ];
    }
}
