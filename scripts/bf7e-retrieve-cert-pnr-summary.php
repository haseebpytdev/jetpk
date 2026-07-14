<?php

/**
 * BF7-E controlled CERT PNR retrieve + safe branded-fare summary (retrieve-only).
 *
 * Usage:
 *   php scripts/bf7e-retrieve-cert-pnr-summary.php --pnr=RQFUYD --booking=51 --allow-production-cert-controlled-retrieve
 *   php scripts/bf7e-retrieve-cert-pnr-summary.php --pnr=QJUAKV --booking=51 --allow-production-cert-controlled-retrieve
 *   php scripts/bf7e-retrieve-cert-pnr-summary.php --pnr=RQFUYD --booking=51 --skip-send
 *   php scripts/bf7e-retrieve-cert-pnr-summary.php --self-test-cli-parse
 */

declare(strict_types=1);

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Services\Suppliers\Sabre\PnrRetrieve\SabrePnrRetrieveProbe;
use App\Services\Suppliers\Sabre\PnrRetrieve\SabreTripOrdersGetBookingItineraryMapper;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

const BF7E_PASSENGER_RECORDS_READ_PATH = '/v2.4.0/passenger/records?mode=read';
const BF7E_GET_BOOKING_PATH = '/v1/trip/orders/getBooking';
const BF7E_BLOCKED_BOOKING_IDS = [43, 46];

/** @var list<string> */
const BF7E_PII_BRANCH_KEYS = [
    'travelers',
    'traveler',
    'contactinfo',
    'contact',
    'payments',
    'payment',
    'specialservices',
    'specialservice',
    'customerinfo',
    'personname',
    'passenger',
    'passengers',
];

/**
 * @return array{
 *     pnr: string|null,
 *     booking_id: int|null,
 *     skip_send: bool,
 *     allow_production_cert_controlled_retrieve: bool,
 *     self_test_cli_parse: bool
 * }
 */
function parseBf7eArgv(array $argv): array
{
    $pnr = null;
    $bookingId = null;
    $skipSend = false;
    $allowProductionCertControlledRetrieve = false;
    $selfTestCliParse = false;

    $args = array_slice($argv, 1);
    for ($i = 0; $i < count($args); $i++) {
        $arg = $args[$i];
        if ($arg === '--self-test-cli-parse') {
            $selfTestCliParse = true;
        } elseif ($arg === '--skip-send') {
            $skipSend = true;
        } elseif ($arg === '--allow-production-cert-controlled-retrieve') {
            $allowProductionCertControlledRetrieve = true;
        } elseif (str_starts_with($arg, '--pnr=')) {
            $pnr = strtoupper(trim(substr($arg, 6)));
        } elseif ($arg === '--pnr' && isset($args[$i + 1]) && trim($args[$i + 1]) !== '') {
            $pnr = strtoupper(trim($args[$i + 1]));
            $i++;
        } elseif (str_starts_with($arg, '--booking=')) {
            $bookingId = (int) substr($arg, 10);
        } elseif ($arg === '--booking' && isset($args[$i + 1]) && is_numeric($args[$i + 1])) {
            $bookingId = (int) $args[$i + 1];
            $i++;
        }
    }

    return [
        'pnr' => $pnr !== null && $pnr !== '' ? $pnr : null,
        'booking_id' => $bookingId !== null && $bookingId > 0 ? $bookingId : null,
        'skip_send' => $skipSend,
        'allow_production_cert_controlled_retrieve' => $allowProductionCertControlledRetrieve,
        'self_test_cli_parse' => $selfTestCliParse,
    ];
}

function resolveAppEnvGate(bool $allowProductionFlag, string $appEnv): ?string
{
    if (in_array($appEnv, ['local', 'testing'], true)) {
        return null;
    }

    if ($appEnv === 'production' && $allowProductionFlag) {
        return null;
    }

    return 'APP_ENV must be local or testing, or pass --allow-production-cert-controlled-retrieve on production.';
}

function runBf7eCliParseSelfTest(): int
{
    $failures = [];

    $eq = static function (string $label, mixed $expected, mixed $actual) use (&$failures): void {
        if ($expected !== $actual) {
            $failures[] = $label.': expected '.json_encode($expected).', got '.json_encode($actual);
        }
    };

    $parsed = parseBf7eArgv(['script', '--pnr=RQFUYD', '--booking=51']);
    $eq('pnr_equals_form', 'RQFUYD', $parsed['pnr']);
    $eq('booking_equals_form', 51, $parsed['booking_id']);

    $parsed = parseBf7eArgv(['script', '--pnr', 'QJUAKV', '--booking', '51']);
    $eq('pnr_space_form', 'QJUAKV', $parsed['pnr']);
    $eq('booking_space_form', 51, $parsed['booking_id']);

    $parsed = parseBf7eArgv(['script', '--pnr=RQFUYD', '--booking=51', '--allow-production-cert-controlled-retrieve', '--skip-send']);
    $eq('allow_on', true, $parsed['allow_production_cert_controlled_retrieve']);
    $eq('skip_send_on', true, $parsed['skip_send']);

    $eq('gate_local_ok', null, resolveAppEnvGate(false, 'local'));
    $eq('gate_production_blocked', 'APP_ENV must be local or testing, or pass --allow-production-cert-controlled-retrieve on production.', resolveAppEnvGate(false, 'production'));
    $eq('gate_production_allowed', null, resolveAppEnvGate(true, 'production'));

    if ($failures !== []) {
        fwrite(STDERR, "CLI parse self-test FAILED:\n".implode("\n", $failures)."\n");

        return 1;
    }

    fwrite(STDERR, "CLI parse self-test OK\n");

    return 0;
}

function flagSnapshot(): array
{
    return [
        'SABRE_BRANDED_FARES_AIRPRICE_BRAND_SHAPE_COMPARE_ENABLED' => (bool) config('suppliers.sabre.branded_fares_airprice_brand_shape_compare_enabled', false),
        'SABRE_VERIFIED_MULTISEG_AUTO_PNR_ENABLED' => (bool) config('suppliers.sabre.verified_multiseg_auto_pnr_enabled', false),
        'SABRE_CPNR_CONNECTING_SAME_CARRIER_PUBLIC_CHECKOUT_ENABLED' => (bool) config('suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled', false),
        'SABRE_TICKETING_ENABLED' => (bool) config('suppliers.sabre.ticketing_enabled', false),
        'SABRE_BRANDED_FARES_PROBE_ENABLED' => (bool) config('suppliers.sabre.branded_fares_probe_enabled', false),
    ];
}

function assertSafetyFlagsOff(): ?string
{
    $snap = flagSnapshot();
    if ($snap['SABRE_TICKETING_ENABLED']) {
        return 'sabre_ticketing_enabled_must_be_false';
    }
    if ($snap['SABRE_VERIFIED_MULTISEG_AUTO_PNR_ENABLED']) {
        return 'sabre_verified_multiseg_auto_pnr_enabled_must_be_false';
    }
    if ($snap['SABRE_CPNR_CONNECTING_SAME_CARRIER_PUBLIC_CHECKOUT_ENABLED']) {
        return 'sabre_cpnr_public_checkout_enabled_must_be_false';
    }
    if ($snap['SABRE_BRANDED_FARES_PROBE_ENABLED']) {
        return 'sabre_branded_fares_probe_enabled_must_be_false';
    }
    if ($snap['SABRE_BRANDED_FARES_AIRPRICE_BRAND_SHAPE_COMPARE_ENABLED']) {
        return 'sabre_branded_fares_airprice_brand_shape_compare_enabled_must_be_false';
    }

    return null;
}

function loadBooking(int $bookingId): Booking
{
    $booking = Booking::query()->find($bookingId);
    if ($booking === null) {
        throw new RuntimeException("Booking {$bookingId} not found.");
    }

    return $booking->loadMissing(['passengers', 'contact', 'fareBreakdown']);
}

function connectionReady(?Booking $booking): array
{
    $cid = (int) data_get($booking?->meta, 'supplier_connection_id', 0);
    $conn = $cid > 0 ? SupplierConnection::query()->find($cid) : SupplierConnection::query()->where('provider', SupplierProvider::Sabre->value)->first();
    if ($conn === null) {
        return ['ok' => false, 'reason' => 'no_sabre_supplier_connection', 'connection' => null, 'is_cert_host' => false, 'resolved_base_host' => 'unknown'];
    }
    $creds = is_array($conn->credentials) ? $conn->credentials : [];
    $hasCreds = trim((string) ($creds['client_id'] ?? '')) !== '' && trim((string) ($creds['client_secret'] ?? '')) !== '';
    $base = SabreInspectGate::resolveSabreBaseUrlContext($conn);
    $isCert = SabreInspectGate::isCertSabreHost((string) ($base['resolved_base_url'] ?? ''));

    return [
        'ok' => $hasCreds && $isCert,
        'reason' => ! $hasCreds ? 'sabre_credentials_missing_on_connection' : (! $isCert ? 'resolved_host_not_cert' : null),
        'connection' => $conn,
        'connection_id' => $conn->id,
        'resolved_base_host' => $base['resolved_base_host'] ?? 'unknown',
        'is_cert_host' => $isCert,
        'has_credentials' => $hasCreds,
    ];
}

/**
 * @return array<string, string>
 */
function buildExpectedFromBooking(Booking $booking): array
{
    $meta = is_array($booking->meta) ? $booking->meta : [];
    $opt = is_array($meta['selected_fare_family_option'] ?? null) ? $meta['selected_fare_family_option'] : [];
    $builder = app(SabreBookingPayloadBuilder::class);
    $brand = $builder->selectedFareFamilyBrandCodeFromBookingMetaForInspect($meta);

    $out = [];
    if (is_string($brand) && trim($brand) !== '') {
        $out['brand_code'] = strtoupper(trim($brand));
    }
    $family = trim((string) ($opt['brand_name'] ?? $opt['name'] ?? ''));
    if ($family !== '') {
        $out['fare_family_name'] = strtoupper($family);
    }
    $fareBasis = trim((string) ($opt['fare_basis'] ?? ''));
    if ($fareBasis !== '') {
        $out['fare_basis'] = strtoupper($fareBasis);
    }
    $bookingClass = trim((string) ($opt['booking_class'] ?? ''));
    if ($bookingClass !== '') {
        $out['booking_class'] = strtoupper($bookingClass);
    }
    $baggage = trim((string) ($opt['baggage'] ?? $opt['baggage_summary'] ?? ''));
    if ($baggage !== '') {
        $out['baggage'] = $baggage;
    }

    return $out;
}

function bf7eTruncateSafe(string $value, int $max = 64): string
{
    $value = trim($value);
    if ($value === '' || strlen($value) <= $max) {
        return $value;
    }

    return substr($value, 0, $max);
}

function bf7eIsSensitiveKeyName(string $key): bool
{
    $lk = strtolower($key);
    foreach ([
        'password', 'token', 'authorization', 'secret', 'pcc', 'pseudo',
        'email', 'phone', 'telephone', 'passport', 'document', 'givenname',
        'surname', 'personname', 'birthdate', 'dateofbirth',
    ] as $frag) {
        if (str_contains($lk, $frag)) {
            return true;
        }
    }

    return false;
}

function bf7eIsPiiBranchKey(string $key): bool
{
    $lk = strtolower($key);
    foreach (BF7E_PII_BRANCH_KEYS as $frag) {
        if ($lk === $frag || str_contains($lk, $frag)) {
            return true;
        }
    }

    return bf7eIsSensitiveKeyName($key);
}

/**
 * @param  list<string>  $fragments
 */
function bf7eKeyMatchesAny(string $lowerKey, array $fragments): bool
{
    foreach ($fragments as $frag) {
        if ($lowerKey === $frag || str_contains($lowerKey, $frag)) {
            return true;
        }
    }

    return false;
}

function bf7ePathHasBrandFragment(string $path): bool
{
    $lower = strtolower($path);

    return str_contains($lower, 'brand')
        || str_contains($lower, 'farefamily')
        || str_contains($lower, 'fare_family');
}

function bf7ePathHasFareBasisFragment(string $path): bool
{
    $lower = strtolower($path);

    return str_contains($lower, 'farebasis')
        || str_contains($lower, 'fare_basis');
}

/**
 * @param  list<string>  $bucket
 */
function bf7ePushUnique(array &$bucket, string $value, int $max = 8): void
{
    $value = trim($value);
    if ($value === '' || in_array($value, $bucket, true)) {
        return;
    }
    if (count($bucket) >= $max) {
        return;
    }
    $bucket[] = $value;
}

/**
 * @param  array<string, mixed>  $json
 * @return array{
 *   fare_basis_codes: list<string>,
 *   brand_or_family: list<string>,
 *   booking_classes: list<string>,
 *   baggage_hints: list<string>,
 *   price_hints: list<string>
 * }
 */
function bf7eExtractFareContext(array $json): array
{
    $fareBasis = [];
    $brandFamily = [];
    $bookingClass = [];
    $baggage = [];
    $price = [];

    $walker = function (mixed $node, string $path, int $depth) use (&$walker, &$fareBasis, &$brandFamily, &$bookingClass, &$baggage, &$price): void {
        if ($depth > 14 || ! is_array($node)) {
            return;
        }

        foreach ($node as $k => $v) {
            if (! is_string($k)) {
                if (is_array($v)) {
                    $walker($v, $path, $depth + 1);
                }

                continue;
            }
            if (bf7eIsPiiBranchKey($k)) {
                continue;
            }

            $childPath = $path === '' ? $k : $path.'.'.$k;
            $lk = strtolower($k);

            if (is_scalar($v) && trim((string) $v) !== '') {
                $raw = trim((string) $v);
                if (bf7eKeyMatchesAny($lk, ['farebasis', 'fare_basis', 'farebasiscode'])
                    || ($lk === 'code' && bf7ePathHasFareBasisFragment($path))) {
                    bf7ePushUnique($fareBasis, strtoupper(bf7eTruncateSafe($raw, 24)));
                }
                if (bf7eKeyMatchesAny($lk, ['brandcode', 'brand', 'farefamily', 'farefamilyname', 'farefamilycode'])) {
                    bf7ePushUnique($brandFamily, strtoupper(bf7eTruncateSafe($raw, 32)));
                }
                if ($lk === 'content' && bf7ePathHasBrandFragment($path)) {
                    bf7ePushUnique($brandFamily, strtoupper(bf7eTruncateSafe($raw, 32)));
                }
                if (bf7eKeyMatchesAny($lk, ['resbookdesigcode', 'bookingclass', 'classofservice'])) {
                    bf7ePushUnique($bookingClass, strtoupper(bf7eTruncateSafe($raw, 4)));
                }
                if (bf7eKeyMatchesAny($lk, ['baggage', 'allowance', 'weight', 'kg', 'piece'])) {
                    bf7ePushUnique($baggage, bf7eTruncateSafe($raw, 32));
                }
                if (bf7eKeyMatchesAny($lk, ['totalfare', 'amount', 'currency', 'totalprice', 'equivfare'])) {
                    bf7ePushUnique($price, bf7eTruncateSafe($raw, 24));
                }
            }

            if (is_array($v)) {
                $walker($v, $childPath, $depth + 1);
            }
        }
    };

    $walker($json, '', 0);

    return [
        'fare_basis_codes' => $fareBasis,
        'brand_or_family' => $brandFamily,
        'booking_classes' => $bookingClass,
        'baggage_hints' => $baggage,
        'price_hints' => $price,
    ];
}

/**
 * @return array{http_status: int, json: array<string, mixed>|null, error: ?string}
 */
function bf7eLiveRetrieveJson(SupplierConnection $connection, string $path, string $pnr): array
{
    $base = rtrim((string) ($connection->base_url ?: config('suppliers.sabre.default_base_url')), '/');
    if (! SabreInspectGate::isCertSabreHost($base)) {
        return ['http_status' => 0, 'json' => null, 'error' => 'cert_host_blocked'];
    }

    try {
        $client = app(SabreClient::class);
        $token = $client->getAccessToken($connection);
        $timeouts = $client->httpTimeoutSettings();
        $body = str_contains(strtolower($path), 'getbooking')
            ? ['confirmationId' => $pnr]
            : ['recordLocator' => $pnr];
        $url = $base.(str_starts_with($path, '/') ? $path : '/'.$path);
        $response = Http::withToken((string) $token)
            ->acceptJson()
            ->timeout($timeouts['timeout_seconds'])
            ->connectTimeout($timeouts['connect_timeout_seconds'])
            ->withBody((string) json_encode($body), 'application/json')
            ->post($url);
        $httpStatus = $response->status();
        $decoded = $response->json();

        return [
            'http_status' => $httpStatus,
            'json' => is_array($decoded) ? $decoded : null,
            'error' => null,
        ];
    } catch (ConnectionException) {
        return ['http_status' => 0, 'json' => null, 'error' => 'network_or_timeout'];
    } catch (Throwable $e) {
        return ['http_status' => 0, 'json' => null, 'error' => 'retrieve_failed'];
    }
}

/**
 * @param  list<array<string, mixed>>  $segments
 * @return array<string, mixed>
 */
function bf7eBuildItinerarySegments(array $segments): array
{
    $out = [];
    foreach ($segments as $segment) {
        if (! is_array($segment)) {
            continue;
        }
        $out[] = array_filter([
            'origin' => (string) ($segment['origin'] ?? ''),
            'destination' => (string) ($segment['destination'] ?? ''),
            'departure_at' => (string) ($segment['departure_at'] ?? ''),
            'arrival_at' => (string) ($segment['arrival_at'] ?? ''),
            'carrier' => (string) ($segment['marketing_airline'] ?? $segment['airline_code'] ?? ''),
            'flight_number' => (string) ($segment['flight_number'] ?? ''),
            'booking_class' => (string) ($segment['booking_class'] ?? ''),
            'segment_status' => (string) ($segment['segment_status'] ?? $segment['flight_status_code'] ?? ''),
        ], static fn (string $v): bool => $v !== '');
    }

    return $out;
}

/**
 * @param  array<string, string>  $expected
 * @param  array<string, mixed>  $fareContext
 * @param  list<array<string, mixed>>  $itinerarySegments
 * @return array<string, bool|null>
 */
function bf7eComputeExpectedMatch(array $expected, array $fareContext, array $itinerarySegments): array
{
    $haystack = [];
    foreach (['fare_basis_codes', 'brand_or_family', 'booking_classes', 'baggage_hints'] as $key) {
        foreach ((array) ($fareContext[$key] ?? []) as $val) {
            if (is_string($val) && $val !== '') {
                $haystack[] = strtoupper($val);
            }
        }
    }
    foreach ($itinerarySegments as $seg) {
        $bc = strtoupper(trim((string) ($seg['booking_class'] ?? '')));
        if ($bc !== '') {
            $haystack[] = $bc;
        }
    }
    $blob = implode('|', $haystack);

    $matchField = static function (?string $expectedValue) use ($blob): ?bool {
        if ($expectedValue === null || trim($expectedValue) === '') {
            return null;
        }
        $needle = strtoupper(trim($expectedValue));

        return str_contains($blob, $needle);
    };

    return [
        'brand_code_matches' => $matchField($expected['brand_code'] ?? null),
        'fare_family_name_matches' => $matchField($expected['fare_family_name'] ?? null),
        'fare_basis_matches' => $matchField($expected['fare_basis'] ?? null),
        'booking_class_matches' => $matchField($expected['booking_class'] ?? null),
        'baggage_matches' => $matchField($expected['baggage'] ?? null),
    ];
}

/**
 * @param  array<string, mixed>  $retrieveSummary
 * @param  array<string, mixed>|null  $bestRow
 */
function bf7eInferPnrActiveInCert(array $retrieveSummary, ?array $bestRow): string
{
    $statuses = array_map('strtoupper', (array) ($retrieveSummary['segment_statuses'] ?? []));
    if ($statuses !== [] && in_array('HK', $statuses, true)) {
        return 'likely_active';
    }

    $statusSummary = is_array($bestRow['get_booking_status_summary'] ?? null)
        ? $bestRow['get_booking_status_summary']
        : [];
    if (($statusSummary['is_cancelable_value'] ?? null) === true) {
        return 'likely_active';
    }
    if (($statusSummary['is_cancelable_value'] ?? null) === false
        && (int) ($retrieveSummary['segment_count'] ?? 0) === 0) {
        return 'likely_cancelled';
    }
    if (($retrieveSummary['retrieve_success'] ?? false) === true) {
        return 'likely_active';
    }

    return 'unknown';
}

/**
 * @param  array<string, mixed>  $fareContextA
 * @param  array<string, mixed>  $fareContextB
 */
function bf7eMergeFareContext(array $fareContextA, array $fareContextB): array
{
    $merged = $fareContextA;
    foreach (['fare_basis_codes', 'brand_or_family', 'booking_classes', 'baggage_hints', 'price_hints'] as $key) {
        foreach ((array) ($fareContextB[$key] ?? []) as $val) {
            if (! is_string($val) || $val === '') {
                continue;
            }
            bf7ePushUnique($merged[$key], $val);
        }
    }

    return $merged;
}

/**
 * @param  list<array<string, mixed>>  $attempted
 * @return list<array{endpoint_path: string, http_status: int, available: bool}>
 */
function bf7eSummarizeAttemptedEndpoints(array $attempted): array
{
    $out = [];
    foreach ($attempted as $row) {
        if (! is_array($row)) {
            continue;
        }
        $out[] = [
            'endpoint_path' => (string) ($row['endpoint_path'] ?? ''),
            'http_status' => (int) ($row['http_status'] ?? 0),
            'available' => ($row['available'] ?? false) === true,
        ];
    }

    return $out;
}

/**
 * @param  array<string, mixed>  $preflight
 */
function emitPreflightToStderr(array $preflight): void
{
    $lines = [
        '--- BF7-E preflight ---',
        'app_env='.$preflight['app_env'],
        'allow_production_cert_controlled_retrieve='.($preflight['allow_production_cert_controlled_retrieve'] ? 'true' : 'false'),
        'booking_id='.$preflight['booking_id'],
        'pnr='.$preflight['pnr'],
        'endpoint_host='.($preflight['endpoint_host'] ?? 'unknown'),
        'expected_brand_code='.($preflight['expected_brand_code'] ?? 'null'),
        '---',
    ];
    fwrite(STDERR, implode(PHP_EOL, $lines).PHP_EOL);
}

$cli = parseBf7eArgv($argv ?? []);
if ($cli['self_test_cli_parse']) {
    exit(runBf7eCliParseSelfTest());
}

$bf7eDirectInvocation = PHP_SAPI === 'cli'
    && isset($argv[0])
    && realpath($argv[0]) === realpath(__FILE__);

if (! $bf7eDirectInvocation) {
    return;
}

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$appEnv = (string) config('app.env');
$allowProduction = $cli['allow_production_cert_controlled_retrieve'];
$skipSend = $cli['skip_send'];
$pnrOpt = $cli['pnr'];
$bookingOpt = $cli['booking_id'];

$report = [
    'sprint' => 'SABRE-BRANDED-FARES-BF7-E',
    'app_env' => $appEnv,
    'allow_production_cert_controlled_retrieve' => $allowProduction,
    'flags_snapshot' => flagSnapshot(),
    'booking_id' => $bookingOpt,
    'pnr' => $pnrOpt,
    'endpoint_host' => null,
    'expected' => null,
    'pnr_exists' => false,
    'retrieve_success' => false,
    'best_endpoint' => null,
    'attempted_endpoints_summary' => [],
    'itinerary_segments' => [],
    'fare_context' => null,
    'price_present' => false,
    'payment_present' => false,
    'ticketing_present' => false,
    'ticket_numbers_present' => false,
    'warnings_errors_sanitized' => [],
    'pnr_active_in_cert' => 'unknown',
    'expected_match' => null,
    'retrieve_summary' => null,
    'status' => 'pending',
];

try {
    if ($pnrOpt === null || ! preg_match('/^[A-Z0-9]{5,8}$/', $pnrOpt)) {
        throw new RuntimeException('Missing or invalid --pnr={locator} (5-8 alphanumeric).');
    }

    if ($bookingOpt === null || $bookingOpt <= 0) {
        throw new RuntimeException('Missing --booking={id} (e.g. --booking=51).');
    }

    if (in_array($bookingOpt, BF7E_BLOCKED_BOOKING_IDS, true)) {
        throw new RuntimeException('booking_id_blocked_for_bf7e');
    }

    $envGate = resolveAppEnvGate($allowProduction, $appEnv);
    if ($envGate !== null) {
        throw new RuntimeException($envGate);
    }

    $flagBlock = assertSafetyFlagsOff();
    if ($flagBlock !== null) {
        throw new RuntimeException($flagBlock);
    }

    $booking = loadBooking($bookingOpt);
    $report['expected'] = buildExpectedFromBooking($booking);

    $connInfo = connectionReady($booking);
    if (! ($connInfo['ok'] ?? false)) {
        throw new RuntimeException((string) ($connInfo['reason'] ?? 'connection_not_ready'));
    }

    /** @var SupplierConnection $connection */
    $connection = $connInfo['connection'];
    $report['endpoint_host'] = $connInfo['resolved_base_host'] ?? 'unknown';

    $preflight = [
        'app_env' => $appEnv,
        'allow_production_cert_controlled_retrieve' => $allowProduction,
        'booking_id' => $bookingOpt,
        'pnr' => $pnrOpt,
        'endpoint_host' => $report['endpoint_host'],
        'expected_brand_code' => $report['expected']['brand_code'] ?? null,
    ];

    if ($skipSend) {
        $report['status'] = 'inspect_only';
    } else {
        emitPreflightToStderr($preflight);

        $probe = app(SabrePnrRetrieveProbe::class);
        $probePayload = $probe->probeDirectPnr(
            $connection,
            $pnrOpt,
            true,
            null,
            'auto',
            false,
            true,
            true,
        );

        if (isset($probePayload['error'])) {
            throw new RuntimeException((string) $probePayload['error']);
        }

        $retrieveSummary = is_array($probePayload['retrieve_summary'] ?? null)
            ? $probePayload['retrieve_summary']
            : [];
        $report['retrieve_summary'] = $retrieveSummary;
        $report['best_endpoint'] = $probePayload['best_candidate_endpoint'] ?? null;
        $report['attempted_endpoints_summary'] = bf7eSummarizeAttemptedEndpoints(
            is_array($probePayload['attempted_endpoints'] ?? null) ? $probePayload['attempted_endpoints'] : []
        );

        $attempted = is_array($probePayload['attempted_endpoints'] ?? null) ? $probePayload['attempted_endpoints'] : [];
        $bestRow = null;
        $bestPath = (string) ($probePayload['best_candidate_endpoint'] ?? '');
        foreach ($attempted as $row) {
            if (is_array($row) && (string) ($row['endpoint_path'] ?? '') === $bestPath) {
                $bestRow = $row;
                break;
            }
        }

        $mapPreview = is_array($bestRow['map_preview'] ?? null) ? $bestRow['map_preview'] : [];
        $candidateRows = is_array($mapPreview['candidate_rows'] ?? null) ? $mapPreview['candidate_rows'] : [];
        $report['itinerary_segments'] = bf7eBuildItinerarySegments($candidateRows);

        $report['retrieve_success'] = ($retrieveSummary['retrieve_success'] ?? false) === true;
        $report['pnr_exists'] = $report['retrieve_success']
            || in_array((int) ($retrieveSummary['http_status'] ?? 0), [200, 201], true);
        $report['ticketing_present'] = ($retrieveSummary['ticketing_present'] ?? false) === true;
        $report['ticket_numbers_present'] = ($retrieveSummary['ticket_numbers_present'] ?? false) === true;
        $report['warnings_errors_sanitized'] = array_values(array_slice(
            (array) ($retrieveSummary['warnings_errors_sanitized'] ?? []),
            0,
            16
        ));
        $report['pnr_active_in_cert'] = bf7eInferPnrActiveInCert($retrieveSummary, $bestRow);

        $topKeys = array_map('strtolower', (array) ($bestRow['top_level_keys_sanitized'] ?? []));
        $report['payment_present'] = in_array('payments', $topKeys, true) || in_array('payment', $topKeys, true);
        $report['price_present'] = in_array('fares', $topKeys, true)
            || in_array('fareoffers', $topKeys, true)
            || in_array('totalfare', $topKeys, true);

        $fareContext = [
            'fare_basis_codes' => [],
            'brand_or_family' => [],
            'booking_classes' => [],
            'baggage_hints' => [],
            'price_hints' => [],
        ];

        $pathsToExtract = [BF7E_PASSENGER_RECORDS_READ_PATH, BF7E_GET_BOOKING_PATH];
        $bestEndpoint = (string) ($report['best_endpoint'] ?? '');
        if ($bestEndpoint !== '' && ! in_array($bestEndpoint, $pathsToExtract, true)) {
            $pathsToExtract[] = $bestEndpoint;
        }

        foreach ($pathsToExtract as $extractPath) {
            $live = bf7eLiveRetrieveJson($connection, $extractPath, $pnrOpt);
            if (! is_array($live['json']) || ! in_array((int) $live['http_status'], [200, 201], true)) {
                continue;
            }
            $fareContext = bf7eMergeFareContext($fareContext, bf7eExtractFareContext($live['json']));

            if ($extractPath === BF7E_GET_BOOKING_PATH && $report['itinerary_segments'] === []) {
                $mapper = app(SabreTripOrdersGetBookingItineraryMapper::class);
                $preview = $mapper->mapPreview($live['json'], ['http_status' => (int) $live['http_status']]);
                $rows = is_array($preview['candidate_rows'] ?? null) ? $preview['candidate_rows'] : [];
                $report['itinerary_segments'] = bf7eBuildItinerarySegments($rows);
            }
        }

        $report['fare_context'] = $fareContext;
        $report['expected_match'] = bf7eComputeExpectedMatch(
            is_array($report['expected']) ? $report['expected'] : [],
            $fareContext,
            $report['itinerary_segments'],
        );

        if (! $report['price_present'] && $fareContext['price_hints'] !== []) {
            $report['price_present'] = true;
        }

        $report['status'] = 'retrieve_completed';
    }
} catch (Throwable $e) {
    if ($report['status'] === 'pending') {
        $report['status'] = 'error';
    }
    $report['error'] = $e->getMessage();
    $report['error_class'] = $e::class;
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;

$exitOk = in_array($report['status'], ['retrieve_completed', 'inspect_only'], true);
exit($exitOk ? 0 : 1);
