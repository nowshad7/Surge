<?php

declare(strict_types=1);

/*
 * Surge configuration.
 *
 * Publish with:  php artisan vendor:publish --tag=surge-config
 *
 * The app id/key/secret here must match the `pusher` connection in
 * config/broadcasting.php and the Echo config on the frontend. Point both at
 * this server's host/port and you have a drop-in, self-hosted Pusher.
 */

return [
    'host' => env('SURGE_HOST', '0.0.0.0'),
    'port' => (int) env('SURGE_PORT', 9019),

    'app' => [
        'id'     => env('SURGE_APP_ID', env('PUSHER_APP_ID', 'surge')),
        'key'    => env('SURGE_APP_KEY', env('PUSHER_APP_KEY', 'surge-key')),
        'secret' => env('SURGE_APP_SECRET', env('PUSHER_APP_SECRET', 'surge-secret')),
    ],

    // Seconds of inactivity before the server asks the client to ping.
    'activity_timeout' => (int) env('SURGE_ACTIVITY_TIMEOUT', 120),

    // Allow client-to-client (client-*) events on private/presence channels.
    'client_events' => (bool) env('SURGE_CLIENT_EVENTS', true),

    'limits' => [
        'max_connections'     => (int) env('SURGE_MAX_CONNECTIONS', 10000),
        'max_message_bytes'   => (int) env('SURGE_MAX_MESSAGE_BYTES', 10 * 1024 * 1024),
    ],

    // Optional Redis scaling for running Surge across multiple nodes.
    'scaling' => [
        'enabled' => (bool) env('SURGE_SCALING_ENABLED', false),
        'redis'   => [
            'host'   => env('SURGE_REDIS_HOST', '127.0.0.1'),
            'port'   => (int) env('SURGE_REDIS_PORT', 6379),
            'prefix' => env('SURGE_REDIS_PREFIX', 'surge'),
        ],
    ],
];
