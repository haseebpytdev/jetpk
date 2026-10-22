<?php
/**
 * Probe which Gender attribute value Zapways TEST accepts.
 * Mutates request XML only in-memory — no code deploy.
 */
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

$cert = (string) ($config['tls_cert_path'] ?? '');
$key = (string) ($config['tls_key_path'] ?? '');
$endpoint = (string) ($config['endpoint_url'] ?? '');

function postXml(string $endpoint, string $cert, string $key, string $xml): array
{
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
    $fault = '';
    if (preg_match('/<(?:[a-zA-Z0-9_]+:)?faultstring[^>]*>([^<]+)</i', $body, $m)) {
        $fault = html_entity_decode(trim($m[1]), ENT_QUOTES);
        $fault = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+/i', 'EMAIL', $fault) ?? $fault;
    }
    $hasPnr = (bool) preg_match('/BookingReferenceID[^>]*ID="([A-Z0-9]+)"/i', $body);
    $hasAirBookRs = stripos($body, 'AirBookRS') !== false || stripos($body, 'OTA_AirBookRS') !== false;

    return [
        'status' => $resp->status(),
        'fault' => substr($fault, 0, 180),
        'has_pnr' => $hasPnr,
        'has_rs' => $hasAirBookRs,
        'bytes' => strlen($body),
    ];
}

$variants = [
    'as_is_M' => $baseXml,
    'omit_gender' => preg_replace('/\sGender="M"/', '', $baseXml, 1) ?? $baseXml,
    'gender_Male' => preg_replace('/Gender="M"/', 'Gender="Male"', $baseXml, 1) ?? $baseXml,
    'gender_male' => preg_replace('/Gender="M"/', 'Gender="male"', $baseXml, 1) ?? $baseXml,
    'gender_1' => preg_replace('/Gender="M"/', 'Gender="1"', $baseXml, 1) ?? $baseXml,
];

foreach ($variants as $name => $xml) {
    $r = postXml($endpoint, $cert, $key, $xml);
    echo "VARIANT={$name} HTTP={$r['status']} HAS_RS=".($r['has_rs']?'YES':'NO').' HAS_PNR_ATTR='.($r['has_pnr']?'YES':'NO').' FAULT='.($r['fault']!==''?$r['fault']:'none').PHP_EOL;
    // stop early if success-like
    if ($r['status'] >= 200 && $r['status'] < 300 && $r['has_rs']) {
        echo "FIRST_SUCCESS_VARIANT={$name}".PHP_EOL;
        break;
    }
    usleep(400000);
}
