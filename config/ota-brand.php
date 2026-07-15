<?php

$brandName = (string) env('OTA_BRAND_NAME', env('APP_NAME', 'Travel'));

return [
    'product_name' => $brandName,
    'name' => $brandName,
    'domain' => env('OTA_BRAND_DOMAIN', 'ota.haseebasif.com'),
    'tagline' => 'Book domestic and international flights with trusted travel support.',
    'support_phone' => '+92 300 7654321',
    'support_whatsapp' => '923007654321',
    'support_email' => 'support@haseebasif.com',
    'company_note' => 'Flight booking support for individuals, families, and corporate travelers.',
    'homepage_headline' => 'Book Flights With Confidence',
    'homepage_subheadline' => 'Search routes, compare fares, and confirm your booking with dedicated support.',
    'flight_search_note' => 'Fares and availability are subject to airline confirmation at booking time.',
];
