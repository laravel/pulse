<?php

namespace Laravel\Pulse\Structs;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ServerStruct
{
    /**
     * @param Collection<string, int|null> $cpu
     * @param Collection<string, int|null> $memory
     * @param Collection<int, StorageDirectoryStruct> $storage
     */
    public function __construct(
        public string $name,
        public int $cpu_current,
        public Collection $cpu,
        public int $memory_current,
        public int $memory_total,
        public Collection $memory,
        public Collection $storage,
        public CarbonInterface $updated_at,
        public bool $recently_reported,
    ) {
        //
    }
}