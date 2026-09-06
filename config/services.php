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

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
    ],

    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'shared_secret' => env('APPLE_SHARED_SECRET'),
        'iap' => [
            'issuer_id' => env('APPLE_IAP_ISSUER_ID'),
            'key_id' => env('APPLE_IAP_KEY_ID'),
            'bundle_id' => env('APPLE_IAP_BUNDLE_ID'),
            'app_apple_id' => env('APPLE_IAP_APPLE_ID'),
            'private_key_path' => env('APPLE_IAP_PRIVATE_KEY_PATH'),
            'root_ca_paths' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('APPLE_IAP_ROOT_CA_PATHS', ''))
            ))),
        ],
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
    ],

    'fcm' => [
        'credentials_path' => env('FIREBASE_CREDENTIALS_PATH'),
    ],

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'from' => env('TWILIO_FROM_NUMBER'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

];
