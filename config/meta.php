<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Meta WhatsApp Business API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración para la API de WhatsApp Business de Meta (Facebook)
    | https://developers.facebook.com/docs/whatsapp
    |
    */

    'whatsapp_base_url' => env('META_WHATSAPP_BASE_URL', 'https://graph.facebook.com'),
    
    'whatsapp_access_token' => env('META_WHATSAPP_ACCESS_TOKEN'),
    
    'whatsapp_phone_number_id' => env('META_WHATSAPP_PHONE_NUMBER_ID'),
    
    'whatsapp_business_account_id' => env('META_WHATSAPP_BUSINESS_ACCOUNT_ID'),
    
    'whatsapp_verify_token' => env('META_WHATSAPP_VERIFY_TOKEN'),
    
    'whatsapp_webhook_secret' => env('META_WHATSAPP_WEBHOOK_SECRET'),
    
    // Preferir Meta sobre Clicklab (true = intentar Meta primero, false = solo Clicklab)
    'whatsapp_prefer_meta' => env('META_WHATSAPP_PREFER', true),
];
