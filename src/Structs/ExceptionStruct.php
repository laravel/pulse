<?php

namespace Laravel\Pulse\Structs;

use Carbon\CarbonInterface;

class ExceptionStruct
{
    /**
     * @param  class-string  $class
     */
    public function __construct(
        public string $class,
        public ?string $location,
        public CarbonInterface $latest,
        public int $count,
    ) {
        //
    }
}