<?php

namespace Laravel\Pulse\Structs;

class SlowRequestStruct
{
    public function __construct(
        public string $uri,
        public string $method,
        public ?string $action,
        public int $count,
        public int $slowest,
        public int $threshold,
    ) {
        //
    }
}