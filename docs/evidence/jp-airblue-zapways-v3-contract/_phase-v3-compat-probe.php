<?php
/**
 * Non-mutating v3 TEST compatibility probe.
 * UNSAVED Conn22 clone only. Exactly ONE Read; optional ONE SeatMap.
 * No AirBook / AirBookModify / AirDemandTicket / Cancel / seat select.
 */
declare(strict_types=1);

$appRoot = '/home/pkjetp/jetpk_app';
chdir($appRoot);
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\AirBlueAncillaryService;
use App\Services\Suppliers\AirBlue\AirBlueClient;
use App\Services\Suppliers\AirBlue\AirBlueConfigResolver;
use App\Services\Suppliers\AirBlue\AirBlueOtaXmlBuilder;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueException;

function out(string $k, string|int|float|bool|null $v): void
{
    if (is_bool($v)) {
        $v = $v ? 'YES' : 'NO';
    }
    echo $k.'='.(string) $v.PHP_EOL;
}

function hash12(string $s): string
{
    return substr(hash('sha256', $s), 0, 12);
}

function connFingerprint(SupplierConnection $c): string
{
    $cred = is_array($c->credentials) ? $c->credentials : [];

    return hash('sha256', json_encode([
        'id' => (int) $c->id,
        'provider' => (string) ($c->provider?->value ?? ''),
        'env' => (string) ($c->environment?->value ?? ''),
        'active' => (bool) $c->is_active,
        'base_url' => (string) $c->base_url,
        'keys' => array_keys($cred),
        'ticketing_enabled' => $cred['ticketing_enabled'] ?? null,
        'cancel_enabled' => $cred['cancel_enabled'] ?? null,
        'booking_enabled' => $cred['booking_enabled'] ?? null,
        'protocol_version' => $cred['protocol_version'] ?? null,
        'api_channel' => $cred['api_channel'] ?? null,
        'certification_status' => $cred['certification_status'] ?? null,
    ]));
}

out('PROVIDER_MUTATION_CALLS', '0');
out('AIRBOOK_CALLS', '0');
out('AIRBOOK_MODIFY_CALLS', '0');
out('TICKETING_CALLS', '0');
out('CANCEL_CALLS', '0');
out('REFUND_CALLS', '0');
out('TEST_INITIATED_NON_AIRBLUE_SUPPLIER_CALLS', '0');

$start = [];
foreach (SupplierConnection::query()->orderBy('id')->get() as $c) {
    $start[(int) $c->id] = connFingerprint($c);
}

$conn22 = SupplierConnection::find(22);
if (! $conn22) {
    out('FATAL', 'conn22_missing');
    exit(2);
}
$host22 = (string) (parse_url((string) $conn22->base_url, PHP_URL_HOST) ?: '');
$cred22 = is_array($conn22->credentials) ? $conn22->credentials : [];
if ((string) ($conn22->environment?->value ?? '') !== 'sandbox' || $host22 !== 'otatest4.zapways.com') {
    out('FATAL', 'STOP_ENV_DRIFT');
    exit(2);
}
out('CONN22_PROTOCOL_PRE', (string) ($cred22['protocol_version'] ?? '2.0'));
out('CONN22_HOST_PRE', $host22);

// Runtime override: WSDL-certified v3 TEST SOAPActions (PR not deployed).
$versions = (array) config('suppliers.airblue.protocol_versions');
$v3 = is_array($versions['3.0'] ?? null) ? $versions['3.0'] : [];
$liveRead = (string) (($v3['ota_operations']['read']['soap_action_live'] ?? null) ?: 'https://ota.zapways.com/Read');
$v3['ota_operations'] = [
    'air_low_fare_search' => ['soap_action' => 'http://zapways.com/air/ota/3.0/AirLowFareSearch'],
    'air_book' => ['soap_action' => 'http://zapways.com/air/ota/3.0/AirBook'],
    'air_demand_ticket' => ['soap_action' => 'http://zapways.com/air/ota/3.0/AirDemandTicket'],
    'read' => [
        'soap_action_test' => 'http://zapways.com/air/ota/3.0/Read',
        'soap_action_live' => $liveRead,
    ],
    'cancel' => ['soap_action' => 'http://zapways.com/air/ota/3.0/Cancel'],
    'air_book_modify' => ['soap_action' => 'http://zapways.com/air/ota/3.0/AirBookModify'],
    'air_seat_map' => ['soap_action' => 'http://zapways.com/air/ota/3.0/AirSeatMap'],
    'air_ancillary_items' => ['soap_action' => 'http://zapways.com/air/ota/3.0/AirAncillaryItems'],
];
$versions['3.0'] = $v3;
config(['suppliers.airblue.protocol_versions' => $versions]);

$clone = $conn22->replicate();
$clone->exists = false;
$clone->id = null;
$cloneCred = is_array($clone->credentials) ? $clone->credentials : [];
$cloneCred['protocol_version'] = '3.0';
$cloneCred['certification_status'] = 'pending';
$clone->credentials = $cloneCred;
$clone->base_url = 'https://otatest4.zapways.com/v3.0/OTAAPI.asmx';
out('CLONE_SAVED', $clone->exists ? 'YES' : 'NO');
out('CLONE_PROTOCOL', '3.0');
out('CLONE_BASE_HOST', (string) (parse_url((string) $clone->base_url, PHP_URL_HOST) ?: ''));

$config = app(AirBlueConfigResolver::class)->resolveOta($clone);
$client = app(AirBlueClient::class);
$method = (new \ReflectionClass($client))->getMethod('resolveOtaSoapAction');
$method->setAccessible(true);
$readAction = (string) $method->invoke($client, 'read', $config);
$seatAction = (string) $method->invoke($client, 'air_seat_map', $config);
out('RUNTIME_V3_READ_SOAP_ACTION', $readAction);
out('RUNTIME_V3_SEAT_MAP_SOAP_ACTION', $seatAction);
out('RUNTIME_V3_ENDPOINT', (string) ($config['endpoint_url'] ?? ''));
out('RUNTIME_V3_NAMESPACE', (string) ($config['namespace'] ?? ''));
if ($readAction !== 'http://zapways.com/air/ota/3.0/Read'
    || (string) ($config['endpoint_url'] ?? '') !== 'https://otatest4.zapways.com/v3.0/OTAAPI.asmx') {
    out('FATAL', 'v3_runtime_contract_mismatch');
    exit(10);
}

$candidates = Booking::query()
    ->where(function ($q) {
        $q->where('source_channel', 'qa_ticketing_cert_booking_c')
            ->orWhere('booking_reference', 'like', 'AB-TIX-C-%');
    })
    ->orderByDesc('id')
    ->get();
$matches = [];
foreach ($candidates as $b) {
    $pnr = trim((string) ($b->pnr ?? ''));
    if ($pnr !== '' && hash12($pnr) === '94b2bd04b019') {
        $matches[] = $b;
    }
}
out('BOOKING_C_MATCH_COUNT', (string) count($matches));
if (count($matches) !== 1) {
    out('FATAL', 'booking_c_match_count_not_1');
    exit(11);
}
$booking = $matches[0]->fresh(['passengers']);
$meta = is_array($booking->meta) ? $booking->meta : [];
$ctx = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
$pnr = trim((string) ($booking->pnr ?? ''));
out('BOOKING_C_FOUND', 'YES');
out('PNR_C_HASH', hash12($pnr));
out('BOOKING_C_CONNECTION_ID', (string) (int) ($meta['supplier_connection_id'] ?? 0));

// Direct Read — do not mergeSync / save Booking C.
$xml = app(AirBlueOtaXmlBuilder::class)->buildReadRequest($config, $pnr, null);
out('V3_READ_CALLS', '1');
$readSynced = false;
$pnrMatch = false;
$readHttp = 'ABSENT';
$readReason = '';
$parsed = [];
try {
    $response = $client->callOta($clone, 'read', $xml, ['request_context' => 'v3_compat_read']);
    $diag = is_array($response['_ota_diagnostic'] ?? null) ? $response['_ota_diagnostic'] : [];
    $readHttp = (string) ($diag['http_status'] ?? '200');
    $parsed = is_array($response['parsed'] ?? null) ? $response['parsed'] : [];
    $bookingParsed = is_array($parsed['booking'] ?? null) ? $parsed['booking'] : [];
    $respPnr = trim((string) ($parsed['pnr'] ?? $bookingParsed['pnr'] ?? ''));
    $pnrMatch = $respPnr !== '' && hash12($respPnr) === '94b2bd04b019';
    $tickets = is_array($parsed['tickets'] ?? null) ? $parsed['tickets'] : [];
    $unticketed = $tickets === [];
    if ($pnrMatch) {
        $readSynced = true;
        $readReason = 'synced';
    } else {
        $readSynced = false;
        $readReason = $respPnr !== '' ? 'pnr_mismatch' : 'empty_or_unusable';
    }
    out('V3_READ_HTTP', $readHttp);
    out('V3_READ_SYNCED', $readSynced ? 'YES' : 'NO');
    out('V3_READ_REASON', $readReason);
    out('V3_READ_PNR_MATCH', $pnrMatch ? 'YES' : 'NO');
    out('V3_READ_INSTANCE_PRESENT', trim((string) ($parsed['instance'] ?? $bookingParsed['instance'] ?? '')) !== '' ? 'YES' : 'NO');
    out('V3_READ_UNTICKETED', $unticketed ? 'YES' : 'NO');
} catch (AirBlueException $e) {
    $readHttp = (string) ($e->context['http_status'] ?? $e->httpStatus ?? 'ABSENT');
    out('V3_READ_HTTP', $readHttp);
    out('V3_READ_SYNCED', 'NO');
    out('V3_READ_REASON', (string) $e->normalizedCode);
    out('V3_READ_PNR_MATCH', 'NO');
    out('V3_READ_INSTANCE_PRESENT', 'NO');
    out('V3_READ_UNTICKETED', 'UNKNOWN');
    out('V3_READ_FAULT_CLASS', (string) ($e->context['fault_class'] ?? ''));
    $snippet = (string) ($e->context['fault_snippet'] ?? $e->getMessage());
    out('V3_READ_FAULT_SNIPPET_SANITIZED', substr(preg_replace('/\s+/', ' ', $snippet), 0, 220));
    out('V3_SEAT_MAP_CALLS', '0');
    out('FINAL_STATUS', 'V2_PNR_NOT_INTEROPERABLE_WITH_V3');
    goto restore_check;
}

if (! $readSynced || ! $pnrMatch) {
    out('V3_SEAT_MAP_CALLS', '0');
    out('FINAL_STATUS', 'V2_PNR_NOT_INTEROPERABLE_WITH_V3');
    goto restore_check;
}

// Build seat-map segments from parsed Read booking/itineraries, then booking context fallback.
$flightSegments = [];
$bookingParsed = is_array($parsed['booking'] ?? null) ? $parsed['booking'] : [];
foreach (is_array($bookingParsed['segments'] ?? null) ? $bookingParsed['segments'] : [] as $seg) {
    if (is_array($seg)) {
        $flightSegments[] = $seg;
    }
}
foreach (is_array($parsed['priced_itineraries'] ?? null) ? $parsed['priced_itineraries'] : [] as $itin) {
    if (! is_array($itin)) {
        continue;
    }
    foreach (is_array($itin['segments'] ?? null) ? $itin['segments'] : [] as $seg) {
        if (is_array($seg)) {
            $flightSegments[] = $seg;
        }
    }
}
if ($flightSegments === [] && is_array($ctx['priced_itineraries'] ?? null)) {
    foreach ($ctx['priced_itineraries'] as $itin) {
        if (! is_array($itin)) {
            continue;
        }
        foreach (is_array($itin['segments'] ?? null) ? $itin['segments'] : [] as $seg) {
            if (is_array($seg)) {
                $flightSegments[] = $seg;
            }
        }
    }
}
// Deduplicate by flight_number + departure_datetime
$deduped = [];
foreach ($flightSegments as $seg) {
    $key = trim((string) ($seg['flight_number'] ?? '')).'|'.trim((string) ($seg['departure_datetime'] ?? ''));
    if ($key === '|') {
        continue;
    }
    $deduped[$key] = $seg;
}
$flightSegments = array_values($deduped);
out('V3_READ_SEGMENT_COUNT', (string) count($flightSegments));
if ($flightSegments === []) {
    out('V3_SEAT_MAP_CALLS', '0');
    out('FINAL_STATUS', 'V3_READ_OK_NO_SEGMENTS_FOR_SEATMAP');
    goto restore_check;
}

out('V3_SEAT_MAP_CALLS', '1');
$seatCount = 0;
$available = 0;
$freeAvailable = 0;
$paidAvailable = 0;
try {
    $seatResult = app(AirBlueAncillaryService::class)->fetchSeatMap($clone, [
        'pnr' => $pnr,
        'instance' => '',
        'flight_segments' => $flightSegments,
    ]);
    $maps = is_array($seatResult['seat_maps'] ?? null) ? $seatResult['seat_maps'] : [];
    foreach ($maps as $map) {
        if (! is_array($map)) {
            continue;
        }
        foreach (is_array($map['rows'] ?? null) ? $map['rows'] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach (is_array($row['seats'] ?? null) ? $row['seats'] : [] as $seat) {
                if (! is_array($seat)) {
                    continue;
                }
                $seatCount++;
                $isAvail = (bool) ($seat['is_available'] ?? false);
                if (! $isAvail) {
                    $availInd = strtolower(trim((string) ($seat['available_ind'] ?? '')));
                    $occInd = strtolower(trim((string) ($seat['occupied_ind'] ?? '')));
                    $isAvail = in_array($availInd, ['true', '1', 'yes'], true) && ! in_array($occInd, ['true', '1', 'yes'], true);
                }
                if (! $isAvail) {
                    continue;
                }
                $available++;
                $cost = $seat['cost'] ?? '';
                $isFree = (bool) ($seat['is_complimentary'] ?? false);
                if (! array_key_exists('is_complimentary', $seat)) {
                    $isFree = $cost === '' || $cost === null || (is_numeric($cost) && (float) $cost <= 0.0);
                }
                if ($isFree) {
                    $freeAvailable++;
                } else {
                    $paidAvailable++;
                }
            }
        }
    }
    out('V3_SEAT_MAP_HTTP', '200');
    out('V3_SEAT_MAP_COUNT', (string) count($maps));
    out('V3_SEAT_MAP_SUCCESS', ($maps !== [] && $seatCount > 0) ? 'YES' : (($maps !== [] || $seatCount > 0) ? 'PARTIAL' : 'NO'));
    out('V3_SEAT_COUNT', (string) $seatCount);
    out('V3_AVAILABLE_SEAT_COUNT', (string) $available);
    out('V3_FREE_AVAILABLE_SEAT_COUNT', (string) $freeAvailable);
    out('V3_PAID_AVAILABLE_SEAT_COUNT', (string) $paidAvailable);
    if ($seatCount === 0) {
        out('FREE_SEAT_CERTIFICATION_POSSIBLE', 'UNKNOWN');
        out('PAID_SEAT_PAYMENT_FLOW_REQUIRED', 'UNKNOWN');
        out('FINAL_STATUS', $maps !== [] ? 'V3_COMPAT_READ_PASS_SEATMAP_EMPTY_SEATS' : 'V3_SEAT_MAP_EMPTY');
    } else {
        out('FREE_SEAT_CERTIFICATION_POSSIBLE', $freeAvailable > 0 ? 'YES' : 'NO');
        out('PAID_SEAT_PAYMENT_FLOW_REQUIRED', $freeAvailable > 0 ? 'NO' : 'YES');
        out('FINAL_STATUS', 'V3_COMPAT_PROBE_PASS');
    }
} catch (AirBlueException $e) {
    $http = (string) ($e->context['http_status'] ?? $e->httpStatus ?? 'ABSENT');
    out('V3_SEAT_MAP_HTTP', $http);
    out('V3_SEAT_MAP_SUCCESS', 'NO');
    out('V3_SEAT_COUNT', '0');
    out('V3_AVAILABLE_SEAT_COUNT', '0');
    out('V3_FREE_AVAILABLE_SEAT_COUNT', '0');
    out('V3_PAID_AVAILABLE_SEAT_COUNT', '0');
    out('FREE_SEAT_CERTIFICATION_POSSIBLE', 'NO');
    out('PAID_SEAT_PAYMENT_FLOW_REQUIRED', 'UNKNOWN');
    out('V3_SEAT_MAP_REASON', (string) $e->normalizedCode);
    $snippet = (string) ($e->context['fault_snippet'] ?? $e->getMessage());
    out('V3_SEAT_MAP_FAULT_SNIPPET_SANITIZED', substr(preg_replace('/\s+/', ' ', $snippet), 0, 220));
    out('FINAL_STATUS', 'V3_SEAT_MAP_FAILED');
} catch (Throwable $e) {
    out('V3_SEAT_MAP_HTTP', 'ABSENT');
    out('V3_SEAT_MAP_SUCCESS', 'NO');
    out('V3_SEAT_COUNT', '0');
    out('V3_AVAILABLE_SEAT_COUNT', '0');
    out('V3_FREE_AVAILABLE_SEAT_COUNT', '0');
    out('V3_PAID_AVAILABLE_SEAT_COUNT', '0');
    out('FREE_SEAT_CERTIFICATION_POSSIBLE', 'NO');
    out('PAID_SEAT_PAYMENT_FLOW_REQUIRED', 'UNKNOWN');
    out('V3_SEAT_MAP_REASON', 'exception');
    out('V3_SEAT_MAP_FAULT_SNIPPET_SANITIZED', substr(preg_replace('/\s+/', ' ', $e->getMessage()), 0, 220));
    out('FINAL_STATUS', 'V3_SEAT_MAP_FAILED');
}

restore_check:
$conn22After = SupplierConnection::find(22);
$changed = 0;
$conn22Changed = 'YES';
if ($conn22After) {
    $conn22Changed = (($start[22] ?? '') === connFingerprint($conn22After)) ? 'NO' : 'YES';
}
foreach (SupplierConnection::query()->orderBy('id')->get() as $c) {
    $id = (int) $c->id;
    if (($start[$id] ?? '') !== '' && ($start[$id] ?? '') !== connFingerprint($c)) {
        $changed++;
    }
}
out('CONN22_CHANGED', $conn22Changed);
out('NON_CONN22_CONNECTION_ROWS_CHANGED', (string) max(0, $changed - ($conn22Changed === 'YES' ? 1 : 0)));
out('CONNECTION_ROWS_CHANGED_TOTAL', (string) $changed);
$idsAfter = SupplierConnection::query()->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
$idsBefore = array_map('intval', array_keys($start));
sort($idsAfter);
sort($idsBefore);
out('CLONE_WAS_PERSISTED', $idsAfter === $idsBefore ? 'NO' : 'YES');
out('DONE', 'YES');
