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

    /*
    |--------------------------------------------------------------------------
    | VNPAY Payment Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | VNPAY Sandbox Test Card:
    | - Bank: NCB
    | - Card Number: 9704198526191432198
    | - Card Holder: NGUYEN VAN A
    | - Expiry Date: 07/15
    | - OTP: 123456
    |
    */
    'vnpay' => [
        'tmn_code' => env('VNP_TMN_CODE', ''),
        'hash_secret' => env('VNP_HASH_SECRET', ''),
        'url' => env('VNP_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),
        'api_url' => env('VNP_API_URL', 'https://sandbox.vnpayment.vn/merchant_webapi/api/transaction'),
        'return_url' => env('VNP_RETURN_URL'),
        'version' => '2.1.0',
        'command' => 'pay',
        'curr_code' => 'VND',
        'locale' => 'vn',
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Chat Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for AI chat service (OpenAI, Claude, etc.)
    |
    */
    'ai' => [
        'provider' => env('AI_PROVIDER', 'openai'), // 'openai', 'claude', 'gemini'
        'api_key' => env('AI_API_KEY', ''),
        'model' => env('AI_MODEL', 'gpt-4o-mini'), // 'gpt-4o-mini', 'gpt-4o', 'claude-3-haiku', etc.
        'max_tokens' => env('AI_MAX_TOKENS', 1000),
        'temperature' => env('AI_TEMPERATURE', 0.7),
        'enabled' => env('AI_CHAT_ENABLED', true),
    ],

];
