<?php

declare(strict_types=1);

use Illuminate\Support\Str;

return [
    'default' => env('DB_CONNECTION', 'pgsql'),

    'connections' => [
        /*
         * Read/write splitting: writes go to the primary, reads are served by the
         * streaming replica. This is the application-level expression of the
         * 100:1 read/write ratio in the requirements.
         */
        'pgsql' => [
            'driver' => 'pgsql',
            'read' => [
                'host' => [env('DB_READ_HOST', env('DB_HOST', 'postgres-replica'))],
            ],
            'write' => [
                'host' => [env('DB_HOST', 'postgres-primary')],
            ],
            'sticky' => true,
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'ticketarget'),
            'username' => env('DB_USERNAME', 'ticketarget'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],
    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    'redis' => [
        'client' => env('REDIS_CLIENT', 'predis'),
        'options' => [
            'prefix' => Str::slug(env('APP_NAME', 'ticketarget'), '_').'_event_',
        ],
        /*
         * Connects to the Redis master endpoint. The Sentinel cluster is deployed
         * for HA; to use Sentinel-aware master discovery, replace host/port with a
         * predis node list plus options.replication = 'sentinel'.
         */
        'cache' => [
            'host' => env('REDIS_HOST', 'redis-master'),
            'port' => 6379,
            'password' => env('REDIS_PASSWORD'),
            'database' => (int) env('REDIS_CACHE_DB', 0),
        ],
    ],
];
