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

    'sms' => [
        'url' => env('SMS_GATEWAY_URL'),
        'key' => env('SMS_GATEWAY_API_KEY'),
        'sender' => env('SMS_SENDER', env('APP_NAME', 'Casa Bonita')),
    ],

    'infobip' => [
        'base_url' => env('INFOBIP_BASE_URL'), // ej: https://XXXX.api.infobip.com
        'api_key' => env('INFOBIP_API_KEY'),
        'sender' => env('INFOBIP_SENDER', env('APP_NAME', 'Casa Bonita')),
    ],

    /*
    |--------------------------------------------------------------------------
    | LOGICWARE CRM API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración para la integración con el API externa de LOGICWARE CRM.
    | Este servicio se utiliza para importar lotes desde el sistema externo.
    | La autenticación se realiza mediante API Key en headers (X-API-Key).
    |
    */
        'logicware' => [
        'base_url' => env('LOGICWARE_BASE_URL'),
        'api_key' => env('LOGICWARE_API_KEY'),
        'subdomain' => env('LOGICWARE_SUBDOMAIN', 'casabonita'),
        'timeout' => env('LOGICWARE_TIMEOUT', 30),
        'webhook_secret' => env('LOGICWARE_WEBHOOK_SECRET'), // Secret para validar webhooks
    ],

];
