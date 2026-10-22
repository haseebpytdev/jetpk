<?php
/**
 * ONE diagnostic AirBook HTTP capture after PR113 deploy failure.
 * Purpose: extract sanitized faultstring only. Not a booking retry / no local booking mutation.
 * Does not create SupplierBooking. Does not persist PNR.
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

// Ensure airblue log path exists for future calls
$logPath = $appRoot.'/storage/logs/airblue.log';
if (! file_exists($logPath)) {
    touch($logPath);
    chmod($logPath, 0664);
    out('AIRBLUE_LOG_CREATED', 'YES');
} else {
    out('AIRBLUE_LOG_CREATED', 'NO');
}

$conn = SupplierConnection::find(22);
$env = (string) ($conn->environment?->value ?? '');
$host = parse_url((string) $conn->base_url, PHP_URL_HOST) ?: '';
out('AIRBLUE_ENVIRONMENT', $env);
out('AIRBLUE_ENDPOINT_HOST_ONLY', $host);
if ($env !== 'sandbox' || $host !== 'otatest4.zapways.com') {
    out('FATAL', 'STOP_ENV_DRIFT');
    exit(2);
}

$booking = Booking::query()
    ->where('source_channel', 'sandbox_cert')
    ->where('meta->cert_loop', 'JP-AIRBLUE-ZAPWAYS-FULL-SANDBOX-CERT-POST113')
    ->orderByDesc('id')
    ->with(['passengers', 'contact'])
    ->first();
if (! $booking) {
    out('FATAL', 'no_booking_for_xml_shape');
    exit(2);
}

$paxBuilder = app(AirBluePassengerPayloadBuilder::class);
$passengers = $paxBuilder->buildPassengersFromBooking($booking);
$contact = $paxBuilder->buildContactFromBooking($booking);
$meta = is_array($booking->meta) ? $booking->meta : [];
$fromMeta = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
$snapshot = is_array($booking->offer_snapshot) ? $booking->offer_snapshot : (is_array($meta['offer_snapshot'] ?? null) ? $meta['offer_snapshot'] : []);
$raw = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [];
$fromSnapshot = is_array($raw['provider_context'] ?? null) ? $raw['provider_context'] : [];
$providerContext = array_merge($fromSnapshot, $fromMeta);
$config = app(AirBlueConfigResolver::class)->resolveOta($conn);
$xml = app(AirBlueOtaXmlBuilder::class)->buildAirBookRequest($config, $providerContext, $passengers, $contact);

out('WIRE_GENDER', preg_match('/Gender="Male"/', $xml) ? 'Male' : 'OTHER');
out('WIRE_NAMEPREFIX', preg_match('/<ota:NamePrefix>MR<\/ota:NamePrefix>/', $xml) ? 'MR' : 'OTHER');
out('WIRE_FAREBASIS', preg_match('/<ota:FareBasisCode>([^<]+)</', $xml, $m) ? $m[1] : 'EMPTY');

$endpoint = (string) ($config['endpoint_url'] ?? '');
$cert = (string) ($config['tls_cert_path'] ?? '');
$key = (string) ($config['tls_key_path'] ?? '');
out('ENDPOINT_HOST', parse_url($endpoint, PHP_URL_HOST) ?: '');
out('MTLS_CERT_EXISTS', is_file($cert) ? 'YES' : 'NO');
out('MTLS_KEY_EXISTS', is_file($key) ? 'YES' : 'NO');

// Process-scoped enable for this diagnostic process only
putenv('AIRBLUE_EXTERNAL_CALLS_ENABLED=true');
$_ENV['AIRBLUE_EXTERNAL_CALLS_ENABLED'] = 'true';
$_SERVER['AIRBLUE_EXTERNAL_CALLS_ENABLED'] = 'true';
config(['suppliers.airblue.external_calls_enabled' => true]);

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
$response = $pending->withBody($xml, 'text/xml; charset=utf-8')->post($endpoint);

$status = $response->status();
$body = (string) $response->body();
out('DIAG_HTTP_STATUS', (string) $status);
out('DIAG_BODY_BYTES', (string) strlen($body));
out('DIAG_BODY_SHA12', substr(hash('sha256', $body), 0, 12));

$fault = '';
if (preg_match('/<faultstring[^>]*>([^<]+)<\/faultstring>/i', $body, $m)) {
    $fault = html_entity_decode($m[1], ENT_QUOTES | ENT_XML1);
}
out('DIAG_FAULTSTRING', substr($fault, 0, 300));
out('DIAG_HAS_PNR', preg_match('/BookingReferenceID[^>]*ID="/i', $body) ? 'YES' : 'NO');
out('DIAG_CLASS', match (true) {
    str_contains($fault, 'DocumentTypeGender') => 'SOAP_CLIENT_SCHEMA_GENDER',
    str_contains($fault, 'Object reference') => 'PROVIDER_NRE',
    str_contains($fault, 'validation error') => 'SOAP_CLIENT_SCHEMA_OTHER',
    $status >= 500 => 'HTTP_5XX',
    default => 'OTHER',
});
out('DIAG_SUPPLIER_CALLS', '1');
out('DIAG_LOCAL_BOOKING_MUTATION', 'NO');
out('CAPTURE_COMPLETE', 'YES');
