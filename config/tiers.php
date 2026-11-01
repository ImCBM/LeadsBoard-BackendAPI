<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Tier Configuration
    |--------------------------------------------------------------------------
    |
    | Defines rate limits, quotas, and allowed scopes for each account tier.
    | Keys inherit their owner's tier, or can have a tier directly assigned.
    | Custom per-key rate limits always override the tier default.
    |
    */

    'default' => 'free',

    'tiers' => [
        'guest' => [
            'name' => 'Guest',
            'rate_limit_per_minute' => 30,
            'daily_request_limit' => 500,
            'max_keys' => 1,
            'allowed_scopes' => ['leads:read', 'stats:read', 'tags:read'],
            'can_export' => false,
            'can_bulk_operations' => false,
        ],
        'free' => [
            'name' => 'Free Developer',
            'rate_limit_per_minute' => 60,
            'daily_request_limit' => 5000,
            'max_keys' => 3,
            'allowed_scopes' => ['leads:read', 'leads:write', 'leads:export', 'leads:import', 'tags:read', 'tags:write', 'stats:read'],
            'can_export' => true,
            'can_bulk_operations' => false,
        ],
        'pro' => [
            'name' => 'Pro / Growth',
            'rate_limit_per_minute' => 300,
            'daily_request_limit' => 50000,
            'max_keys' => 10,
            'allowed_scopes' => ['*'],
            'can_export' => true,
            'can_bulk_operations' => true,
        ],
        'enterprise' => [
            'name' => 'Enterprise',
            'rate_limit_per_minute' => 0, // unlimited
            'daily_request_limit' => 0,   // unlimited
            'max_keys' => 50,
            'allowed_scopes' => ['*'],
            'can_export' => true,
            'can_bulk_operations' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Trace Configuration
    |--------------------------------------------------------------------------
    */

    'traces' => [
        'enabled' => env('API_TRACES_ENABLED', true),
        'retention_days' => env('API_TRACES_RETENTION_DAYS', 30),
        'log_request_payload' => env('API_TRACES_LOG_PAYLOAD', false),
        'sanitize_fields' => [
            'password',
            'password_confirmation',
            'token',
            'secret',
            'api_key',
            'authorization',
        ],
    ],
];
