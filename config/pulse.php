<?php

use Carbon\CarbonInterval as Interval;
use Laravel\Pulse\Http\Middleware\Authorize;
use Laravel\Pulse\Recorders;

return [

    /*
    |--------------------------------------------------------------------------
    | Pulse Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Pulse will be accessible from. If the
    | setting is null, Pulse will reside under the same domain as the
    | application. Otherwise, this value will be used as the subdomain.
    |
    */

    'domain' => env('PULSE_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Pulse Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Pulse will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the paths of its internal API that aren't exposed to users.
    |
    */

    'path' => env('PULSE_PATH', 'pulse'),

    /*
    |--------------------------------------------------------------------------
    | Pulse Storage Driver
    |--------------------------------------------------------------------------
    |
    | This configuration options determines the storage driver that will
    | be used to store Pulse's data. In addition, you may set any
    | custom options as needed by the particular driver you choose.
    |
    */

    'storage' => [
        'driver' => env('PULSE_STORAGE_DRIVER', 'database'),

        'database' => [
            'connection' => env('PULSE_DB_CONNECTION', null),
            'chunk' => 1000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pulse Ingest Driver
    |--------------------------------------------------------------------------
    |
    | This configuration options determines the ingest driver that will
    | be used to capture Pulse's data. In addition, you may set any
    | custom options as needed by the particular driver you choose.
    |
    */

    'ingest' => [
        'driver' => env('PULSE_INGEST_DRIVER', 'storage'),

        'trim_lottery' => [1, 1_000],

        'redis' => [
            'connection' => env('PULSE_REDIS_CONNECTION'),
            'chunk' => 1000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pulse Data Retention
    |--------------------------------------------------------------------------
    |
    | Determines how long Pulse will retain data in both the ingest
    | and storage before removing old records.
    |
    */

    'retain' => Interval::days(7),

    /*
    |--------------------------------------------------------------------------
    | Pulse Master Switch
    |--------------------------------------------------------------------------
    |
    | This option may be used to disable all Pulse's watchers regardless
    | of their individual configuration, which simply provides a single
    | and convenient way to enable or disable Pulse data storage.
    |
    */

    'enabled' => env('PULSE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Pulse Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will be assigned to every Pulse route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    */

    'middleware' => [
        'web',
        Authorize::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pulse Recorders
    |--------------------------------------------------------------------------
    |
    | The following array lists the "recorders" that will be registered with
    | Pulse. The recorder gather the application's profile data when
    | a request or task is executed. Feel free to customize this list.
    |
    */

    'recorders' => [
        Recorders\CacheInteractions::class => [
            'groups' => [
                // '/^user:.\d+:(.*)/' => 'user:*:\1',
                // '/^user:.+$/' => 'user:*',
                '/(.*)/' => '\1',
            ],
        ],

        Recorders\Exceptions::class => [
            //
        ],

        Recorders\Jobs::class => [
            'threshold' => env('PULSE_SLOW_JOB_THRESHOLD', 1000),
        ],

        Recorders\OutgoingRequests::class => [
            'threshold' => env('PULSE_SLOW_OUTGOING_REQUEST_THRESHOLD', 1000),
            'groups' => [
                // '#^https://api.github.com/repos/.*$#' => 'api.github.com/repos/*',
                // '#^https?://([^/]*).*$#' => '\1',
                '/(.*)/' => '\1',
            ],
        ],

        Recorders\Requests::class => [
            'threshold' => env('PULSE_SLOW_ENDPOINT_THRESHOLD', 1000),
        ],

        Recorders\SlowQueries::class => [
            'threshold' => env('PULSE_SLOW_QUERY_THRESHOLD', 1000),
        ],

        Recorders\SystemStats::class => [
            'server_name' => env('PULSE_SERVER_NAME', gethostname()),
            'directories' => explode(':', env('PULSE_DIRECTORIES', '/')),
            'graph_aggregation' => 'avg', // Supported: "avg", "max"
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pulse Queue Monitoring
    |--------------------------------------------------------------------------
    |
    | The queues to show stats for.
    |
    */

    // TODO clean up this example after chatting with Jess.
    'queues' => [
        'default',
        // 'default-connection:queue-1',
        // 'default-connection:queue-2',
        // env('QUEUE_CONNECTION', 'sync') => [
        //     'default-connection:queue-3-via-array',
        // ],
        // 'specific-connection' => [
        //     'queue-a',
        //     'queue-1',
        // ],
        // 'connection-1' => [
        //     'queue-1',
        //     'queue-2'
        // ],
        // 'connection-2' => [
        //     'queue-1',
        //     'queue-2'
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pulse Sample Rate
    |--------------------------------------------------------------------------
    |
    | NOTE: This feature doesn't exist yet.
    | For metrics such as request counts, you may want to sample the data to reduce what is captured and stored.
    | The dashboard would scale the data to account for the sampling rate, potentially showing a "~" to indicate that it is an approximation.
    | The more traffic you receive, the lower you could set this number, without losing too much accuracy.
    | This setting should probably not be used for capturing problems like "slow" metrics, which already have a mechanism to reduce the data captured, as well as exceptions.
    |
    */

    'sample_rate' => env('PULSE_SAMPLE_RATE', 1),

    // ---
    // TODO:
    // - env variable to not run migrations on certain environments?
    // - filter configuration?
];
