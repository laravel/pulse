<?php

namespace Laravel\Pulse;

class Value
{
    /**
     * Create a new Value instance.
     */
    public function __construct(
        public int $timestamp,
        public string $type,
        public string $key,
        public string $value,
    ) {
        //
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
