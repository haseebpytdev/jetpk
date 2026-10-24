<?php
/** Read-only: diagnose Booking D seat sync gap. No mutations. */
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
use App\Services\Suppliers\AirBlue\AirBlueRetrieveService;

function hash12(string $s): string
{
    return substr(hash('sha256', $s), 0, 12);
}

$targetHash = 'e8cb368f76c0';
$booking = null;
foreach (Booking::query()->where('source_channel', 'qa_v3_booking_d_ticketing')->orderByDesc('id')->get() as $b) {
    if (hash12(trim((string) ($b->pnr ?? ''))) === $targetHash) {
        $booking = $b;
        break;
    }
}
if (! $booking) {
    echo "BOOKING_D_FOUND=NO\n";
    exit(2);
}
echo 'BOOKING_D_ID_HASH='.hash12((string) $booking->id)."\n";
$meta = is_array($booking->meta) ? $booking->meta : [];
$ctx = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
$seatsLocal = is_array($ctx['seats'] ?? null) ? $ctx['seats'] : [];
echo 'LOCAL_SEATS_COUNT='.count($seatsLocal)."\n";
echo 'CONN_ID='.(string) ($meta['supplier_connection_id'] ?? '')."\n";

$conn = SupplierConnection::find((int) ($meta['supplier_connection_id'] ?? 0));
if (! $conn) {
    echo "CONN_MISSING=YES\n";
    exit(3);
}

$config = app(AirBlueConfigResolver::class)->resolveOta($conn);
$pnr = trim((string) ($booking->pnr ?? ''));
$client = app(AirBlueClient::class);
$xml = app(AirBlueOtaXmlBuilder::class)->buildReadRequest($config, $pnr, null);
$resp = $client->callOta($conn, 'read', $xml, ['request_context' => 'v3_booking_d_seat_diag']);
$parsed = is_array($resp['parsed'] ?? null) ? $resp['parsed'] : [];
$raw = (string) ($resp['raw_xml'] ?? '');
unset($resp['raw_xml']);
$seatsParsed = is_array($parsed['seats'] ?? null) ? $parsed['seats'] : [];
echo 'PARSED_SEATS_COUNT='.count($seatsParsed)."\n";
echo 'PARSED_SEAT_KEYS='.(count($seatsParsed) ? implode(',', array_keys($seatsParsed[0])) : 'NONE')."\n";

$domSeatRequests = 0;
$rowNums = [];
if ($raw !== '') {
    $dom = new DOMDocument;
    @$dom->loadXML($raw, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    $xp = new DOMXPath($dom);
    $nodes = $xp->query('//*[local-name()="SeatRequest"]');
    $domSeatRequests = $nodes ? $nodes->length : 0;
    if ($nodes) {
        foreach ($nodes as $n) {
            if ($n instanceof DOMElement) {
                $rowNums[] = $n->getAttribute('RowNumber').$n->getAttribute('SeatNumber');
            }
        }
    }
}
echo 'DOM_SEATREQUEST_COUNT='.$domSeatRequests."\n";
echo 'DOM_SEAT_IDS='.implode(',', array_slice($rowNums, 0, 5))."\n";

$sync = app(AirBlueRetrieveService::class)->retrieveAndSync($booking->fresh(), $conn);
$booking = $booking->fresh();
$meta = is_array($booking->meta) ? $booking->meta : [];
$ctx = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
$seatsAfter = is_array($ctx['seats'] ?? null) ? $ctx['seats'] : [];
echo 'SYNCED='.(($sync['synced'] ?? false) ? 'YES' : 'NO')."\n";
echo 'AFTER_SYNC_SEATS_COUNT='.count($seatsAfter)."\n";
echo 'NORMALIZE_HAS_SEATS_KEY='.(array_key_exists('seats', $sync['data'] ?? []) ? 'YES' : 'NO')."\n";
$dataSeats = is_array(($sync['data']['seats'] ?? null)) ? $sync['data']['seats'] : null;
echo 'NORMALIZE_SEATS_COUNT='.($dataSeats === null ? 'NULL' : (string) count($dataSeats))."\n";
echo "DONE=YES\n";
