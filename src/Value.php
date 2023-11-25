<?php

namespace Laravel\Pulse;

use Closure;

class Value
{
    /**
     * Create a new Value instance.
     */
    public function __construct(
        public int $timestamp,
        public string $type,
        public Closure|string $key,
        public string $value,
    ) {
        //
    }

    /**
     * Resolve the entry for ingest.
     */
    public function resolve(): self
    {
        $this->key = value($this->key);

        return $this;
    }

    /**
     * Fetch the value attributes for persisting.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'timestamp' => $this->timestamp,
            'type' => $this->type,
            'key' => $this->key,
            'value' => $this->value,
        ];
    }
}
