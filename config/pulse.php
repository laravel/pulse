<?php

return [

    'path' => env('PULSE_PATH', 'pulse'),

    'middleware' => [
        'web',
    ],

    // The name that will appear in the dashboard after running the `pulse:check` command.
    // This must be unique for each reporting server.
    'server_name' => env('PULSE_SERVER_NAME', gethostname()),

    // in milliseconds
    'slow_endpoint_threshold' => 3000,

    // in milliseconds
    'slow_query_threshold' => 1000,

    // queues to show stats for
    'queues' => [
        'default',
    ],

    // directories to monitor sizes for
    'directories' => [
        '/',
    ],

];
