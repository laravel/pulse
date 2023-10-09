<?php

namespace Laravel\Pulse;

use Closure;

class Update
{
    use Concerns\SerializesClosures;

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
     * Resolve the update for ingest.
     */
    public function resolve(): self
    {
        return $this;
    }
}
