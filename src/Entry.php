<?php

namespace Laravel\Pulse;

use Closure;

class Entry
{
    /**
     * @var list<'sum'|'max'|'avg'>
     */
    protected array $aggregations = [];

    /**
     * Create a new Entry instance.
     */
    public function __construct(
        public int $timestamp,
        public string $type,
        public Closure|string $key,
        public int $value = 1,
    ) {
        //
    }

    /**
     * Create a new Entry instance.
     */
    public static function make(
        int $timestamp,
        string $type,
        Closure|string $key,
        int $value = null
    ): self {
        return new self($timestamp, $type, $key, $value);
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
     * Capture the sum aggregate.
     */
    public function sum(): static
    {
        $this->aggregations[] = 'sum';

        return $this;
    }

    /**
     * Capture the maximum aggregate.
     */
    public function max(): static
    {
        $this->aggregations[] = 'max';

        return $this;
    }

    /**
     * Capture the average aggregate.
     */
    public function avg(): static
    {
        $this->aggregations[] = 'avg';

        return $this;
    }

    /**
     * Determine whether the entry is marked for sum aggregation.
     */
    public function isSum(): bool
    {
        return in_array('sum', $this->aggregations);
    }

    /**
     * Determine whether the entry is marked for maximum aggregation.
     */
    public function isMax(): bool
    {
        return in_array('max', $this->aggregations);
    }

    /**
     * Determine whether the entry is marked for average aggregation.
     */
    public function isAvg(): bool
    {
        return in_array('avg', $this->aggregations);
    }

    /**
     * Fetch the entry attributes for persisting.
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

    /**
     * Fetch the aggregate attributes for persisting.
     *
     * @return array<string, mixed>
     */
    public function aggregateAttributes(int $period, string $suffix): array
    {
        return [
            'bucket' => (int) floor($this->timestamp / $period) * $period,
            'period' => $period,
            'type' => $this->type.':'.$suffix,
            'key' => $this->key,
            'value' => $this->value,
            'count' => 1,
        ];
    }
}
