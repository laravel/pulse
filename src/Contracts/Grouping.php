<?php

namespace Laravel\Pulse\Contracts;

use Closure;

interface Grouping
{
    /**
     * Return a closure that groups the given value.
     *
     * @return Closure(): string
     */
    public function group(string $value): Closure;

    /**
     * Return the column that grouping should be applied to.
     */
    public function groupColumn(): string;
}
