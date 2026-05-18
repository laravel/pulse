<?php

namespace Laravel\Pulse\Structs;

class SlowOutgoingRequestStruct
{
    public function __construct(
        public string $method,
        public string $uri,
        public int $slowest,
        public int $count,
        public int $threshold,
    ) {
        //
    }
}