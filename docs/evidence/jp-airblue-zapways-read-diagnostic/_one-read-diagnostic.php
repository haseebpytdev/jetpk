<?php
/**
 * Exactly ONE Zapways TEST Read diagnostic on Booking C (PR125).
 * No Search/AirBook/AirDemandTicket/Cancel/Refund/second Read.
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
use App\Services\Suppliers\AirBlue\AirBlueRetrieveService;
use ReflectionClass;

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
        'host' => (string) (parse_url((string) $c->base_url, PHP_URL_HOST) ?: ''),
        'keys' => array_keys($cred),
        'ticketing_enabled' => $cred['ticketing_enabled'] ?? null,
        'protocol_version' => $cred['protocol_version'] ?? null,
        'api_channel' => $cred['api_channel'] ?? null,
        'base_url' => (string) $c->base_url,
    ]));
}

out('TICKETING_CALLS', '0');
out('AIRBOOK_CALLS', '0');
out('CANCEL_CALLS', '0');
out('REFUND_CALLS', '0');
out('TEST_INITIATED_NON_AIRBLUE_SUPPLIER_CALLS', '0');

$start = [];
foreach (SupplierConnection::query()->orderBy('id')->get() as $c) {
    $start[(int) $c->id] = connFingerprint($c);
}

$conn = SupplierConnection::find(22);
if (! $conn) {
    out('FATAL', 'conn22_missing');
    exit(2);
}
$host = (string) (parse_url((string) $conn->base_url, PHP_URL_HOST) ?: '');
$cred = is_array($conn->credentials) ? $conn->credentials : [];
if ((string) ($conn->environment?->value ?? '') !== 'sandbox' || $host !== 'otatest4.zapways.com') {
    out('FATAL', 'STOP_ENV_DRIFT');
    exit(2);
}
out('CONN22_PROTOCOL', (string) ($cred['protocol_version'] ?? ''));
out('CONN22_CHANNEL', (string) ($cred['api_channel'] ?? ''));
$fp22Pre = $start[22] ?? '';

// Offline contract check (no network)
$config = app(AirBlueConfigResolver::class)->resolveOta($conn);
$client = app(AirBlueClient::class);
$method = (new ReflectionClass($client))->getMethod('resolveOtaSoapAction');
$method->setAccessible(true);
$soapAction = (string) $method->invoke($client, 'read', $config);
$endpoint = (string) ($config['endpoint_url'] ?? '');
out('RUNTIME_READ_SOAP_ACTION', $soapAction);
out('RUNTIME_READ_ENDPOINT', $endpoint);
$xml = app(AirBlueOtaXmlBuilder::class)->buildReadRequest($config, 'PLACEHOLDERPNR');
out('READ_XML_PNR_ONLY', str_contains($xml, '<ota:UniqueID ID="PLACEHOLDERPNR"/>') ? 'YES' : 'NO');
out('READ_XML_TYPE14_ABSENT', ! str_contains($xml, 'Type="14"') ? 'YES' : 'NO');
out('READ_XML_INSTANCE_ABSENT', ! str_contains($xml, 'Instance=') ? 'YES' : 'NO');
out('PLACEHOLDER_READ_SENT', 'NO');
if ($soapAction !== 'https://ota.qa.zapways.com/Read' || $endpoint !== 'https://otatest4.zapways.com/v2.0/OTAAPI.asmx') {
    out('FATAL', 'read_contract_mismatch');
    exit(10);
}

// Locate Booking C
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
    out('BOOKING_C_FOUND', 'NO');
    out('FATAL', 'booking_c_match_count_not_1');
    exit(11);
}
$booking = $matches[0]->fresh();
out('BOOKING_C_FOUND', 'YES');
out('BOOKING_C_ID_HASH', hash12((string) $booking->id));
out('BOOKING_C_REF_HASH', hash12((string) $booking->booking_reference));

$meta = is_array($booking->meta) ? $booking->meta : [];
$ctx = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
$tickets = is_array($ctx['ticket_numbers'] ?? null) ? $ctx['ticket_numbers'] : [];
$ticketed = ($ctx['ticketing_status'] ?? '') === 'ticketed' || $tickets !== [];
$connId = (int) ($meta['supplier_connection_id'] ?? 0);
out('BOOKING_C_CONNECTION_ID', (string) $connId);
out('TICKETED_PRE', $ticketed ? 'YES' : 'NO');
out('CONTEXT_SUPPLIER_PAYABLE', (string) ($ctx['supplier_payable_amount'] ?? 'ABSENT'));
if ($connId !== 22 || $ticketed || (string) ($booking->supplier ?? '') !== 'airblue') {
    out('FATAL', 'booking_c_precondition_fail');
    exit(12);
}

// Force normal current-PNR mode (no specific instance)
unset($ctx['read_specific_instance'], $ctx['retrieve_mode'], $ctx['read_mode']);
$meta['airblue_context'] = $ctx;
$booking->meta = $meta;
$booking->save();
$booking = $booking->fresh();

// Exactly ONE Read
out('READ_CALLS', '1');
$result = app(AirBlueRetrieveService::class)->retrieveAndSync($booking->fresh(), $conn->fresh());
$synced = is_array($result) && ($result['synced'] ?? false) === true;
$reason = is_array($result) ? (string) ($result['reason'] ?? ($synced ? 'synced' : 'unknown')) : 'invalid';
$diag = is_array($result['diagnostic'] ?? null) ? $result['diagnostic'] : [];

out('READ_SYNCED', $synced ? 'YES' : 'NO');
out('READ_REASON', $reason);
out('READ_HTTP_STATUS', isset($diag['http_status']) ? (string) $diag['http_status'] : 'ABSENT');
out('READ_FAULT_CLASS', (string) ($diag['fault_class'] ?? 'ABSENT'));
$snippet = (string) ($diag['fault_snippet'] ?? '');
out('READ_FAULT_SNIPPET_SANITIZED', $snippet !== '' ? substr(preg_replace('/\s+/', ' ', $snippet), 0, 220) : 'ABSENT');
out('READ_CORRELATION_ID_PRESENT', ! empty($diag['correlation_id']) ? 'YES' : 'NO');
if (! empty($diag['correlation_id'])) {
    out('READ_CORRELATION_ID_HASH', hash12((string) $diag['correlation_id']));
}

$http = isset($diag['http_status']) ? (int) $diag['http_status'] : null;
$faultClass = strtolower((string) ($diag['fault_class'] ?? ''));
$snippetL = strtolower($snippet);
$class = 'OTHER';
if ($http === 404) {
    $class = 'HTTP_404';
} elseif ($http === 405) {
    $class = 'HTTP_405';
} elseif (in_array($http, [401, 403], true)) {
    $class = 'HTTP_401_403_AUTH';
} elseif ($http === 500 && ($faultClass === 'provider_nre' || str_contains($snippetL, 'object reference not set'))) {
    $class = 'HTTP_500_PROVIDER_NRE';
} elseif ($http === 500 && ($faultClass === 'soap_fault' || $faultClass !== '' || $snippet !== '')) {
    $class = 'HTTP_500_SOAP_FAULT';
} elseif ($http === 500) {
    $class = 'HTTP_500_OTHER';
} elseif ($reason === 'provider_error' || ($http !== null && $http >= 200 && $http < 300 && ! $synced)) {
    $class = 'PROVIDER_BUSINESS_ERROR';
} elseif ($reason === 'supplier_transport_failed' || $http === null && str_contains($reason, 'transport')) {
    $class = 'TRANSPORT_ERROR';
} elseif ($http !== null && ($http < 200 || $http >= 300)) {
    $class = 'OTHER';
} elseif (! $synced && $http === null && $diag === []) {
    $class = 'OTHER';
}
out('READ_CLASSIFICATION', $class);

// Isolation end-check
$c22 = SupplierConnection::find(22);
$fp22Post = $c22 ? connFingerprint($c22) : '';
out('CONN22_CHANGED', ($fp22Pre !== '' && $fp22Pre === $fp22Post) ? 'NO' : 'YES');
$changed = 0;
foreach (SupplierConnection::query()->orderBy('id')->get() as $c) {
    $id = (int) $c->id;
    if ($id === 22) {
        continue;
    }
    if (($start[$id] ?? '') !== '' && ($start[$id] ?? '') !== connFingerprint($c)) {
        $changed++;
    }
}
out('NON_CONN22_CONNECTION_ROWS_CHANGED', (string) $changed);
out('FINAL_STATUS', $synced ? 'READ_DIAGNOSTIC_SYNCED' : 'READ_DIAGNOSTIC_CAPTURED');
out('DONE', 'YES');
