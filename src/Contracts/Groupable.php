<?php

namespace Laravel\Pulse\Contracts;

interface Groupable
{
    /**
     * The grouped value.
     */
    public function group(string $value): string;
}
