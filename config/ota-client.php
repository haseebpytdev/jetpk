<?php

$brandName = (string) env('OTA_BRAND_NAME', env('APP_NAME', 'Travel'));

$canonicalDomain = (string) config('client.canonical_client.domain', 'jetpakistan.pk');
$canonicalEmail = (string) env('OTA_SUPPORT_EMAIL', 'ota@jetpakistan.pk');

return [
    'agency_name' => $brandName,
    'agency_tagline' => 'Flights, support, and travel assistance you can rely on.',
    'logo_text' => $brandName,
    'primary_color' => '#0c4a6e',
    'support_phone' => '+92 300 4455667',
    'support_whatsapp' => '923004455667',
    'support_email' => $canonicalEmail,
    'office_city' => 'Karachi',
    'powered_by' => $brandName,
    'domain_preview' => $canonicalDomain,
    'footer_text' => 'We help you plan and book flights with dedicated support.',
    'social_facebook' => 'https://facebook.com/example',
    'social_linkedin' => 'https://linkedin.com/company/example',
    'social_instagram' => 'https://instagram.com/example',
];
