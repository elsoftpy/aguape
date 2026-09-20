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

    'ingesta' => [
        'token' => env('INGESTA_TOKEN'),
        'hmac_secret' => env('INGESTA_HMAC_SECRET'),
        'signature_tolerance_seconds' => env('INGESTA_SIGNATURE_TOLERANCE_SECONDS', 300),
    ],
    
     'recaptcha' => [
        'sitekey' => env('RECAPTCHA_SITE_KEY'),
        'secretkey' => env('RECAPTCHA_SECRET_KEY'),
    ],
];
