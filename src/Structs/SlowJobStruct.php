<?php

namespace Laravel\Pulse\Structs;

class SlowJobStruct
{
    public function __construct(
        public string $job,
        public int $slowest,
        public int $count,
        public int $threshold,
    ) {
        //
    }
}