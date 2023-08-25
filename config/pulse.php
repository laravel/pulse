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

    // env variable to not run migrations on certain environments.

    // The name that will appear in the dashboard after running the `pulse:check` command.
    // This must be unique for each reporting server.
    'server_name' => env('PULSE_SERVER_NAME', gethostname()),

    'storage' => [
        'driver' => env('PULSE_STORAGE_DRIVER', 'database'),

        'database' => [
            'connection' => env('PULSE_DB_CONNECTION', null),
        ],
    ],

    'ingest' => [
        'driver' => env('PULSE_INGEST_DRIVER', 'storage'),

        // TODO this might conflict with sampling lottery / whatevers
        'lottery' => [1, 1000],

        'redis' => [
            'connection' => env('PULSE_REDIS_CONNECTION'),
        ],
    ],

    // TODO how does this play with "storage" and the conflicting key above.
    'retain' => Interval::days(7),

    // TODO: filter configuration?

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
