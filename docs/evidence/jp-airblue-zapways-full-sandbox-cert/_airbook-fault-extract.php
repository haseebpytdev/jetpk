<?php
/**
 * One-shot AirBook HTTP 500 fault extractor — prints only fault/error codes, never full SOAP/secrets.
 */
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
use Illuminate\Support\Facades\Http;
use ReflectionClass;

$booking = Booking::query()->with(['passengers', 'contact'])->find(41);
$conn = SupplierConnection::find(22);
$resolver = app(AirBlueConfigResolver::class);
$builder = app(AirBlueOtaXmlBuilder::class);
$paxBuilder = app(AirBluePassengerPayloadBuilder::class);
$config = $resolver->resolveOta($conn);
$meta = is_array($booking->meta) ? $booking->meta : [];
$ctx = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
$xml = $builder->buildAirBookRequest(
    $config,
    $ctx,
    $paxBuilder->buildPassengersFromBooking($booking),
    $paxBuilder->buildContactFromBooking($booking),
);

// Use same client TLS options via reflection of private methods if needed — simpler: Http with cert paths from config
$cert = (string) ($config['tls_cert_path'] ?? '');
$key = (string) ($config['tls_key_path'] ?? '');
$endpoint = (string) ($config['endpoint_url'] ?? '');
$soapAction = 'http://zapways.com/air/ota/2.0/AirBook';

$pending = Http::timeout(60)->withHeaders([
    'Content-Type' => 'text/xml; charset=utf-8',
    'SOAPAction' => '"'.$soapAction.'"',
]);
if ($cert !== '' && is_file($cert)) {
    $sslKey = ($key !== '' && is_file($key)) ? $key : $cert;
    $pending = $pending->withOptions(['cert' => $cert, 'ssl_key' => $sslKey, 'verify' => true]);
}

$resp = $pending->withBody($xml, 'text/xml; charset=utf-8')->post($endpoint);
$status = $resp->status();
$body = (string) $resp->body();
echo 'HTTP_STATUS='.$status.PHP_EOL;
echo 'BODY_BYTES='.strlen($body).PHP_EOL;

// Extract fault/error tokens only
$codes = [];
if (preg_match_all('/<(?:Faultcode|faultcode|ErrorCode|Code|ShortText|Type)[^>]*>([^<]{1,80})</i', $body, $m)) {
    foreach ($m[1] as $v) {
        $codes[] = trim($v);
    }
}
if (preg_match_all('/\b(E[A-Z0-9_]{2,40}|ERR[_A-Z0-9]{2,40}|INVALID[_A-Z0-9]{2,40})\b/', $body, $m2)) {
    foreach ($m2[1] as $v) {
        $codes[] = $v;
    }
}
$codes = array_values(array_unique(array_slice($codes, 0, 20)));
echo 'FAULT_TOKENS='.( $codes === [] ? 'none' : implode('|', $codes) ).PHP_EOL;

// Presence flags without content
echo 'HAS_SOAP_FAULT='.(stripos($body, 'Fault') !== false ? 'YES' : 'NO').PHP_EOL;
echo 'HAS_OTA_ERROR='.(stripos($body, 'Error') !== false ? 'YES' : 'NO').PHP_EOL;
echo 'HAS_AIRBOOKRS='.(stripos($body, 'AirBookRS') !== false || stripos($body, 'OTA_AirBookRS') !== false ? 'YES' : 'NO').PHP_EOL;

// XML structure checks (no dump)
echo 'REQ_HAS_FARE_BASIS_NONEMPTY='.(preg_match('/FareBasisCode>[^<]+</', $xml) || preg_match('/FareBasis="[^"]+"/', $xml) ? 'YES' : 'NO').PHP_EOL;
echo 'REQ_HAS_RBD='.(preg_match('/ResBookDesigCode="[^"]+"/', $xml) || str_contains($xml, 'RBD') ? 'YES' : 'NO').PHP_EOL;
echo 'REQ_HAS_CLIENT_ID='.(str_contains($xml, 'ClientId') || str_contains($xml, 'client_id') || str_contains(strtolower($xml), 'clientid') ? 'YES' : 'NO').PHP_EOL;
echo 'ENDPOINT_HOST='.(parse_url($endpoint, PHP_URL_HOST) ?: '').PHP_EOL;
