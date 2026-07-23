<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the system monitoring subsystem.
    |
    */

    'enabled' => env('MONITORING_ENABLED', true),

    'broadcast' => [
        'channel' => env('MONITORING_CHANNEL', 'system-metrics'),
        'event' => env('MONITORING_EVENT', 'metrics.updated'),
    ],

    'collectors' => [
        'cpu' => [
            'enabled' => true,
            'interval' => 1,
        ],
        'memory' => [
            'enabled' => true,
            'interval' => 1,
        ],
        'disk' => [
            'enabled' => true,
            'interval' => 1,
        ],
        'network' => [
            'enabled' => true,
            'interval' => 1,
        ],
        'processes' => [
            'enabled' => true,
            'interval' => 2,
        ],
        'temperature' => [
            'enabled' => true,
            'interval' => 5,
        ],
        'gpu' => [
            'enabled' => true,
            'interval' => 5,
        ],
        'docker' => [
            'enabled' => true,
            'interval' => 10,
        ],
        'queue' => [
            'enabled' => true,
            'interval' => 5,
        ],
        'ollama' => [
            'enabled' => true,
            'interval' => 10,
            'host' => env('OLLAMA_HOST', 'http://localhost:11434'),
        ],
    ],

    'history' => [
        'enabled' => true,
        'max_points' => 300,
        'retention_seconds' => 3600,
    ],

    'alerts' => [
        'cpu_threshold' => 80,
        'memory_threshold' => 85,
        'disk_threshold' => 90,
        'temperature_threshold' => 80,
        'queue_failed_threshold' => 100,
    ],
];
