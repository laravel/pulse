<?php

namespace Laravel\Pulse\Structs;

class UsageStruct
{
    public function __construct(
        public string $key,
        public ResolvedUserStruct $user,
        public int $count,
    ) {
        //
    }
}