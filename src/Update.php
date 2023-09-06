<?php

namespace Laravel\Pulse;

use Closure;
use Laravel\SerializableClosure\SerializableClosure;

class Update
{
    protected array|Closure|SerializableClosure $attributes;

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

    public function __sleep()
    {
        //
    }

    public function __wakeup()
    {
        ///
    }

    public function attributes()
    {
        //
    }
}
