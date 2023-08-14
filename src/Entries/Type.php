<?php

namespace Laravel\Pulse\Entries;

use Illuminate\Support\Collection;

enum Type: string
{
    case CacheHit = 'pulse_cache_hits';
    case Exception = 'pulse_exceptions';
    case Job = 'pulse_jobs';
    case OutgoingRequest = 'pulse_outgoing_requests';
    case Query = 'pulse_queries';
    case QueueSize = 'pulse_queue_sizes';
    case Request = 'pulse_requests';
    case Server = 'pulse_servers';

    /**
     * Get all cases as a Collection.
     *
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function all(): Collection
    {
        return collect(self::cases());
    }
}
