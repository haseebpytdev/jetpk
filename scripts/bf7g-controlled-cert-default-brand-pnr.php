<?php

/**
 * BF7-G controlled CERT PNR create using production default Brand wire (gate OFF).
 *
 * Usage:
 *   php scripts/bf7g-controlled-cert-default-brand-pnr.php --booking=51 [--skip-send]
 *   php scripts/bf7g-controlled-cert-default-brand-pnr.php --booking 51 --allow-production-cert-controlled-send
 *   php scripts/bf7g-controlled-cert-default-brand-pnr.php --self-test-cli-parse
 */

declare(strict_types=1);

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Config;

/**
 * @return array{
 *     booking_id: int|null,
 *     skip_send: bool,
 *     allow_production_cert_controlled_send: bool,
 *     self_test_cli_parse: bool
 * }
 */
function parseBf7gArgv(array $argv): array
{
    $bookingId = null;
    $skipSend = false;
    $allowProductionCertControlledSend = false;
    $selfTestCliParse = false;

    $args = array_slice($argv, 1);
    for ($i = 0; $i < count($args); $i++) {
        $arg = $args[$i];
        if ($arg === '--self-test-cli-parse') {
            $selfTestCliParse = true;
        } elseif ($arg === '--skip-send') {
            $skipSend = true;
        } elseif ($arg === '--allow-production-cert-controlled-send') {
            $allowProductionCertControlledSend = true;
        } elseif (str_starts_with($arg, '--booking=')) {
            $bookingId = (int) substr($arg, 10);
        } elseif ($arg === '--booking' && isset($args[$i + 1]) && is_numeric($args[$i + 1])) {
            $bookingId = (int) $args[$i + 1];
            $i++;
        }
    }

    return [
        'booking_id' => $bookingId !== null && $bookingId > 0 ? $bookingId : null,
        'skip_send' => $skipSend,
        'allow_production_cert_controlled_send' => $allowProductionCertControlledSend,
        'self_test_cli_parse' => $selfTestCliParse,
    ];
}

function resolveBf7gAppEnvGate(bool $allowProductionFlag, string $appEnv): ?string
{
    if (in_array($appEnv, ['local', 'testing'], true)) {
        return null;
    }

    if ($appEnv === 'production' && $allowProductionFlag) {
        return null;
    }

    return 'APP_ENV must be local or testing, or pass --allow-production-cert-controlled-send on production.';
}

function runBf7gCliParseSelfTest(): int
{
    $failures = [];

    $eq = static function (string $label, mixed $expected, mixed $actual) use (&$failures): void {
        if ($expected !== $actual) {
            $failures[] = $label.': expected '.json_encode($expected).', got '.json_encode($actual);
        }
    };

    $parsed = parseBf7gArgv(['script', '--booking=51']);
    $eq('booking_equals_form', 51, $parsed['booking_id']);

    $parsed = parseBf7gArgv(['script', '--booking', '51']);
    $eq('booking_space_form', 51, $parsed['booking_id']);

    $parsed = parseBf7gArgv(['script', '--booking=51', '--allow-production-cert-controlled-send', '--skip-send']);
    $eq('allow_on', true, $parsed['allow_production_cert_controlled_send']);
    $eq('skip_send_on', true, $parsed['skip_send']);

    $eq('gate_local_ok', null, resolveBf7gAppEnvGate(false, 'local'));
    $eq('gate_production_blocked', 'APP_ENV must be local or testing, or pass --allow-production-cert-controlled-send on production.', resolveBf7gAppEnvGate(false, 'production'));
    $eq('gate_production_allowed', null, resolveBf7gAppEnvGate(true, 'production'));

    if ($failures !== []) {
        fwrite(STDERR, "CLI parse self-test FAILED:\n".implode("\n", $failures)."\n");

        return 1;
    }

    fwrite(STDERR, "CLI parse self-test OK\n");

    return 0;
}

function bf7gFlagSnapshot(): array
{
    return [
        'SABRE_BRANDED_FARES_AIRPRICE_BRAND_SHAPE_COMPARE_ENABLED' => (bool) config('suppliers.sabre.branded_fares_airprice_brand_shape_compare_enabled', false),
        'SABRE_BRANDED_FARES_AIRPRICE_BRAND_SHAPE_COMPARE_VARIANT' => (string) config('suppliers.sabre.branded_fares_airprice_brand_shape_compare_variant', 'current_object_code'),
        'SABRE_VERIFIED_MULTISEG_AUTO_PNR_ENABLED' => (bool) config('suppliers.sabre.verified_multiseg_auto_pnr_enabled', false),
        'SABRE_CPNR_CONNECTING_SAME_CARRIER_PUBLIC_CHECKOUT_ENABLED' => (bool) config('suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled', false),
        'SABRE_TICKETING_ENABLED' => (bool) config('suppliers.sabre.ticketing_enabled', false),
        'SABRE_BRANDED_FARES_PROBE_ENABLED' => (bool) config('suppliers.sabre.branded_fares_probe_enabled', false),
        'SABRE_BOOKING_ENABLED' => (bool) config('suppliers.sabre.booking_enabled', false),
        'SABRE_BOOKING_LIVE_CALL_ENABLED' => (bool) config('suppliers.sabre.booking_live_call_enabled', false),
    ];
}

function bf7gApplyFlagSnapshot(array $snapshot): void
{
    $boolMap = [
        'SABRE_BRANDED_FARES_AIRPRICE_BRAND_SHAPE_COMPARE_ENABLED' => 'suppliers.sabre.branded_fares_airprice_brand_shape_compare_enabled',
        'SABRE_VERIFIED_MULTISEG_AUTO_PNR_ENABLED' => 'suppliers.sabre.verified_multiseg_auto_pnr_enabled',
        'SABRE_CPNR_CONNECTING_SAME_CARRIER_PUBLIC_CHECKOUT_ENABLED' => 'suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled',
        'SABRE_TICKETING_ENABLED' => 'suppliers.sabre.ticketing_enabled',
        'SABRE_BRANDED_FARES_PROBE_ENABLED' => 'suppliers.sabre.branded_fares_probe_enabled',
        'SABRE_BOOKING_ENABLED' => 'suppliers.sabre.booking_enabled',
        'SABRE_BOOKING_LIVE_CALL_ENABLED' => 'suppliers.sabre.booking_live_call_enabled',
    ];

    foreach ($boolMap as $envKey => $configKey) {
        $val = (bool) ($snapshot[$envKey] ?? false);
        putenv($envKey.'='.($val ? 'true' : 'false'));
        Config::set($configKey, $val);
    }

    $variant = (string) ($snapshot['SABRE_BRANDED_FARES_AIRPRICE_BRAND_SHAPE_COMPARE_VARIANT'] ?? 'current_object_code');
    putenv('SABRE_BRANDED_FARES_AIRPRICE_BRAND_SHAPE_COMPARE_VARIANT='.$variant);
    Config::set('suppliers.sabre.branded_fares_airprice_brand_shape_compare_variant', $variant);
}

function setBf7gProductionDefaultFlags(): void
{
    putenv('SABRE_BRANDED_FARES_AIRPRICE_BRAND_SHAPE_COMPARE_ENABLED=false');
    putenv('SABRE_TICKETING_ENABLED=false');
    putenv('SABRE_VERIFIED_MULTISEG_AUTO_PNR_ENABLED=false');
    putenv('SABRE_CPNR_CONNECTING_SAME_CARRIER_PUBLIC_CHECKOUT_ENABLED=false');
    putenv('SABRE_BRANDED_FARES_PROBE_ENABLED=false');
    Config::set('suppliers.sabre.branded_fares_airprice_brand_shape_compare_enabled', false);
    Config::set('suppliers.sabre.ticketing_enabled', false);
    Config::set('suppliers.sabre.verified_multiseg_auto_pnr_enabled', false);
    Config::set('suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled', false);
    Config::set('suppliers.sabre.branded_fares_probe_enabled', false);
}

function setBf7gTemporaryBookingSendFlags(): void
{
    putenv('SABRE_BOOKING_ENABLED=true');
    putenv('SABRE_BOOKING_LIVE_CALL_ENABLED=true');
    Config::set('suppliers.sabre.booking_enabled', true);
    Config::set('suppliers.sabre.booking_live_call_enabled', true);
}

function bf7gAssertSafetyFlagsOff(): ?string
{
    $snap = bf7gFlagSnapshot();
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

function bf7gAttemptCountsSnapshot(): array
{
    try {
        return [
            'supplier_booking_attempts' => SupplierBookingAttempt::query()->count(),
            'create_pnr' => SupplierBookingAttempt::query()->where('action', 'create_pnr')->count(),
            'compare_booking_endpoint' => SupplierBookingAttempt::query()->where('action', 'compare_booking_endpoint')->count(),
        ];
    } catch (Throwable) {
        return [
            'supplier_booking_attempts' => null,
            'create_pnr' => null,
            'compare_booking_endpoint' => null,
        ];
    }
}

function bf7gLoadBooking(int $bookingId): Booking
{
    $booking = Booking::query()->find($bookingId);
    if ($booking === null) {
        throw new RuntimeException("Booking {$bookingId} not found.");
    }

    return $booking->loadMissing(['passengers', 'contact', 'fareBreakdown']);
}

function bf7gConnectionReady(?Booking $booking): array
{
    $cid = (int) data_get($booking?->meta, 'supplier_connection_id', 0);
    $conn = $cid > 0 ? SupplierConnection::query()->find($cid) : SupplierConnection::query()->where('provider', SupplierProvider::Sabre->value)->first();
    if ($conn === null) {
        return ['ok' => false, 'reason' => 'no_sabre_supplier_connection', 'is_cert_host' => false, 'resolved_base_host' => 'unknown'];
    }
    $creds = is_array($conn->credentials) ? $conn->credentials : [];
    $hasCreds = trim((string) ($creds['client_id'] ?? '')) !== '' && trim((string) ($creds['client_secret'] ?? '')) !== '';
    $base = SabreInspectGate::resolveSabreBaseUrlContext($conn);
    $isCert = SabreInspectGate::isCertSabreHost((string) ($base['resolved_base_url'] ?? ''));

    return [
        'ok' => $hasCreds && $isCert,
        'reason' => ! $hasCreds ? 'sabre_credentials_missing_on_connection' : (! $isCert ? 'resolved_host_not_cert' : null),
        'connection_id' => $conn->id,
        'resolved_base_host' => $base['resolved_base_host'] ?? 'unknown',
        'is_cert_host' => $isCert,
        'has_credentials' => $hasCreds,
    ];
}

function bf7gResolveBookingBrandCode(Booking $booking): ?string
{
    $meta = is_array($booking->meta) ? $booking->meta : [];

    return app(SabreBookingPayloadBuilder::class)->selectedFareFamilyBrandCodeFromBookingMetaForInspect($meta);
}

function bf7gBookingHasFareFamily(Booking $booking): bool
{
    $meta = is_array($booking->meta) ? $booking->meta : [];
    $opt = $meta['selected_fare_family_option'] ?? null;
    if (! is_array($opt)) {
        return false;
    }

    return trim((string) ($opt['name'] ?? '')) !== '' || trim((string) ($opt['code'] ?? '')) !== '';
}

function bf7gFlagsRestored(array $before, array $after): bool
{
    foreach ($before as $key => $value) {
        if (! array_key_exists($key, $after) || $after[$key] !== $value) {
            return false;
        }
    }

    return true;
}

/**
 * @param  array<string, mixed>  $diag
 */
function bf7gAssertProductionDefaultBrandDiagnostics(array $diag): ?string
{
    if (($diag['compare_gate_enabled'] ?? true) !== false) {
        return 'compare_gate_must_be_off';
    }
    if (($diag['active_brand_shape_selector'] ?? '') !== SabreBookingPayloadBuilder::DEFAULT_AIRPRICE_BRAND_SHAPE_SELECTOR) {
        return 'active_brand_shape_selector_not_object_content';
    }
    if (($diag['default_brand_node_shape'] ?? '') !== 'array_of_content_objects') {
        return 'default_brand_node_shape_not_array_of_content_objects';
    }
    if (($diag['current_brand_node_shape'] ?? '') !== 'array_of_content_objects') {
        return 'current_brand_node_shape_not_array_of_content_objects';
    }

    return null;
}

/**
 * @param  list<string>  $paths
 * @param  list<string>  $messages
 */
function bf7gBrandPointerErrorPresent(array $paths, array $messages): bool
{
    $pointer = SabreBookingPayloadBuilder::AIRPRICE_BRAND_REJECTED_POINTER;
    foreach ($paths as $path) {
        $p = (string) $path;
        if ($p !== '' && (str_contains($p, 'Brand/0') || str_contains($p, $pointer))) {
            return true;
        }
    }

    $blob = strtolower(json_encode($messages, JSON_UNESCAPED_SLASHES) ?: '[]');

    return str_contains($blob, 'brand/0');
}

/**
 * @param  list<string>  $messages
 */
function bf7gBrandCodePropertyErrorPresent(array $paths, array $messages): bool
{
    foreach ($messages as $message) {
        $msg = strtolower((string) $message);
        if (str_contains($msg, 'object instance has properties')) {
            return true;
        }
    }

    return false;
}

/**
 * @param  array<string, mixed>  $row
 */
function bf7gClassifySabreRow(array $row): string
{
    if (isset($row['classification']) && is_string($row['classification']) && trim($row['classification']) !== '') {
        return trim($row['classification']);
    }

    $httpStatus = (string) ($row['http_status'] ?? '');
    $pnrCreated = ($row['pnr_created'] ?? false) === true;
    $paths = array_map('strval', (array) ($row['response_error_paths'] ?? []));
    $messages = array_map('strval', (array) ($row['response_error_messages'] ?? []));

    if ($httpStatus === '422') {
        if (bf7gBrandCodePropertyErrorPresent($paths, $messages) && bf7gBrandPointerErrorPresent($paths, $messages)) {
            return 'brand_shape_still_rejected_422';
        }

        return 'validation_error_422_other';
    }

    if ($httpStatus === '200' || $pnrCreated) {
        return 'brand_shape_accepted_pnr_or_200';
    }

    if ($httpStatus !== '' && $httpStatus !== 'not_sent') {
        return 'http_'.$httpStatus;
    }

    $status = (string) ($row['status'] ?? '');

    return $status !== '' ? $status : 'unknown';
}

/**
 * @param  array<string, mixed>  $preflight
 */
function bf7gEmitPreflightToStderr(array $preflight): void
{
    $lines = [
        '--- BF7-G preflight ---',
        'app_env='.$preflight['app_env'],
        'allow_production_cert_controlled_send='.($preflight['allow_production_cert_controlled_send'] ? 'true' : 'false'),
        'booking_id='.$preflight['booking_id'],
        'booking_reference='.($preflight['booking_reference'] ?? 'null'),
        'brand_code='.($preflight['brand_code'] ?? 'null'),
        'default_brand_shape_selector='.($preflight['default_brand_shape_selector'] ?? 'null'),
        'active_brand_shape_selector='.($preflight['active_brand_shape_selector'] ?? 'null'),
        'default_brand_node_shape='.($preflight['default_brand_node_shape'] ?? 'null'),
        'current_brand_node_shape='.($preflight['current_brand_node_shape'] ?? 'null'),
        'compare_gate_enabled='.($preflight['compare_gate_enabled'] ? 'true' : 'false'),
        'endpoint_host='.($preflight['endpoint_host'] ?? 'unknown'),
        'endpoint_path='.($preflight['endpoint_path'] ?? 'unknown'),
        '---',
    ];
    fwrite(STDERR, implode(PHP_EOL, $lines).PHP_EOL);
}

$cli = parseBf7gArgv($argv ?? []);
if ($cli['self_test_cli_parse']) {
    exit(runBf7gCliParseSelfTest());
}

$bf7gDirectInvocation = PHP_SAPI === 'cli'
    && isset($argv[0])
    && realpath($argv[0]) === realpath(__FILE__);

if (! $bf7gDirectInvocation) {
    return;
}

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

const BF7G_ENDPOINT_PATH = SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH;
const BF7G_PAYLOAD_STYLE = SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS;
const BF7G_BLOCKED_BOOKING_IDS = [43, 46];

/** @var array<string, mixed>|null */
$bf7gOriginalFlagSnapshot = null;

$appEnv = (string) config('app.env');
$allowProduction = $cli['allow_production_cert_controlled_send'];
$skipSend = $cli['skip_send'];
$bookingOpt = $cli['booking_id'];

$bf7gOriginalFlagSnapshot = bf7gFlagSnapshot();

$report = [
    'sprint' => 'SABRE-BRANDED-FARES-BF7-G',
    'app_env' => $appEnv,
    'allow_production_cert_controlled_send' => $allowProduction,
    'flags_before' => $bf7gOriginalFlagSnapshot,
    'flags_during_test' => null,
    'flags_after' => null,
    'flags_restored' => null,
    'booking_id' => $bookingOpt,
    'booking_reference' => null,
    'brand_code' => null,
    'default_brand_shape_selector' => SabreBookingPayloadBuilder::DEFAULT_AIRPRICE_BRAND_SHAPE_SELECTOR,
    'active_brand_shape_selector' => null,
    'default_brand_node_shape' => 'array_of_content_objects',
    'current_brand_node_shape' => null,
    'compare_gate_enabled' => false,
    'endpoint_path' => BF7G_ENDPOINT_PATH,
    'endpoint_host' => null,
    'payload_style' => BF7G_PAYLOAD_STYLE,
    'attempt_counts_before' => bf7gAttemptCountsSnapshot(),
    'attempt_counts_after' => null,
    'brand_node_preview_safe' => null,
    'http_status' => null,
    'sabre_classification' => null,
    'response_top_level_message' => null,
    'response_error_paths' => null,
    'brand_pointer_error_present' => null,
    'brand_code_property_error_present' => null,
    'pnr_created' => false,
    'pnr' => null,
    'status' => 'pending',
];

try {
    if ($bookingOpt === null || $bookingOpt <= 0) {
        throw new RuntimeException('Missing --booking={id} (e.g. --booking=51 or --booking 51).');
    }

    $envBlock = resolveBf7gAppEnvGate($allowProduction, $appEnv);
    if ($envBlock !== null) {
        throw new RuntimeException($envBlock);
    }

    if (in_array($bookingOpt, BF7G_BLOCKED_BOOKING_IDS, true)) {
        $report['status'] = 'blocked_precheck';
        $report['sabre_classification'] = 'blocked_booking_id_43_or_46';
        throw new RuntimeException('Booking id '.$bookingOpt.' is blocked (43 and 46 are not allowed).');
    }

    if (BF7G_ENDPOINT_PATH !== SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH) {
        $report['status'] = 'blocked_precheck';
        $report['sabre_classification'] = 'endpoint_path_not_allowed';
        throw new RuntimeException('Endpoint path must be '.SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH);
    }

    $safetyBlock = bf7gAssertSafetyFlagsOff();
    if ($safetyBlock !== null) {
        $report['status'] = 'blocked_precheck';
        $report['sabre_classification'] = $safetyBlock;
        throw new RuntimeException('Safety flag check failed: '.$safetyBlock);
    }

    $booking = bf7gLoadBooking($bookingOpt);
    $report['booking_id'] = $booking->id;
    $report['booking_reference'] = $booking->booking_reference ?? null;

    $brandCode = bf7gResolveBookingBrandCode($booking);
    $hasFareFamily = bf7gBookingHasFareFamily($booking);
    $report['brand_code'] = $brandCode;

    if (! $hasFareFamily) {
        $report['status'] = 'blocked_precheck';
        $report['sabre_classification'] = 'selected_fare_family_missing';
        throw new RuntimeException('Booking must have selected_fare_family_option present.');
    }

    if ($brandCode !== 'FL') {
        $report['status'] = 'blocked_precheck';
        $report['sabre_classification'] = 'brand_code_not_fl';
        throw new RuntimeException('Controlled BF7-G test requires brand_code FL (got '.($brandCode ?? 'null').').');
    }

    $connCheck = bf7gConnectionReady($booking);
    $report['endpoint_host'] = $connCheck['resolved_base_host'] ?? null;

    if (! $skipSend) {
        if (! ($connCheck['has_credentials'] ?? false)) {
            $report['status'] = 'blocked_precheck';
            $report['sabre_classification'] = 'sabre_credentials_missing_on_connection';
            throw new RuntimeException('Sabre credentials missing on supplier connection.');
        }
        if (($connCheck['is_cert_host'] ?? false) !== true) {
            $report['status'] = 'blocked_precheck';
            $report['sabre_classification'] = 'resolved_host_not_cert';
            throw new RuntimeException('Resolved Sabre host is not CERT.');
        }
    }

    setBf7gProductionDefaultFlags();
    $report['flags_during_test'] = bf7gFlagSnapshot();

    /** @var SabreBookingService $sabreBooking */
    $sabreBooking = app(SabreBookingService::class);

    $diag = $sabreBooking->inspectPassengerRecordsAirPriceBrandDiagnosticsForCommand($booking, BF7G_PAYLOAD_STYLE);

    if (is_array($diag)) {
        $report['current_brand_node_shape'] = $diag['current_brand_node_shape'] ?? null;
        $report['active_brand_shape_selector'] = $diag['active_brand_shape_selector'] ?? null;
        $report['default_brand_node_shape'] = $diag['default_brand_node_shape'] ?? 'array_of_content_objects';
        $report['compare_gate_enabled'] = ($diag['compare_gate_enabled'] ?? true) === true;
        $report['brand_node_preview_safe'] = $diag['current_brand_node_json_preview'] ?? null;
        if ($report['brand_code'] === null && isset($diag['selected_fare_family_brand_code'])) {
            $report['brand_code'] = $diag['selected_fare_family_brand_code'];
        }
        if ($report['brand_code'] === null && isset($diag['resolved_brand_code_for_wire'])) {
            $report['brand_code'] = $diag['resolved_brand_code_for_wire'];
        }
    }

    $diagBlock = bf7gAssertProductionDefaultBrandDiagnostics(is_array($diag) ? $diag : []);
    if ($diagBlock !== null) {
        $report['status'] = 'blocked_precheck';
        $report['sabre_classification'] = $diagBlock;
        throw new RuntimeException('Brand wire preflight failed: '.$diagBlock);
    }

    $preflight = [
        'app_env' => $appEnv,
        'allow_production_cert_controlled_send' => $allowProduction,
        'booking_id' => $report['booking_id'],
        'booking_reference' => $report['booking_reference'],
        'brand_code' => $report['brand_code'],
        'default_brand_shape_selector' => $report['default_brand_shape_selector'],
        'active_brand_shape_selector' => $report['active_brand_shape_selector'],
        'default_brand_node_shape' => $report['default_brand_node_shape'],
        'current_brand_node_shape' => $report['current_brand_node_shape'],
        'compare_gate_enabled' => $report['compare_gate_enabled'],
        'endpoint_host' => $report['endpoint_host'],
        'endpoint_path' => BF7G_ENDPOINT_PATH,
    ];

    if ($skipSend) {
        $report['status'] = 'inspect_only';
        $report['sabre_classification'] = 'send_skipped_by_flag';
        $report['http_status'] = 'not_sent';
    } else {
        bf7gEmitPreflightToStderr($preflight);
        setBf7gTemporaryBookingSendFlags();

        $rows = $sabreBooking->compareBookingEndpointsForCommand(
            $booking,
            true,
            true,
            BF7G_ENDPOINT_PATH,
            BF7G_PAYLOAD_STYLE,
        );
        $row = is_array($rows[0] ?? null) ? $rows[0] : [];

        $report['http_status'] = (string) ($row['http_status'] ?? 'not_sent');
        $report['pnr_created'] = ($row['pnr_created'] ?? false) === true;
        $report['pnr'] = is_string($row['pnr'] ?? null) && $row['pnr'] !== '' ? $row['pnr'] : null;
        $report['response_top_level_message'] = is_string($row['response_top_level_message'] ?? null)
            ? $row['response_top_level_message']
            : null;
        $paths = is_array($row['response_error_paths'] ?? null)
            ? array_slice(array_map('strval', $row['response_error_paths']), 0, 32)
            : [];
        $report['response_error_paths'] = $paths !== [] ? $paths : null;
        $messages = is_array($row['response_error_messages'] ?? null)
            ? array_slice(array_map('strval', $row['response_error_messages']), 0, 32)
            : [];
        $report['sabre_classification'] = bf7gClassifySabreRow(array_merge($row, [
            'response_error_paths' => $paths,
            'response_error_messages' => $messages,
        ]));
        $report['brand_pointer_error_present'] = bf7gBrandPointerErrorPresent($paths, $messages);
        $report['brand_code_property_error_present'] = bf7gBrandCodePropertyErrorPresent($paths, $messages);
        $report['status'] = 'live_attempted';
    }
} catch (Throwable $e) {
    if ($report['status'] === 'pending') {
        $report['status'] = 'error';
    }
    $report['error'] = $e->getMessage();
} finally {
    if ($bf7gOriginalFlagSnapshot !== null) {
        bf7gApplyFlagSnapshot($bf7gOriginalFlagSnapshot);
    }
    $report['flags_after'] = bf7gFlagSnapshot();
    $report['attempt_counts_after'] = bf7gAttemptCountsSnapshot();
    $report['flags_restored'] = $bf7gOriginalFlagSnapshot !== null
        && bf7gFlagsRestored($bf7gOriginalFlagSnapshot, $report['flags_after']);
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;

$exitOk = in_array($report['status'], ['live_attempted', 'inspect_only'], true);
exit($exitOk ? 0 : 1);
