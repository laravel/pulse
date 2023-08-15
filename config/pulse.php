<?php

use Carbon\CarbonInterval as Interval;
use Laravel\Pulse\Http\Middleware\Authorize;

return [

    'enabled' => env('PULSE_ENABLED', true),

    'path' => env('PULSE_PATH', 'pulse'),

    'middleware' => [
        'web',
        Authorize::class,
    ],

    // The name that will appear in the dashboard after running the `pulse:check` command.
    // This must be unique for each reporting server.
    'server_name' => env('PULSE_SERVER_NAME', gethostname()),

    'storage' => [
        'driver' => env('PULSE_STORAGE_DRIVER', 'database'),

        'database' => [
            'connection' => env('PULSE_DB_CONNECTION') ?? env('DB_CONNECTION') ?? 'mysql',
            // TODO can we just use this instead of caring about Redis time?
            // Then we can just use our time and this can be tweaked if needed
            // to adjust for out of time sync issues.
            'trim_after' => Interval::days(7),
        ],
    ],

    'ingest' => [
        'driver' => env('PULSE_INGEST_DRIVER', 'redis'),

        'storage' => [],

        'redis' => [
            'connection' => env('PULSE_REDIS_CONNECTION') ?? 'default',
            'trim_after' => Interval::days(7),
        ],
    ],

    // TODO: filter configuration?
    // TODO: trim lottery configuration
    // TODO: configure days of data to store? default: 7

    // in milliseconds
    'slow_endpoint_threshold' => 1000,

    // in milliseconds
    'slow_query_threshold' => 1000,

    // in milliseconds
    'slow_job_threshold' => 1000,

    // in milliseconds
    'slow_outgoing_request_threshold' => 1000,

    // queues to show stats for
    'queues' => [
        'default',
    ],

    // directories to monitor sizes for
    'directories' => [
        '/',
    ],

    // cache keys to monitor
    // regex_pattern => name
    'cache_keys' => [
        '^post:139$' => 'Post 139',
        '^server:1\d{2}$' => 'Servers 100 - 199',
        '^flight:.*' => 'All flights',
    ],

    // Options: "avg", "max"
    'graph_aggregation' => 'avg',

];
