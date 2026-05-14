<?php

namespace Laravel\Pulse\Structs;

class CacheStruct
{
    public function __construct(
        public ?string $key,
        public int $hits,
        public int $misses,
    ) {
        //
    }
}