<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'supabase' => [
        'url' => env('VITE_SUPABASE_URL'),
        'key' => env('VITE_SUPABASE_KEY'),
        'whatsapp_table' => env('WHATSAPP_SUPABASE_TABLE_PATTERN', 'globaltax_registro_whatsapp'),
    ],

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
    ],

    // Sin default para 'model'/'vision_model': el agente de WhatsApp no debe
    // arrancar con un modelo elegido a ciegas si falta configurarlo en .env.
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL'),
        'vision_model' => env('OPENAI_VISION_MODEL'),
        'timeout' => env('OPENAI_TIMEOUT', 30),
        'retries' => env('OPENAI_RETRIES', 3),
        'retry_backoff_ms' => env('OPENAI_RETRY_BACKOFF_MS', 500),
    ],

];
