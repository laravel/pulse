<?php

namespace Laravel\Pulse\Entries;

use Closure;

class Update
{
    /**
     * @param  array<string, mixed>  $conditions
     * @param  array<string, mixed>|(\Closure(array<string, mixed>): array<string, mixed>)  $attributes
     */
    public function __construct(
        public string $table,
        public array $conditions,
        public array|Closure $attributes
    ) {
        //
    }

    /**
     * Resolve the update for ingest and storage.
     */
    public function resolve(): self
    {
        return $this;
    }
}
