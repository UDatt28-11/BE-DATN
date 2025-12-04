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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID', env('GOOGLE_DRIVE_CLIENT_ID')),
        'client_secret' => env('GOOGLE_CLIENT_SECRET', env('GOOGLE_DRIVE_CLIENT_SECRET')),
        'redirect' => env('GOOGLE_REDIRECT_URI', 'http://localhost:8000/api/google/callback'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'payos' => [
        'client_id' => env('PAYOS_CLIENT_ID'),
        'api_key' => env('PAYOS_API_KEY'),
        'checksum_key' => env('PAYOS_CHECKSUM_KEY'),
        // Production: https://api-merchant.payos.vn (có thể cần /v2 tùy endpoint)
        // Development/Sandbox: https://api.payos.vn/v2 (nếu có)
        'base_url' => env('PAYOS_BASE_URL', 'https://api-merchant.payos.vn'),
    ],

];
