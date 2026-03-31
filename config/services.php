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

    'instagram' => [
        'client_id' => env('INSTAGRAM_CLIENT_ID'),
        'client_secret' => env('INSTAGRAM_CLIENT_SECRET'),
        'authorize_url' => env('INSTAGRAM_AUTHORIZE_URL', 'https://api.instagram.com/oauth/authorize'),
        'token_url' => env('INSTAGRAM_TOKEN_URL', 'https://api.instagram.com/oauth/access_token'),
        'graph_url' => env('INSTAGRAM_GRAPH_URL', 'https://graph.instagram.com'),
        'scopes' => array_values(array_filter(array_map('trim', explode(',', (string) env('INSTAGRAM_SCOPES', 'user_profile,user_media'))))),
        'webhook_verify_token' => env('INSTAGRAM_WEBHOOK_VERIFY_TOKEN'),
        'webhook_app_secret' => env('INSTAGRAM_WEBHOOK_APP_SECRET'),
        'use_mock' => (bool) env('INSTAGRAM_USE_MOCK', false),
    ],

];
