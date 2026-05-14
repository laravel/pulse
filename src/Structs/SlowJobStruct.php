<?php

namespace Laravel\Pulse\Structs;

class SlowJobStruct
{
    public function __construct(
        public string $job,
        public string $slowest,
        public int $count,
        public int $threshold,
    ) {
        //
    }
}