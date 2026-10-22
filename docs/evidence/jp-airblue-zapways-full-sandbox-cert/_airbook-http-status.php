<?php
declare(strict_types=1);
$appRoot = '/home/pkjetp/jetpk_app';
chdir($appRoot);
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\AirBlueClient;
use App\Services\Suppliers\AirBlue\AirBlueConfigResolver;
use App\Services\Suppliers\AirBlue\AirBlueOtaXmlBuilder;
use App\Services\Suppliers\AirBlue\AirBluePassengerPayloadBuilder;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueException;

$booking = Booking::query()->with(['passengers', 'contact'])->find(41);
// Prefer newest sandbox cert booking if 41 incomplete
$latest = Booking::query()->where('source_channel', 'sandbox_cert')->orderByDesc('id')->first();
if ($latest) {
    $booking = Booking::query()->with(['passengers', 'contact'])->find($latest->id);
}
$conn = SupplierConnection::find(22);
echo 'BOOKING_ID='.$booking->id.PHP_EOL;
echo 'HOST='.(parse_url((string)$conn->base_url, PHP_URL_HOST) ?: '').PHP_EOL;
echo 'EXT='.(filter_var(env('AIRBLUE_EXTERNAL_CALLS_ENABLED', false), FILTER_VALIDATE_BOOLEAN) ? 'YES' : 'NO').PHP_EOL;

$resolver = app(AirBlueConfigResolver::class);
$builder = app(AirBlueOtaXmlBuilder::class);
$paxBuilder = app(AirBluePassengerPayloadBuilder::class);
$client = app(AirBlueClient::class);

$meta = is_array($booking->meta) ? $booking->meta : [];
$ctx = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
$config = $resolver->resolveOta($conn);
$passengers = $paxBuilder->buildPassengersFromBooking($booking);
$contact = $paxBuilder->buildContactFromBooking($booking);
$xml = $builder->buildAirBookRequest($config, $ctx, $passengers, $contact);
echo 'XML_BYTES='.strlen($xml).PHP_EOL;
echo 'XML_HAS_PASSENGER='.(str_contains($xml, 'TestQA') || str_contains($xml, 'AIRBLUEA') || str_contains($xml, 'Traveler') ? 'YES' : 'NO').PHP_EOL;
echo 'XML_HAS_ITIN='.(str_contains($xml, 'ISB') && str_contains($xml, 'KHI') ? 'YES' : 'NO').PHP_EOL;
// Do not print XML

try {
    $resp = $client->callOta($conn, 'air_book', $xml, [
        'request_context' => 'air_book_diag',
        'booking_id' => $booking->id,
    ]);
    echo 'CALL_OK=YES'.PHP_EOL;
    echo 'PARSED_KEYS='.implode(',', array_keys($resp)).PHP_EOL;
} catch (AirBlueException $e) {
    echo 'CALL_OK=NO'.PHP_EOL;
    echo 'ERROR_CODE='.$e->normalizedCode.PHP_EOL;
    echo 'HTTP_STATUS='.(string) ($e->httpStatus ?? ($e->context['http_status'] ?? 'n/a')).PHP_EOL;
    echo 'SAFE_MSG='.substr($e->safeMessage, 0, 120).PHP_EOL;
    $ctxKeys = array_keys(is_array($e->context) ? $e->context : []);
    echo 'CTX_KEYS='.implode(',', $ctxKeys).PHP_EOL;
}
