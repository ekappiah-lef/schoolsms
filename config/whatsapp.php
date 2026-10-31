<?php

/*
 * WhatsApp Business: each school's own official number, connected through 360dialog (a Meta partner:
 * the school signs up through LEF's 360dialog link and gets an API key) or directly with Meta's Cloud API.
 *
 *   360dialog: WHATSAPP_PROVIDER=360dialog, WHATSAPP_API_KEY=<the school's 360dialog key>
 *   Meta:      WHATSAPP_PROVIDER=meta, WHATSAPP_TOKEN, WHATSAPP_PHONE_NUMBER_ID
 *
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
    'provider' => env('WHATSAPP_PROVIDER', 'meta'),   // meta | 360dialog
    'api_key' => env('WHATSAPP_API_KEY'),              // 360dialog
    'token' => env('WHATSAPP_TOKEN'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'template' => env('WHATSAPP_TEMPLATE', 'school_message'),
    'template_lang' => env('WHATSAPP_TEMPLATE_LANG', 'en'),
    'version' => env('WHATSAPP_API_VERSION', 'v21.0'),
];
