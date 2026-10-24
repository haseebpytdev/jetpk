<?php
/** Read-only structural probe: count seat-related XML locals. No PNR/Instance dump. */
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

function hash12(string $s): string
{
    return substr(hash('sha256', $s), 0, 12);
}

function countLocals(string $raw, array $names): array
{
    $out = [];
    if ($raw === '') {
        foreach ($names as $n) {
            $out[$n] = 0;
        }

        return $out;
    }
    $dom = new DOMDocument;
    @$dom->loadXML($raw, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    $xp = new DOMXPath($dom);
    foreach ($names as $n) {
        $nodes = $xp->query('//*[local-name()="'.$n.'"]');
        $out[$n] = $nodes ? $nodes->length : 0;
    }

    return $out;
}

$targetHash = 'e8cb368f76c0';
$booking = null;
foreach (Booking::query()->where('source_channel', 'qa_v3_booking_d_ticketing')->orderByDesc('id')->get() as $b) {
    if (hash12(trim((string) ($b->pnr ?? ''))) === $targetHash) {
        $booking = $b;
        break;
    }
}
$meta = is_array($booking->meta) ? $booking->meta : [];
$conn = SupplierConnection::find((int) ($meta['supplier_connection_id'] ?? 0));
$config = app(AirBlueConfigResolver::class)->resolveOta($conn);
$client = app(AirBlueClient::class);
$pnr = trim((string) $booking->pnr);
$ctx = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
$instance = trim((string) ($ctx['instance'] ?? ''));

$names = [
    'SeatRequest', 'SeatRequests', 'SpecialReqDetails', 'SeatMap', 'RowInfo', 'SeatInfo',
    'Success', 'Warnings', 'Errors', 'Error', 'AirReservation', 'TravelerInfo',
    'SpecialServiceRequest', 'SpecialServiceRequests',
];

$xml = app(AirBlueOtaXmlBuilder::class)->buildReadRequest($config, $pnr, null);
$resp = $client->callOta($conn, 'read', $xml, ['request_context' => 'v3_booking_d_elem_read']);
$raw = (string) ($resp['raw_xml'] ?? '');
$errors = is_array($resp['errors'] ?? null) ? $resp['errors'] : [];
unset($resp['raw_xml']);
$counts = countLocals($raw, $names);
echo "READ_HTTP=200\n";
echo 'READ_ERROR_COUNT='.count($errors)."\n";
foreach ($counts as $k => $v) {
    echo 'READ_'.$k.'='.$v."\n";
}
// Attribute scan for RowNumber without values
$rowAttr = 0;
$seatAttr = 0;
if ($raw !== '') {
    $rowAttr = preg_match_all('/\bRowNumber="/', $raw) ?: 0;
    $seatAttr = preg_match_all('/\bSeatNumber="/', $raw) ?: 0;
}
echo 'READ_ATTR_RowNumber='.$rowAttr."\n";
echo 'READ_ATTR_SeatNumber='.$seatAttr."\n";
echo 'RAW_LEN='.strlen($raw)."\n";

// Compare Booking C (known seated) structural presence — Read only
$cHash = '94b2bd04b019';
$bookingC = null;
foreach (Booking::query()->where('source_channel', 'qa_ticketing_cert_booking_c')->orderByDesc('id')->get() as $b) {
    if (hash12(trim((string) ($b->pnr ?? ''))) === $cHash) {
        $bookingC = $b;
        break;
    }
}
if ($bookingC) {
    $clone = $conn; // use v3 conn for Read compatibility
    $pnrC = trim((string) $bookingC->pnr);
    $xmlC = app(AirBlueOtaXmlBuilder::class)->buildReadRequest($config, $pnrC, null);
    $respC = $client->callOta($clone, 'read', $xmlC, ['request_context' => 'v3_booking_c_elem_read']);
    $rawC = (string) ($respC['raw_xml'] ?? '');
    unset($respC['raw_xml']);
    $countsC = countLocals($rawC, $names);
    foreach ($countsC as $k => $v) {
        echo 'BOOKING_C_READ_'.$k.'='.$v."\n";
    }
    echo 'BOOKING_C_ATTR_RowNumber='.(preg_match_all('/\bRowNumber="/', $rawC) ?: 0)."\n";
    echo 'BOOKING_C_ATTR_SeatNumber='.(preg_match_all('/\bSeatNumber="/', $rawC) ?: 0)."\n";
}
echo "DONE=YES\n";
