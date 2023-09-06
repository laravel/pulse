<?php

namespace Laravel\Pulse\Concerns;

use Closure;
use Laravel\SerializableClosure\SerializableClosure;

trait SerializesClosures
{
    /**
     * Prepare the instance values for serialization.
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        return array_map(function ($value) {
            return $value instanceof Closure ? new SerializableClosure($value) : $value;
        }, get_object_vars($this));
    }

    /**
     * Restore the instance values after serialization.
     *
     * @param  array<string, mixed>  $data
     */
    public function __unserialize(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->{$key} = $value instanceof SerializableClosure ? $value->getClosure() : $value;
        }
    }
}
