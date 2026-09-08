<?php

/**
 * One-shot local fixtures for Closure-05 CMS browser UAT (non-production).
 * Run: php docs/evidence/jp-homepage-groups-authority-closure-05/seed-local-uat-fixtures.php
 */
declare(strict_types=1);

use App\Enums\ClientPageSettingStatus;
use App\Models\Agency;
use App\Models\Airport;
use App\Models\ClientPageSetting;
use App\Models\ClientProfile;
use App\Models\ClientProfileModule;
use App\Models\GroupInventory;
use App\Support\Client\ClientPageKeys;
use App\Support\Client\ClientProfileConfigReader;

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (['KHI', 'LHE', 'ISB', 'DXB', 'JED', 'SHJ', 'PEW'] as $code) {
    Airport::query()->firstOrCreate(
        ['iata_code' => $code],
        [
            'name' => $code.' Airport',
            'city' => $code,
            'country' => 'PK',
            'country_code' => 'PK',
            'is_active' => true,
            'is_commercial' => true,
        ],
    );
}

config(['ota.default_agency_slug' => 'jetpk-agency']);
Agency::query()->firstOrCreate(
    ['slug' => 'jetpk-agency'],
    ['name' => 'JetPK Agency', 'timezone' => 'Asia/Karachi', 'is_active' => true],
);

$profile = ClientProfile::query()->firstOrCreate(
    ['slug' => 'jetpk'],
    [
        'name' => 'Jet Pakistan',
        'environment' => 'staging',
        'active_frontend_theme' => 'jetpakistan',
        'active_admin_theme' => 'jetpakistan',
        'active_staff_theme' => 'jetpakistan',
        'asset_profile' => 'jetpk-assets',
        'default_locale' => 'en',
        'timezone' => 'Asia/Karachi',
        'currency' => 'PKR',
        'is_master_profile' => false,
        'is_active' => true,
    ],
);

foreach (ClientProfileConfigReader::MODULE_KEYS as $moduleKey) {
    ClientProfileModule::query()->firstOrCreate(
        ['client_profile_id' => $profile->id, 'module_key' => $moduleKey],
        ['enabled' => true],
    );
}

$inventory = GroupInventory::query()->updateOrCreate(
    ['public_id' => 'CLOSURE05-UAT-1'],
    [
        'supplier' => 'alhaider',
        'supplier_package_id' => 'CLOSURE05-UAT-1',
        'title' => 'Closure-05 UAT ISB-DXB',
        'sector' => 'ISB-DXB',
        'airline_name' => 'Air Arabia',
        'departure_date' => now()->addDays(21)->toDateString(),
        'total_seats' => 12,
        'held_seats' => 0,
        'sold_seats' => 0,
        'price' => 89000,
        'currency' => 'PKR',
        'is_active' => true,
    ],
);

$baseContent = [
    'featured_deals' => [
        'enabled' => '1',
        'eyebrow' => 'Live fares',
        'title' => 'Featured deals',
        'items' => [[
            'id' => 'closure05-uat-slot',
            'from' => 'ISB',
            'to' => 'DXB',
            'airline' => 'Air Arabia',
            'enabled' => '1',
            'sort_order' => 0,
        ]],
    ],
    'support_cta' => [
        'enabled' => '1',
        'title' => 'Need help?',
        'subtitle' => 'Closure-05 UAT support',
        'call_enabled' => '1',
        'chat_enabled' => '1',
        'chat_url' => '/support',
    ],
];

foreach ([ClientPageSettingStatus::Draft, ClientPageSettingStatus::Published] as $status) {
    ClientPageSetting::query()->updateOrCreate(
        [
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::HOME,
            'status' => $status,
        ],
        ['content_json' => $baseContent],
    );
}

echo json_encode([
    'profile_id' => $profile->id,
    'inventory_id' => $inventory->id,
    'public_id' => $inventory->public_id,
    'price' => $inventory->price,
], JSON_PRETTY_PRINT).PHP_EOL;
