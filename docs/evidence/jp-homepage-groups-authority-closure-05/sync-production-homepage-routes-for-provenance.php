<?php

/**
 * Sync production homepage trending/destination manifest into local jetpk profile for fare provenance UAT.
 * Run: php docs/evidence/jp-homepage-groups-authority-closure-05/sync-production-homepage-routes-for-provenance.php
 */
declare(strict_types=1);

use App\Enums\ClientPageSettingStatus;
use App\Models\ClientPageSetting;
use App\Models\ClientProfile;
use App\Support\Client\ClientPageKeys;

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$prodUrl = getenv('CLOSURE05_PROD_HOMEPAGE_API') ?: 'https://jetpakistan.pk/api/public/content/homepage';
$raw = file_get_contents($prodUrl);
if ($raw === false) {
    fwrite(STDERR, "FETCH_FAILED\n");
    exit(1);
}
$homepage = json_decode($raw, true);
if (! is_array($homepage)) {
    fwrite(STDERR, "INVALID_JSON\n");
    exit(1);
}

$profile = ClientProfile::query()->where('slug', 'jetpk')->first();
if ($profile === null) {
    fwrite(STDERR, "PROFILE_MISSING\n");
    exit(1);
}

foreach ([ClientPageSettingStatus::Draft, ClientPageSettingStatus::Published] as $status) {
    $setting = ClientPageSetting::query()->firstOrCreate(
        [
            'client_profile_id' => $profile->id,
            'page_key' => ClientPageKeys::HOME,
            'status' => $status,
        ],
        ['content_json' => []],
    );

    $content = is_array($setting->content_json) ? $setting->content_json : [];
    $content['routes'] = $homepage['routes'] ?? ['items' => []];
    $content['destinations'] = $homepage['destinations'] ?? ['items' => []];
    unset($content['_fare_cache']);
    $setting->content_json = $content;
    $setting->save();
}

echo json_encode([
    'ok' => true,
    'source' => $prodUrl,
    'routes' => count($homepage['routes']['items'] ?? []),
    'destinations' => count($homepage['destinations']['items'] ?? []),
], JSON_PRETTY_PRINT).PHP_EOL;
