<?php

namespace Laravel\Pulse\Structs;

class ResolvedUserStruct
{
    public function __construct(
        public string $name,
        public string $extra,
        public string $avatar,
    ) {
        //
    }
}