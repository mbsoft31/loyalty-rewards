<?php

return [
    'database' => [
        // Supported: pgsql, mysql, sqlite
        'driver' => env('LOYALTY_DB_DRIVER', 'pgsql'),
        'host' => env('LOYALTY_DB_HOST', '127.0.0.1'),
        'port' => env('LOYALTY_DB_PORT', 5432),
        'database' => env('LOYALTY_DB_DATABASE', 'loyalty_rewards'),
        'username' => env('LOYALTY_DB_USERNAME', 'app'),
        'password' => env('LOYALTY_DB_PASSWORD', ''),
        'options' => [
            // PDO options can be added here
        ],
    ],

    // Optional: allow defining initial rules in config (consumed by your app bootstrapping)
    'rules' => [
        'earning' => [
            // ['type' => 'category_multiplier', 'category' => 'electronics', 'multiplier' => 2.0]
        ],
        'redemption' => [
            // ['type' => 'basic', 'points_per_dollar' => 100, 'min_points' => 200]
        ],
    ],
];

