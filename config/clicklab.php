<?php

return [
    'provider' => env('CLICKLAB_PROVIDER', 'infobip'),
    'base_url' => env('CLICKLAB_BASE_URL', env('INFOBIP_BASE_URL', 'https://api.clicklab.pe')),
    'api_key' => env('CLICKLAB_API_KEY', env('INFOBIP_API_KEY')),
    'sms_endpoint' => env('CLICKLAB_SMS_ENDPOINT', '/sms/2/text/advanced'),
    'whatsapp_endpoint' => env('CLICKLAB_WHATSAPP_ENDPOINT', '/whatsapp/1/message/text'),
    'email_endpoint' => env('CLICKLAB_EMAIL_ENDPOINT', '/email/4/messages'),
    'sms_sender' => env('CLICKLAB_SMS_SENDER'),
    'whatsapp_sender' => env('CLICKLAB_WHATSAPP_SENDER'),
    'email_sender' => env('CLICKLAB_EMAIL_SENDER'),
    'email_sender_name' => env('CLICKLAB_EMAIL_SENDER_NAME'),
    'email_via_api' => env('CLICKLAB_EMAIL_VIA_API', false),
    'wa_template_name' => env('CLICKLAB_WA_TEMPLATE_NAME'),
    'wa_template_namespace' => env('CLICKLAB_WA_TEMPLATE_NAMESPACE'),
    'wa_template_language' => env('CLICKLAB_WA_TEMPLATE_LANGUAGE', 'es'),
    'notify_on_user_create' => env('CLICKLAB_NOTIFY_ON_USER_CREATE', true),
    'notify_on_user_update_email' => env('CLICKLAB_NOTIFY_ON_USER_UPDATE_EMAIL', true),
    'channels' => explode(',', env('CLICKLAB_CHANNELS', 'email,whatsapp,sms')),
];
