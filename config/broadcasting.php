<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | This option controls the default broadcaster that will be used by the
    | framework when an event needs to be broadcast. You may set this to
    | any of the connections defined in the "connections" array below.
    |
    | Supported: "reverb", "pusher", "ably", "redis", "log", "null"
    |
    */

    'default' => env('BROADCAST_CONNECTION', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the broadcast connections that will be used
    | to broadcast events to other systems or over WebSockets. Samples of
    | each available type of connection are provided inside this array.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Client Connection Settings
    |--------------------------------------------------------------------------
    |
    | What the browser needs to reach the websocket server, which is not always
    | where this application publishes to; see the reverb connection below.
    |
    | The layout renders
    | these into the page at runtime and resources/js/echo.js reads them there,
    | rather than Vite baking them into the bundle at build time: demgem is a
    | self-hosted application, and one built image has to serve any host.
    |
    | With no key the page builds no Echo instance at all, so an instance that
    | runs without Reverb keeps working, minus the live updates.
    |
    */

    'client' => [
        'key' => env('REVERB_APP_KEY'),
        'host' => env('REVERB_HOST', 'localhost'),
        'port' => (int) env('REVERB_PORT', 8080),
        'scheme' => env('REVERB_SCHEME', 'http'),
    ],

    'connections' => [

        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            /*
             * Where this application publishes to, which is not always where a
             * browser connects. On one machine they are the same address and the
             * REVERB_* fallbacks below are all anyone needs.
             *
             * In Docker they differ, and only one of them can be REVERB_HOST: the
             * browser reaches a port published on the host, and the app and the queue
             * worker reach the container by name on the network's own port. Pointing
             * both at "localhost" makes the worker call itself, which fails with
             * cURL error 7 and, thanks to ShouldRescue, fails silently.
             */
            'options' => [
                'host' => env('REVERB_PUBLISH_HOST', env('REVERB_HOST', 'localhost')),
                'port' => env('REVERB_PUBLISH_PORT', env('REVERB_PORT', 443)),
                'scheme' => env('REVERB_PUBLISH_SCHEME', env('REVERB_SCHEME', 'https')),
                'useTLS' => env('REVERB_PUBLISH_SCHEME', env('REVERB_SCHEME', 'https')) === 'https',
            ],
            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
            ],
        ],

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'host' => env('PUSHER_HOST') ?: 'api-'.env('PUSHER_APP_CLUSTER', 'mt1').'.pusher.com',
                'port' => env('PUSHER_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'encrypted' => true,
                'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
            ],
            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
            ],
        ],

        'ably' => [
            'driver' => 'ably',
            'key' => env('ABLY_KEY'),
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
