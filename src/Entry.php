<?php

namespace Laravel\Pulse;

use Closure;

class Entry
{
    /**
     * @var list<'count'|'max'|'avg'>
     */
    protected array $aggregations = [];

    /**
     * Create a new Entry instance.
     */
    public function __construct(
        public int $timestamp,
        public string $type,
        public Closure|string $key,
        public ?int $value = null
    ) {
        //
    }

    /**
     * Create a new Entry instance.
     */
    public function make(
        int $timestamp,
        string $type,
        Closure|string $key,
        int $value = null
    ) {
        return new static($timestamp, $type, $key, $value);
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
     * Capture the count aggregate.
     */
    public function count(): static
    {
        $this->aggregations[] = 'count';

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
     * Determine whether the entry is marked for count aggregation.
     */
    public function isCount(): bool
    {
        return in_array('count', $this->aggregations);
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
     * Fetch the count aggregate attributes for persisting.
     *
     * @return array<string, mixed>
     */
    public function countAttributes(int $period): array
    {
        return [
            'bucket' => (int) floor($this->timestamp / $period) * $period,
            'period' => $period,
            'type' => $this->type.':count',
            'key' => $this->key,
            'value' => 1,
        ];
    }

    /**
     * Fetch the maximum aggregate attributes for persisting.
     *
     * @return array<string, mixed>
     */
    public function maxAttributes(int $period): array
    {
        return [
            'bucket' => (int) floor($this->timestamp / $period) * $period,
            'period' => $period,
            'type' => $this->type.':max',
            'key' => $this->key,
            'value' => $this->value,
        ];
    }

    /**
     * Fetch the average aggregate attributes for persisting.
     *
     * @return array<string, mixed>
     */
    public function avgAttributes(int $period): array
    {
        return [
            'bucket' => (int) floor($this->timestamp / $period) * $period,
            'period' => $period,
            'type' => $this->type.':avg',
            'key' => $this->key,
            'value' => $this->value,
            'count' => 1,
        ];
    }
}
