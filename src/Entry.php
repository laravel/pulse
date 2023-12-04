<?php

namespace Laravel\Pulse;

class Entry
{
    /**
     * The aggregations to perform on the entry.
     *
     * @var list<'count'|'max'|'avg'>
     */
    protected array $aggregations = [];

    /**
     * Whether to only save aggregate bucket data for the entry.
     */
    protected bool $onlyBuckets = false;

    /**
     * Create a new Entry instance.
     */
    public function __construct(
        public int $timestamp,
        public string $type,
        public string $key,
        public ?int $value = null,
    ) {
        //
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
     * Only save aggregate bucket data for the entry.
     */
    public function onlyBuckets(): static
    {
        $this->onlyBuckets = true;

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
     * Determine whether to only save aggregate bucket data for the entry.
     */
    public function isOnlyBuckets(): bool
    {
        return $this->onlyBuckets;
    }

    /**
     * Fetch the entry attributes for persisting.
     *
     * @return array{timestamp: int, type: string, key: string, value: ?int}
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
