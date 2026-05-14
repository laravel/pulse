<?php

namespace Laravel\Pulse\Structs;

class CacheTotalStruct
{
    public function __construct(
        public int $hits,
        public int $misses,
    ) {
        //
    }
}