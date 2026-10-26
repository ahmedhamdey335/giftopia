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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'payments' => [
        'gateway' => env('PAYMENT_GATEWAY', env('APP_ENV') === 'production' ? 'paymob' : 'fake'),
        'fake_webhook_secret' => env('FAKE_PAYMENT_WEBHOOK_SECRET'),
        'paymob' => [
            'base_url' => env('PAYMOB_BASE_URL', 'https://accept.paymob.com'),
            'secret_key' => env('PAYMOB_SECRET_KEY'),
            'public_key' => env('PAYMOB_PUBLIC_KEY'),
            'hmac_secret' => env('PAYMOB_HMAC_SECRET'),
            'integration_id_card' => env('PAYMOB_INTEGRATION_ID_CARD'),
            'integration_id_wallet' => env('PAYMOB_INTEGRATION_ID_WALLET'),
        ],
    ],

];
