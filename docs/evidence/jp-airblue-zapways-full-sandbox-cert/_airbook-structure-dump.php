<?php
/** Structure-only dump of AirBook RQ (tag/attr names, no secret values). */
declare(strict_types=1);
$appRoot = '/home/pkjetp/jetpk_app';
chdir($appRoot);
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\AirBlueConfigResolver;
use App\Services\Suppliers\AirBlue\AirBlueOtaXmlBuilder;
use App\Services\Suppliers\AirBlue\AirBluePassengerPayloadBuilder;

$booking = Booking::query()->with(['passengers', 'contact'])->find(41);
$conn = SupplierConnection::find(22);
$config = app(AirBlueConfigResolver::class)->resolveOta($conn);
echo 'SERVICE_TARGET='.($config['service_target'] ?? '').PHP_EOL;
echo 'SERVICE_VERSION='.($config['service_version'] ?? '').PHP_EOL;
echo 'IS_TEST='.(!empty($config['is_test'])?'YES':'NO').PHP_EOL;
echo 'ENDPOINT_HOST='.(parse_url((string)($config['endpoint_url']??''), PHP_URL_HOST)?:'').PHP_EOL;

$meta = is_array($booking->meta) ? $booking->meta : [];
$ctx = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
$xml = app(AirBlueOtaXmlBuilder::class)->buildAirBookRequest(
    $config,
    $ctx,
    app(AirBluePassengerPayloadBuilder::class)->buildPassengersFromBooking($booking),
    app(AirBluePassengerPayloadBuilder::class)->buildContactFromBooking($booking),
);

// Redact attribute values and text nodes
$red = preg_replace('/="[^"]*"/', '="…"', $xml) ?? $xml;
$red = preg_replace('/>([^<]{1,})</', '>…<', $red) ?? $red;
// Keep only a compact outline
$lines = preg_split('/\r?\n/', $red) ?: [];
foreach ($lines as $line) {
    $t = trim($line);
    if ($t === '') continue;
    echo $t.PHP_EOL;
}
