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

foreach (['faultstring', 'FaultString', 'faultstring', 'Reason', 'Text', 'ShortText', 'ErrorMessage', 'Message'] as $tag) {
    if (preg_match('/<(?:[a-zA-Z0-9_]+:)?'.$tag.'[^>]*>([^<]{1,200})</i', $body, $m)) {
        $msg = trim($m[1]);
        // redact emails/numbers that look like secrets
        $msg = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+/i', 'EMAIL', $msg) ?? $msg;
        $msg = preg_replace('/\b\d{6,}\b/', 'NUM', $msg) ?? $msg;
        echo 'FAULT_'.$tag.'='.$msg.PHP_EOL;
    }
}

// Element presence in request (structure only)
foreach ([
    'POS', 'Source', 'RequestorID', 'AirItinerary', 'FlightSegment', 'PriceInfo',
    'ItinTotalFare', 'PTC_FareBreakdown', 'TravelerInfo', 'AirTraveler', 'PersonName',
    'Telephone', 'Email', 'Document',
] as $el) {
    echo 'REQ_HAS_'.$el.'='.(str_contains($xml, $el) ? 'YES' : 'NO').PHP_EOL;
}

// Attribute checks
echo 'REQ_FLIGHTNUMBER_ATTR='.(preg_match('/FlightNumber="[^"]+"/', $xml) ? 'YES' : 'NO').PHP_EOL;
echo 'REQ_DEPARTURE_ATTR='.(preg_match('/DepartureDateTime="[^"]+"/', $xml) ? 'YES' : 'NO').PHP_EOL;
echo 'REQ_RESBOOK_ATTR='.(preg_match('/ResBookDesigCode="[^"]+"/', $xml) ? 'YES' : 'NO').PHP_EOL;
echo 'REQ_FAREBASIS_ATTR_OR_EL='.(preg_match('/FareBasis[^>]*>[^<]+|FareBasisCode="[^"]+"|FareBasis="[^"]+"/', $xml) ? 'YES' : 'NO').PHP_EOL;

// Body hash only
echo 'RESP_BODY_SHA12='.substr(hash('sha256', $body), 0, 12).PHP_EOL;
echo 'RESP_BODY_BYTES='.strlen($body).PHP_EOL;
// Print body with aggressive redaction of anything between tags longer than needed — only fault envelope tags
if (preg_match('/<(?:soap:)?Fault[\s\S]{0,800}<\/(?:soap:)?Fault>/i', $body, $fm)) {
    $fault = $fm[0];
    $fault = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+/i', 'EMAIL', $fault) ?? $fault;
    $fault = preg_replace('/>([^<]{120,})</', '>[REDACTED_LONG]<', $fault) ?? $fault;
    echo 'FAULT_ENVELOPE_SANITIZED='.str_replace(["\n", "\r"], ' ', $fault).PHP_EOL;
}
