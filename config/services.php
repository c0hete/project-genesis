<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Hub Personal Integration
    |--------------------------------------------------------------------------
    |
    | Configuration for reporting events to Hub Personal API.
    | Hub acts as the central event store for Supervisor to consume.
    |
    | @see C:\Users\JoseA\Projects\knowledge-base\supervisor\03_EVENT_MODEL.md
    |
    */

    'hub' => [
        'events_enabled' => env('HUB_EVENTS_ENABLED', false),
        'event_source' => env('HUB_EVENT_SOURCE', ''),
        'api_url' => env('HUB_API_URL', ''),
        'api_token' => env('HUB_API_TOKEN', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | Multi-gateway configuration.
    | Gateway selection is per-instance via PAYMENT_GATEWAY env var.
    |
    */

    'payment' => [
        'gateway' => env('PAYMENT_GATEWAY', 'stripe'),

        'webpay' => [
            'commerce_code' => env('WEBPAY_COMMERCE_CODE', ''),
            'api_key' => env('WEBPAY_API_KEY', ''),
            'environment' => env('WEBPAY_ENVIRONMENT', 'production'),
        ],

        'mercadopago' => [
            'public_key' => env('MERCADOPAGO_PUBLIC_KEY', ''),
            'access_token' => env('MERCADOPAGO_ACCESS_TOKEN', ''),
        ],

        'flow' => [
            'api_key' => env('FLOW_API_KEY', ''),
            'secret_key' => env('FLOW_SECRET_KEY', ''),
        ],

        'stripe' => [
            'key' => env('STRIPE_KEY', ''),
            'secret' => env('STRIPE_SECRET', ''),
        ],

        'paypal' => [
            'client_id' => env('PAYPAL_CLIENT_ID', ''),
            'secret' => env('PAYPAL_SECRET', ''),
            'mode' => env('PAYPAL_MODE', 'live'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Server Information
    |--------------------------------------------------------------------------
    |
    | Information about the server this instance runs on.
    | Used for reporting to Hub/Supervisor.
    |
    */

    'server' => [
        'id' => env('SERVER_ID', ''),
        'ip' => env('SERVER_IP', ''),
        'hostname' => env('SERVER_HOSTNAME', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    */

    'monitoring' => [
        'report_outdated_packages' => env('REPORT_OUTDATED_PACKAGES', true),
        'report_slow_queries' => env('REPORT_SLOW_QUERIES', true),
        'slow_query_threshold_ms' => env('SLOW_QUERY_THRESHOLD_MS', 1000),
    ],

];
