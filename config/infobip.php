<?php

return [
    'base_url' => env('INFOBIP_BASE_URL', 'https://69qmxz.api.infobip.com'),
    'api_key' => env('INFOBIP_API_KEY'),
    'sms_endpoint' => env('INFOBIP_SMS_ENDPOINT', '/sms/2/text/advanced'),
    'whatsapp_endpoint' => env('INFOBIP_WHATSAPP_ENDPOINT', '/whatsapp/1/message/text'),
    'email_endpoint' => env('INFOBIP_EMAIL_ENDPOINT', '/email/4/messages'),
    'sms_sender' => env('INFOBIP_SMS_SENDER'),
    'whatsapp_sender' => env('INFOBIP_WHATSAPP_SENDER'),
    'email_sender' => env('INFOBIP_EMAIL_SENDER', 'notificaciones@mkt.casabonita.pe'),
    'email_sender_name' => env('INFOBIP_EMAIL_SENDER_NAME', 'Casa Bonita Residencial'),
    'email_via_api' => env('INFOBIP_EMAIL_VIA_API', true),
    'wa_template_name' => env('INFOBIP_WA_TEMPLATE_NAME'),
    'wa_template_namespace' => env('INFOBIP_WA_TEMPLATE_NAMESPACE'),
    'wa_template_language' => env('INFOBIP_WA_TEMPLATE_LANGUAGE', 'es'),
    'notify_on_user_create' => env('INFOBIP_NOTIFY_ON_USER_CREATE', true),
    'notify_on_user_update_email' => env('INFOBIP_NOTIFY_ON_USER_UPDATE_EMAIL', true),
    'channels' => explode(',', env('INFOBIP_CHANNELS', 'email,sms,whatsapp')),
];
