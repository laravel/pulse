<?php

namespace Laravel\Pulse\Structs;

class StorageDirectoryStruct
{
    public function __construct(
        public string $directory,
        public int $total,
        public int $used,
    ) {
        //
    }
}