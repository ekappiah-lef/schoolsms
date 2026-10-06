<?php

/*
 * WhatsApp Business (Meta Cloud API). Messages go out from the school's verified WhatsApp
 * Business number, under its approved display name (e.g. "LEF School").
 *
 * Business-initiated messages must use a message template approved by Meta. Create one in
 * WhatsApp Manager, e.g. name "school_message", category Utility, language English, body:
 *     Message from LEF School: {{1}}
 * The app fills {{1}} with the message text.
 *
 *   WHATSAPP_ENABLED=true
 *   WHATSAPP_TOKEN=...            permanent System User access token
 *   WHATSAPP_PHONE_NUMBER_ID=...  from WhatsApp Manager > Phone numbers
 *   WHATSAPP_TEMPLATE=school_message
 *   WHATSAPP_TEMPLATE_LANG=en
 */
return [
    'enabled' => (bool) env('WHATSAPP_ENABLED', false),
    'token' => env('WHATSAPP_TOKEN'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'template' => env('WHATSAPP_TEMPLATE', 'school_message'),
    'template_lang' => env('WHATSAPP_TEMPLATE_LANG', 'en'),
    'version' => env('WHATSAPP_API_VERSION', 'v21.0'),
];
