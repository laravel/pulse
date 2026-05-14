<?php

namespace Laravel\Pulse\Structs;

class SlowQueryStruct
{
    public function __construct(
        public string $sql,
        public ?string $location,
        public string $slowest,
        public int $count,
        public int $threshold
    ) {
        //
    }
}