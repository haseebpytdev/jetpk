<?php

$brandName = (string) env('OTA_BRAND_NAME', env('APP_NAME', 'Travel'));

$canonicalDomain = (string) config('client.canonical_client.domain', 'jetpakistan.pk');
$canonicalEmail = (string) env('OTA_SUPPORT_EMAIL', 'support@jetpakistan.pk');

return [
    'product_name' => $brandName,
    'name' => $brandName,
    'domain' => env('OTA_BRAND_DOMAIN', $canonicalDomain),
    'tagline' => 'Book domestic and international flights with trusted travel support.',
    'support_phone' => '+92 300 7654321',
    'support_whatsapp' => '923007654321',
    'support_email' => $canonicalEmail,
    'company_note' => 'Flight booking support for individuals, families, and corporate travelers.',
    'homepage_headline' => 'Book Flights With Confidence',
    'homepage_subheadline' => 'Search routes, compare fares, and confirm your booking with dedicated support.',
    'flight_search_note' => 'Fares and availability are subject to airline confirmation at booking time.',
];
