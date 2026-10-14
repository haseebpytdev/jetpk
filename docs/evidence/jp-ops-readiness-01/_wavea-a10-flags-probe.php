<?php

$appRoot = '/home/pkjetp/jetpk_app';
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$flags = [
    'SABRE_BOOKING_ENABLED' => (bool) config('suppliers.sabre.booking_enabled'),
    'SABRE_BOOKING_LIVE_CALL_ENABLED' => (bool) config('suppliers.sabre.booking_live_call_enabled'),
    'SABRE_TICKETING_ENABLED' => (bool) config('suppliers.sabre.ticketing_enabled'),
    'SABRE_PUBLIC_TICKETING_ENABLED' => (bool) config('suppliers.sabre.public_ticketing_enabled'),
    'SABRE_CHECKOUT_AUTO_TICKETING_ENABLED' => (bool) config('suppliers.sabre.checkout_auto_ticketing_enabled'),
    'SABRE_VERIFIED_MULTISEG_AUTO_PNR_ENABLED' => (bool) config('suppliers.sabre.verified_multiseg_auto_pnr_enabled'),
    'AIRBLUE_OTA_V3_LIVE_ENABLED' => (bool) data_get(config('suppliers'), 'airblue.channels.ota_v3.live_enabled', false),
    'ONE_API_LIVE_BOOKING_ENABLED' => (bool) config('suppliers.one_api.live_booking_enabled'),
    'ALHAIDER_BOOKING_ENABLED' => (bool) config('suppliers.al_haider.booking_enabled'),
    'OTA_GROUP_REQUIRE_LIVE_PROVIDER_FOR_PUBLIC_RESULTS' => (bool) config('ota.group_ticketing.require_live_provider_for_public_results'),
    'OTA_GROUP_REQUIRE_LIVE_PROVIDER_FOR_RESERVATION' => (bool) config('ota.group_ticketing.require_live_provider_for_reservation'),
    'QUEUE_CONNECTION' => (string) config('queue.default'),
    'MAIL_NOTIFICATION_QUEUE' => (string) config('mail.notification_queue'),
    'FAILED_JOBS' => (int) Illuminate\Support\Facades\DB::table('failed_jobs')->count(),
    'PENDING_JOBS' => (int) Illuminate\Support\Facades\DB::table('jobs')->count(),
    'RUNTIME_SHA' => is_file($appRoot.'/.jetpk-runtime-sha') ? trim((string) file_get_contents($appRoot.'/.jetpk-runtime-sha')) : 'MISSING',
];

// Best-effort nested path for airblue — scan known keys without secrets
try {
    $sup = config('suppliers');
    $flags['SUPPLIERS_CONFIG_LOADED'] = is_array($sup);
} catch (Throwable $e) {
    $flags['SUPPLIERS_CONFIG_LOADED'] = false;
}

echo json_encode([
    'PROBED_AT_UTC' => gmdate('c'),
    'LIVE_MUTATION_FLAGS' => $flags,
    'NOTE' => 'Read-only config probe; no supplier/payment calls',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
