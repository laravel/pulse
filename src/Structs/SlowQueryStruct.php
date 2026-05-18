<?php

namespace Laravel\Pulse\Structs;

class SlowQueryStruct
{
    public function __construct(
        public string $sql,
        public ?string $location,
        public int $slowest,
        public int $count,
        public int $threshold
    ) {
        //
    }
}