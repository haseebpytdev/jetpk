<?php
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
use Illuminate\Support\Facades\Http;

$booking = Booking::query()->with(['passengers', 'contact'])->find(41);
$conn = SupplierConnection::find(22);
$config = app(AirBlueConfigResolver::class)->resolveOta($conn);
$builder = app(AirBlueOtaXmlBuilder::class);
$paxBuilder = app(AirBluePassengerPayloadBuilder::class);
$meta = is_array($booking->meta) ? $booking->meta : [];
$ctx = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
$baseXml = $builder->buildAirBookRequest(
    $config,
    $ctx,
    $paxBuilder->buildPassengersFromBooking($booking),
    $paxBuilder->buildContactFromBooking($booking),
);

// Gender Male (passes DocumentTypeGender) + inject FareBasis from RBD U
$xml = preg_replace('/Gender="M"/', 'Gender="Male"', $baseXml, 1) ?? $baseXml;
if (! preg_match('/FareBasisCode>/', $xml)) {
    $xml = preg_replace(
        '#(<ota:TotalFare Amount="[^"]+" CurrencyCode="[^"]+"/>)\s*</ota:PassengerFare>#',
        '$1<ota:FareBasisCodes><ota:FareBasisCode>U</ota:FareBasisCode></ota:FareBasisCodes></ota:PassengerFare>',
        $xml,
        1
    ) ?? $xml;
}
// Also set FareType/CabinClass on segment if empty
$xml = preg_replace('/ResBookDesigCode="U"/', 'ResBookDesigCode="U" FareType="PUBLISHED" CabinClass="ECONOMY"', $xml, 1) ?? $xml;

$cert = (string) ($config['tls_cert_path'] ?? '');
$key = (string) ($config['tls_key_path'] ?? '');
$endpoint = (string) ($config['endpoint_url'] ?? '');
$pending = Http::timeout(60)->withHeaders([
    'Content-Type' => 'text/xml; charset=utf-8',
    'SOAPAction' => '"http://zapways.com/air/ota/2.0/AirBook"',
]);
if ($cert !== '' && is_file($cert)) {
    $pending = $pending->withOptions([
        'cert' => $cert,
        'ssl_key' => (($key !== '' && is_file($key)) ? $key : $cert),
        'verify' => true,
    ]);
}
$resp = $pending->withBody($xml, 'text/xml; charset=utf-8')->post($endpoint);
$body = (string) $resp->body();
echo 'HTTP_STATUS='.$resp->status().PHP_EOL;
echo 'HAS_AIRBOOKRS='.(stripos($body, 'AirBookRS') !== false || stripos($body, 'OTA_AirBookRS') !== false ? 'YES' : 'NO').PHP_EOL;
echo 'HAS_SUCCESS='.(stripos($body, 'Success') !== false ? 'YES' : 'NO').PHP_EOL;
if (preg_match('/BookingReferenceID[^>]*\bID="([A-Z0-9]+)"/i', $body, $m)) {
    echo 'PNR_HASH='.substr(hash('sha256', strtoupper($m[1])), 0, 12).PHP_EOL;
    echo 'PNR_PRESENT=YES'.PHP_EOL;
} else {
    echo 'PNR_PRESENT=NO'.PHP_EOL;
}
if (preg_match('/Instance="([^"]+)"/i', $body, $m)) {
    echo 'INSTANCE_HASH='.substr(hash('sha256', $m[1]), 0, 12).PHP_EOL;
    echo 'INSTANCE_PRESENT=YES'.PHP_EOL;
}
if (preg_match('/<(?:[a-zA-Z0-9_]+:)?faultstring[^>]*>([^<]+)</i', $body, $m)) {
    $fault = html_entity_decode(trim($m[1]), ENT_QUOTES);
    echo 'FAULT='.substr($fault, 0, 220).PHP_EOL;
}
if (preg_match_all('/<(?:[a-zA-Z0-9_]+:)?Error[^>]*>([^<]{0,80})</i', $body, $m)) {
    echo 'ERRORS='.implode('|', array_slice($m[1], 0, 8)).PHP_EOL;
}
if (preg_match_all('/ShortText="([^"]{1,80})"/i', $body, $m)) {
    echo 'SHORTTEXT='.implode('|', array_slice($m[1], 0, 8)).PHP_EOL;
}
echo 'REQ_HAS_FAREBASIS='.(str_contains($xml, 'FareBasisCode') ? 'YES' : 'NO').PHP_EOL;
echo 'REQ_GENDER_MALE='.(str_contains($xml, 'Gender="Male"') ? 'YES' : 'NO').PHP_EOL;
echo 'BODY_BYTES='.strlen($body).PHP_EOL;
