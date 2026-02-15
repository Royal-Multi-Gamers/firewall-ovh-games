<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OVH API Configuration
    |--------------------------------------------------------------------------
    |
    | These values are used to connect to the OVH API. You can configure
    | them through the admin panel settings page.
    |
    */

    'ovh' => [
        'application_key' => env('OVH_APPLICATION_KEY', ''),
        'application_secret' => env('OVH_APPLICATION_SECRET', ''),
        'endpoint' => env('OVH_ENDPOINT', 'ovh-eu'),
        'consumer_key' => env('OVH_CONSUMER_KEY', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how the firewall rules synchronization should work.
    |
    */

    'sync' => [
        // Enable automatic synchronization every 5 minutes
        'enabled' => env('OVH_FIREWALL_SYNC_ENABLED', true),

        // Interval in minutes for automatic synchronization
        'interval' => env('OVH_FIREWALL_SYNC_INTERVAL', 5),

        // Enable sync on allocation events (create, update, delete)
        'sync_on_events' => env('OVH_FIREWALL_SYNC_ON_EVENTS', true),

        // Use queue for sync operations (recommended for production)
        'use_queue' => env('OVH_FIREWALL_USE_QUEUE', true),

        // Queue name for sync operations
        'queue_name' => env('OVH_FIREWALL_QUEUE_NAME', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Firewall Rule Configuration
    |--------------------------------------------------------------------------
    |
    | Default configuration for firewall rules.
    |
    */

    'rules' => [
        // Default protocol for firewall rules
        // Options: 'arkSurvivalEvolved', 'arma', 'gtaMultiTheftAutoSanAndreas', 'gtaSanAndreasMultiplayerMod',
        // 'hl2Source', 'minecraftPocketEdition', 'minecraftQuery', 'mumble', 'other', 'rust', 'teamspeak2',
        // 'teamspeak3', 'trackmaniaShootmania'
        'default_protocol' => env('OVH_FIREWALL_DEFAULT_PROTOCOL', 'other'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how sync operations should be logged.
    |
    */

    'logging' => [
        // Enable detailed logging of sync operations
        'enabled' => env('OVH_FIREWALL_LOGGING_ENABLED', false),

        // Number of days to keep sync logs
        'retention_days' => env('OVH_FIREWALL_LOG_RETENTION_DAYS', 30),
    ],
];
