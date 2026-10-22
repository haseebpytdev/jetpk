<?php
/**
 * Offline AirBook XML wire inspection for post-PR113 — NO supplier call.
 * Prints only attribute presence / gender values / fare basis presence.
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

function out(string $k, string|int|bool|null $v): void
{
    if (is_bool($v)) {
        $v = $v ? 'YES' : 'NO';
    }
    echo $k.'='.(string) $v.PHP_EOL;
}

$conn = SupplierConnection::find(22);
$booking = Booking::query()
    ->where('source_channel', 'sandbox_cert')
    ->where('meta->cert_loop', 'JP-AIRBLUE-ZAPWAYS-FULL-SANDBOX-CERT-POST113')
    ->orderByDesc('id')
    ->with(['passengers', 'contact'])
    ->first();

if (! $booking || ! $conn) {
    out('FATAL', 'missing_booking_or_conn');
    exit(2);
}

$paxBuilder = app(AirBluePassengerPayloadBuilder::class);
$passengers = $paxBuilder->buildPassengersFromBooking($booking);
$contact = $paxBuilder->buildContactFromBooking($booking);
out('PAX_COUNT', (string) count($passengers));
out('PAX0_GENDER_LOCAL', (string) ($passengers[0]['gender'] ?? ''));
out('PAX0_TITLE', (string) ($passengers[0]['title'] ?? ''));

$meta = is_array($booking->meta) ? $booking->meta : [];
$fromMeta = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
$snapshot = is_array($booking->offer_snapshot) ? $booking->offer_snapshot : (is_array($meta['offer_snapshot'] ?? null) ? $meta['offer_snapshot'] : []);
$raw = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [];
$fromSnapshot = is_array($raw['provider_context'] ?? null) ? $raw['provider_context'] : [];
$providerContext = array_merge($fromSnapshot, $fromMeta);

$config = app(AirBlueConfigResolver::class)->resolveOta($conn);
$builder = app(AirBlueOtaXmlBuilder::class);
try {
    $xml = $builder->buildAirBookRequest($config, $providerContext, $passengers, $contact);
} catch (Throwable $e) {
    out('BUILD_EXCEPTION', $e::class);
    out('BUILD_MSG', substr($e->getMessage(), 0, 160));
    exit(3);
}

out('XML_LENGTH', (string) strlen($xml));
out('XML_HAS_GENDER_MALE', str_contains($xml, 'Gender="Male"') ? 'YES' : 'NO');
out('XML_HAS_GENDER_FEMALE', str_contains($xml, 'Gender="Female"') ? 'YES' : 'NO');
out('XML_HAS_GENDER_M_BARE', (bool) preg_match('/Gender="M"/', $xml) ? 'YES' : 'NO');
out('XML_HAS_GENDER_F_BARE', (bool) preg_match('/Gender="F"/', $xml) ? 'YES' : 'NO');
out('XML_HAS_NAMEPREFIX', str_contains($xml, 'NamePrefix=') ? 'YES' : 'NO');
out('XML_HAS_OPERATING_AIRLINE', str_contains($xml, 'OperatingAirline') ? 'YES' : 'NO');
out('XML_HAS_FARE_BASIS', str_contains($xml, 'FareBasisCode') || str_contains($xml, 'FareBasis') ? 'YES' : 'NO');
out('XML_HAS_CLIENT_ID', str_contains($xml, (string) ($config->clientId ?? '___none___')) ? 'YES_LEAK_CHECK' : 'NO');
// Strip secrets for any further analysis — do not print xml
if (preg_match_all('/Gender="([^"]+)"/', $xml, $m)) {
    out('XML_GENDER_VALUES', implode(',', array_unique($m[1])));
}
if (preg_match_all('/NamePrefix="([^"]+)"/', $xml, $m)) {
    out('XML_NAMEPREFIX_VALUES', implode(',', array_unique($m[1])));
}
if (preg_match('/FareBasisCode="([^"]*)"/', $xml, $m)) {
    out('XML_FAREBASIS_NONEMPTY', $m[1] !== '' ? 'YES' : 'NO');
}
out('DRYRUN_COMPLETE', 'YES');
out('SUPPLIER_CALLS', '0');
