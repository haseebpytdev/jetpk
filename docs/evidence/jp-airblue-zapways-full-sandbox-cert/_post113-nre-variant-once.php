<?php
/**
 * ONE targeted NRE variant probe (not a booking lifecycle retry).
 * Mutates dry-run XML in memory only. Captures fault class.
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

function out(string $k, string|int|null $v): void
{
    echo $k.'='.(string) $v.PHP_EOL;
}

function post(string $endpoint, string $cert, string $key, string $xml): array
{
    $pending = Http::timeout(60)->withHeaders([
        'Content-Type' => 'text/xml; charset=utf-8',
        'SOAPAction' => '"http://zapways.com/air/ota/2.0/AirBook"',
    ])->withOptions([
        'cert' => $cert,
        'ssl_key' => $key,
        'verify' => true,
    ]);
    $resp = $pending->withBody($xml, 'text/xml; charset=utf-8')->post($endpoint);
    $body = (string) $resp->body();
    $fault = '';
    if (preg_match('/<(?:[a-zA-Z0-9_]+:)?faultstring[^>]*>([^<]+)</i', $body, $m)) {
        $fault = html_entity_decode(trim($m[1]), ENT_QUOTES);
    }

    return [
        'status' => $resp->status(),
        'fault' => substr($fault, 0, 200),
        'has_rs' => stripos($body, 'AirBookRS') !== false || stripos($body, 'OTA_AirBookRS') !== false,
        'has_pnr' => (bool) preg_match('/BookingReferenceID[^>]*ID="[A-Z0-9]+"/i', $body),
        'class' => match (true) {
            str_contains($fault, 'DocumentTypeGender') => 'SCHEMA_GENDER',
            str_contains($fault, 'Object reference') => 'NRE',
            str_contains($fault, 'validation error') => 'SCHEMA_OTHER',
            default => ($resp->status() >= 200 && $resp->status() < 300 ? 'HTTP_OK' : 'OTHER'),
        },
    ];
}

$conn = SupplierConnection::find(22);
$host = parse_url((string) $conn->base_url, PHP_URL_HOST) ?: '';
if ((string) ($conn->environment?->value ?? '') !== 'sandbox' || $host !== 'otatest4.zapways.com') {
    out('FATAL', 'STOP_ENV_DRIFT');
    exit(2);
}
$config = app(AirBlueConfigResolver::class)->resolveOta($conn);
$booking = Booking::query()
    ->where('source_channel', 'sandbox_cert')
    ->where('meta->cert_loop', 'JP-AIRBLUE-ZAPWAYS-FULL-SANDBOX-CERT-POST113')
    ->orderByDesc('id')->with(['passengers', 'contact'])->first();
$paxBuilder = app(AirBluePassengerPayloadBuilder::class);
$meta = is_array($booking->meta) ? $booking->meta : [];
$fromMeta = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
$snapshot = is_array($booking->offer_snapshot) ? $booking->offer_snapshot : (is_array($meta['offer_snapshot'] ?? null) ? $meta['offer_snapshot'] : []);
$raw = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [];
$fromSnapshot = is_array($raw['provider_context'] ?? null) ? $raw['provider_context'] : [];
$providerContext = array_merge($fromSnapshot, $fromMeta);
$baseXml = app(AirBlueOtaXmlBuilder::class)->buildAirBookRequest(
    $config,
    $providerContext,
    $paxBuilder->buildPassengersFromBooking($booking),
    $paxBuilder->buildContactFromBooking($booking),
);

$cert = (string) ($config['tls_cert_path'] ?? '');
$key = (string) ($config['tls_key_path'] ?? '');
$endpoint = (string) ($config['endpoint_url'] ?? '');

// Single combined variant: simple RPH + CabinClass + Equipment (most likely NRE gaps)
$xml = $baseXml;
$xml = preg_replace('/\sRPH="[^"]*"/', ' RPH="1"', $xml, 1) ?? $xml;
if (! str_contains($xml, 'CabinClass=')) {
    $xml = preg_replace('/ResBookDesigCode="([^"]*)"/', 'ResBookDesigCode="$1" CabinClass="Y"', $xml, 1) ?? $xml;
}
if (! str_contains($xml, 'Equipment')) {
    $xml = preg_replace(
        '/(<ota:OperatingAirline Code="[^"]*"\/>)/',
        '$1<ota:Equipment AirEquipType="A320"/>',
        $xml,
        1,
    ) ?? $xml;
}
// Move FareBasisCodes outside PassengerFare if nested inside
$xml = preg_replace(
    '/(<ota:PassengerFare>\s*<ota:BaseFare[^>]*\/>\s*<ota:Taxes[^>]*\/>\s*<ota:TotalFare[^>]*\/>\s*)(<ota:FareBasisCodes>.*?<\/ota:FareBasisCodes>)\s*(<\/ota:PassengerFare>)/s',
    '$1$3$2',
    $xml,
    1,
) ?? $xml;

out('VARIANT', 'rph1_cabinY_equip_farebasis_sibling');
out('HAS_CABIN', str_contains($xml, 'CabinClass="Y"') ? 'YES' : 'NO');
out('HAS_EQUIP', str_contains($xml, 'Equipment') ? 'YES' : 'NO');
out('RPH_SIMPLE', preg_match('/RPH="1"/', $xml) ? 'YES' : 'NO');

$r = post($endpoint, $cert, $key, $xml);
out('HTTP', (string) $r['status']);
out('CLASS', $r['class']);
out('HAS_RS', $r['has_rs'] ? 'YES' : 'NO');
out('HAS_PNR', $r['has_pnr'] ? 'YES' : 'NO');
out('FAULT', $r['fault'] !== '' ? $r['fault'] : 'none');
out('SUPPLIER_CALLS', '1');
out('VARIANT_COMPLETE', 'YES');
