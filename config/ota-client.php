<<<<<<< HEAD
<?php
=======
﻿<?php
>>>>>>> jetpk/main

$brandName = (string) env('OTA_BRAND_NAME', env('APP_NAME', 'Travel'));

return [
    'agency_name' => $brandName,
    'agency_tagline' => 'Flights, support, and travel assistance you can rely on.',
    'logo_text' => $brandName,
    'primary_color' => '#0c4a6e',
    'support_phone' => '+92 300 4455667',
    'support_whatsapp' => '923004455667',
    'support_email' => 'support@haseebasif.com',
    'office_city' => 'Karachi',
    'powered_by' => $brandName,
<<<<<<< HEAD
    'domain_preview' => 'ota.haseebasif.com',
=======
    'domain_preview' => env('OTA_CLIENT_PREVIEW_DOMAIN', 'jetpakistan.pk'),
>>>>>>> jetpk/main
    'footer_text' => 'We help you plan and book flights with dedicated support.',
    'social_facebook' => 'https://facebook.com/example',
    'social_linkedin' => 'https://linkedin.com/company/example',
    'social_instagram' => 'https://instagram.com/example',
];
<<<<<<< HEAD
=======

>>>>>>> jetpk/main
