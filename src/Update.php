<?php

namespace Laravel\Pulse;

use Closure;
use Laravel\Pulse\Concerns\SerializesClosures;

class Update
{
    use SerializesClosures;

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
