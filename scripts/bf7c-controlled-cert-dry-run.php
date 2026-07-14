<?php

/**
 * BF7-C / BF7-C1 controlled CERT dry-run helper.
 *
 * Usage:
 *   php scripts/bf7c-controlled-cert-dry-run.php --booking=51 [--skip-send]
 *   php scripts/bf7c-controlled-cert-dry-run.php --booking 51 --allow-production-cert-controlled-send
 *   php scripts/bf7c-controlled-cert-dry-run.php --self-test-cli-parse
 */

declare(strict_types=1);

/**
 * @return array{
 *     booking_id: int|null,
 *     skip_send: bool,
 *     allow_production_cert_controlled_send: bool,
 *     self_test_cli_parse: bool
 * }
 */
function parseBf7cArgv(array $argv): array
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

function resolveAppEnvGate(bool $allowProductionFlag, string $appEnv): ?string
{
    if (in_array($appEnv, ['local', 'testing'], true)) {
        return null;
    }

    if ($appEnv === 'production' && $allowProductionFlag) {
        return null;
    }

    return 'APP_ENV must be local or testing.';
}

function runBf7cCliParseSelfTest(): int
{
    $failures = [];

    $eq = static function (string $label, mixed $expected, mixed $actual) use (&$failures): void {
        if ($expected !== $actual) {
            $failures[] = $label.': expected '.json_encode($expected).', got '.json_encode($actual);
        }
    };

    $parsed = parseBf7cArgv(['script', '--booking=51']);
    $eq('booking_equals_form', 51, $parsed['booking_id']);
    $eq('allow_off_equals', false, $parsed['allow_production_cert_controlled_send']);

    $parsed = parseBf7cArgv(['script', '--booking', '51']);
    $eq('booking_space_form', 51, $parsed['booking_id']);

    $parsed = parseBf7cArgv(['script', '--booking=51', '--allow-production-cert-controlled-send', '--skip-send']);
    $eq('allow_on', true, $parsed['allow_production_cert_controlled_send']);
    $eq('skip_send_on', true, $parsed['skip_send']);

    $eq('gate_local_ok', null, resolveAppEnvGate(false, 'local'));
    $eq('gate_production_blocked', 'APP_ENV must be local or testing.', resolveAppEnvGate(false, 'production'));
    $eq('gate_production_allowed', null, resolveAppEnvGate(true, 'production'));

    if ($failures !== []) {
        fwrite(STDERR, "CLI parse self-test FAILED:\n".implode("\n", $failures)."\n");

        return 1;
    }

    fwrite(STDERR, 'CLI parse self-test OK ('.count($failures)." failures)\n");

    return 0;
}

$cli = parseBf7cArgv($argv);
if ($cli['self_test_cli_parse']) {
    exit(runBf7cCliParseSelfTest());
}

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Config;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

const BF7C_ENDPOINT_PATH = SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH;
const BF7C_PAYLOAD_STYLE = SabreBookingPayloadBuilder::IATI_LIKE_CPNR_V2_4_GDS;
const BF7C_BLOCKED_BOOKING_IDS = [43, 46];

/** @var array<string, mixed>|null */
$bf7cOriginalFlagSnapshot = null;

function flagSnapshot(): array
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

function applyFlagSnapshot(array $snapshot): void
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

function setBf7cCompareTestFlags(): void
{
    putenv('SABRE_BRANDED_FARES_AIRPRICE_BRAND_SHAPE_COMPARE_ENABLED=true');
    putenv('SABRE_BRANDED_FARES_AIRPRICE_BRAND_SHAPE_COMPARE_VARIANT=string_array');
    Config::set('suppliers.sabre.branded_fares_airprice_brand_shape_compare_enabled', true);
    Config::set('suppliers.sabre.branded_fares_airprice_brand_shape_compare_variant', 'string_array');
}

function setBf7cTemporaryBookingSendFlags(): void
{
    putenv('SABRE_BOOKING_ENABLED=true');
    putenv('SABRE_BOOKING_LIVE_CALL_ENABLED=true');
    Config::set('suppliers.sabre.booking_enabled', true);
    Config::set('suppliers.sabre.booking_live_call_enabled', true);
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

    return null;
}

function attemptCountsSnapshot(): array
{
    return [
        'supplier_booking_attempts' => SupplierBookingAttempt::query()->count(),
        'create_pnr' => SupplierBookingAttempt::query()->where('action', 'create_pnr')->count(),
        'compare_booking_endpoint' => SupplierBookingAttempt::query()->where('action', 'compare_booking_endpoint')->count(),
    ];
}

function ensureBrandedFreedomBooking(?int $bookingId, bool $productionRequiresExplicitBooking): Booking
{
    if ($productionRequiresExplicitBooking && ($bookingId === null || $bookingId <= 0)) {
        throw new RuntimeException('Production requires --booking={id} (e.g. --booking=51 or --booking 51).');
    }

    if ($bookingId !== null && $bookingId > 0) {
        $existing = Booking::query()->find($bookingId);
        if ($existing === null) {
            throw new RuntimeException("Booking {$bookingId} not found.");
        }

        return $existing->loadMissing(['passengers', 'contact', 'fareBreakdown']);
    }

    (new OtaFoundationSeeder)->run();
    $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
    $sabreConn = SupplierConnection::query()
        ->where('agency_id', $agency->id)
        ->where('provider', SupplierProvider::Sabre->value)
        ->firstOrFail();

    $depart = now()->addDays(21)->toDateString();
    $snapshot = [
        'offer_id' => 'bf7c-freedom-cert',
        'supplier_offer_id' => 'bf7c-freedom-cert',
        'supplier_provider' => SupplierProvider::Sabre->value,
        'supplier_connection_id' => $sabreConn->id,
        'airline_code' => 'EK',
        'validating_carrier' => 'EK',
        'fare_family' => 'FREEDOM',
        'segments' => [[
            'origin' => 'LHE',
            'destination' => 'DXB',
            'departure_at' => $depart.'T08:00:00Z',
            'arrival_at' => $depart.'T14:00:00Z',
            'carrier' => 'EK',
            'flight_number' => '615',
            'booking_class' => 'V',
            'fare_basis_code' => 'VOWFL/V',
        ]],
        'fare_breakdown' => [
            'supplier_total' => 165000,
            'currency' => 'PKR',
            'base_fare' => 140000,
            'taxes' => 25000,
            'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
        ],
        'sabre_booking_context' => [
            'brand_code' => 'FL',
            'selected_brand_code' => 'FL',
        ],
    ];

    $booking = Booking::factory()->create([
        'agency_id' => $agency->id,
        'status' => BookingStatus::Pending,
        'supplier' => SupplierProvider::Sabre->value,
        'meta' => [
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => $sabreConn->id,
            'normalized_offer_snapshot' => $snapshot,
            'selected_fare_family_option' => [
                'name' => 'FREEDOM',
                'brand_code' => 'FL',
                'fare_option_key' => 'fl-pi3',
                'displayed_price' => 90062,
                'displayed_currency' => 'PKR',
                'booking_class' => 'V',
                'fare_basis' => 'VOWFL/V',
            ],
            'search_criteria' => [
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_date' => $depart,
                'trip_type' => 'one_way',
                'cabin' => 'economy',
                'adults' => 1,
            ],
        ],
    ]);

    BookingPassenger::factory()->create([
        'booking_id' => $booking->id,
        'passenger_index' => 1,
        'passenger_type' => 'adult',
        'is_lead_passenger' => true,
        'first_name' => 'Bf7c',
        'last_name' => 'CertTest',
        'passport_number' => 'XT7777777',
        'passport_issuing_country' => 'PK',
        'passport_expiry_date' => '2035-06-01',
        'nationality' => 'PK',
        'document_type' => 'passport',
    ]);

    BookingContact::query()->create([
        'booking_id' => $booking->id,
        'email' => 'bf7c-cert@example.test',
        'phone' => '+923001112233',
        'country' => 'Pakistan',
    ]);

    return $booking->fresh(['passengers', 'contact', 'fareBreakdown']);
}

function connectionReady(?Booking $booking): array
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

function resolveBookingBrandCode(Booking $booking): ?string
{
    $meta = is_array($booking->meta) ? $booking->meta : [];

    return app(SabreBookingPayloadBuilder::class)->selectedFareFamilyBrandCodeFromBookingMetaForInspect($meta);
}

function bookingHasFareFamily(Booking $booking): bool
{
    $meta = is_array($booking->meta) ? $booking->meta : [];
    $opt = $meta['selected_fare_family_option'] ?? null;
    if (! is_array($opt)) {
        return false;
    }

    return trim((string) ($opt['name'] ?? '')) !== '' || trim((string) ($opt['code'] ?? '')) !== '';
}

function brandPrecheckPasses(?string $brandCode, bool $hasFareFamily): bool
{
    return $brandCode === 'FL' || $hasFareFamily;
}

/**
 * @param  list<string>  $paths
 * @param  list<string>  $messages
 */
function brandObjectErrorStillPresent(array $paths, array $messages): bool
{
    $pointer = SabreBookingPayloadBuilder::AIRPRICE_BRAND_REJECTED_POINTER;
    foreach ($paths as $path) {
        $p = (string) $path;
        if ($p !== '' && (str_contains($p, 'Brand/0') || str_contains($p, $pointer))) {
            return true;
        }
    }

    $blob = strtolower(json_encode($messages, JSON_UNESCAPED_SLASHES) ?: '[]');

    return str_contains($blob, 'object instance has properties') && str_contains($blob, 'brand');
}

/**
 * @param  array<string, mixed>  $row
 */
function classifySabreRow(array $row): string
{
    if (isset($row['classification']) && is_string($row['classification']) && trim($row['classification']) !== '') {
        return trim($row['classification']);
    }

    $httpStatus = (string) ($row['http_status'] ?? '');
    $pnrCreated = ($row['pnr_created'] ?? false) === true;
    $paths = array_map('strval', (array) ($row['response_error_paths'] ?? []));
    $messages = array_map('strval', (array) ($row['response_error_messages'] ?? []));

    if ($httpStatus === '422') {
        if (brandObjectErrorStillPresent($paths, $messages)) {
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
function emitPreflightToStderr(array $preflight): void
{
    $lines = [
        '--- BF7-C1 preflight ---',
        'app_env='.$preflight['app_env'],
        'allow_production_cert_controlled_send='.($preflight['allow_production_cert_controlled_send'] ? 'true' : 'false'),
        'booking_id='.$preflight['booking_id'],
        'booking_reference='.($preflight['booking_reference'] ?? 'null'),
        'brand_code='.($preflight['brand_code'] ?? 'null'),
        'endpoint_host='.($preflight['endpoint_host'] ?? 'unknown'),
        'endpoint_path='.($preflight['endpoint_path'] ?? 'unknown'),
        'brand_node_shape='.($preflight['brand_node_shape'] ?? 'null'),
        '---',
    ];
    fwrite(STDERR, implode(PHP_EOL, $lines).PHP_EOL);
}

$appEnv = (string) config('app.env');
$isProduction = $appEnv === 'production';
$allowProduction = $cli['allow_production_cert_controlled_send'];
$skipSend = $cli['skip_send'];
$bookingOpt = $cli['booking_id'];

$bf7cOriginalFlagSnapshot = flagSnapshot();

$report = [
    'sprint' => 'SABRE-BRANDED-FARES-BF7-C1',
    'app_env' => $appEnv,
    'allow_production_cert_controlled_send' => $allowProduction,
    'flags_before' => $bf7cOriginalFlagSnapshot,
    'flags_after' => null,
    'booking_id' => $bookingOpt,
    'booking_reference' => null,
    'brand_code' => null,
    'endpoint_path' => BF7C_ENDPOINT_PATH,
    'endpoint_host' => null,
    'payload_style' => BF7C_PAYLOAD_STYLE,
    'attempt_counts_before' => attemptCountsSnapshot(),
    'attempt_counts_after' => null,
    'preflight' => null,
    'brand_node_shape' => null,
    'http_status' => null,
    'sabre_classification' => null,
    'brand_object_error_still_present' => null,
    'brand_object_error_disappeared' => null,
    'pnr_created' => false,
    'pnr' => null,
    'response_top_level_message' => null,
    'response_error_paths' => null,
    'response_error_messages' => null,
    'commands_run' => [],
    'status' => 'pending',
];

try {
    $envBlock = resolveAppEnvGate($allowProduction, $appEnv);
    if ($envBlock !== null) {
        throw new RuntimeException($envBlock);
    }

    $booking = ensureBrandedFreedomBooking($bookingOpt, $isProduction && $allowProduction);
    $report['booking_id'] = $booking->id;
    $report['booking_reference'] = $booking->booking_reference ?? null;

    $brandCode = resolveBookingBrandCode($booking);
    $hasFareFamily = bookingHasFareFamily($booking);
    $report['brand_code'] = $brandCode;

    if ($isProduction && $allowProduction && ! $skipSend) {
        if ($bookingOpt === null || $bookingOpt <= 0) {
            throw new RuntimeException('Production requires --booking={id} (e.g. --booking=51 or --booking 51).');
        }
        if (in_array($booking->id, BF7C_BLOCKED_BOOKING_IDS, true)) {
            $report['status'] = 'blocked_precheck';
            $report['sabre_classification'] = 'blocked_booking_id_43_or_46';
            throw new RuntimeException('Booking id '.$booking->id.' is blocked (43 and 46 are not allowed).');
        }
        if (! brandPrecheckPasses($brandCode, $hasFareFamily)) {
            $report['status'] = 'blocked_precheck';
            $report['sabre_classification'] = 'brand_code_or_fare_family_missing';
            throw new RuntimeException('Booking must have brand_code FL or selected fare family present.');
        }
        if (BF7C_ENDPOINT_PATH !== SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH) {
            $report['status'] = 'blocked_precheck';
            $report['sabre_classification'] = 'endpoint_path_not_allowed';
            throw new RuntimeException('Endpoint path must be '.SabreBookingPayloadBuilder::PASSENGER_RECORDS_V24_CREATE_PATH);
        }
        $safetyBlock = assertSafetyFlagsOff();
        if ($safetyBlock !== null) {
            $report['status'] = 'blocked_precheck';
            $report['sabre_classification'] = $safetyBlock;
            throw new RuntimeException('Safety flag check failed: '.$safetyBlock);
        }
    }

    $connCheck = connectionReady($booking);
    $report['connection'] = $connCheck;
    $report['endpoint_host'] = $connCheck['resolved_base_host'] ?? null;

    setBf7cCompareTestFlags();
    $report['flags_during_test'] = flagSnapshot();

    /** @var SabreBookingService $sabreBooking */
    $sabreBooking = app(SabreBookingService::class);

    $diag = $sabreBooking->inspectPassengerRecordsAirPriceBrandDiagnosticsForCommand($booking, BF7C_PAYLOAD_STYLE);
    $report['commands_run'][] = 'direct:inspectPassengerRecordsAirPriceBrandDiagnosticsForCommand(booking='.$booking->id.', style='.BF7C_PAYLOAD_STYLE.')';

    if (is_array($diag)) {
        $report['brand_node_shape'] = $diag['current_brand_node_shape'] ?? null;
        $report['resolved_brand_code'] = $diag['resolved_brand_code_for_wire'] ?? null;
        $report['active_brand_shape_selector'] = $diag['active_brand_shape_selector'] ?? null;
        if ($report['brand_code'] === null && isset($diag['selected_fare_family_brand_code'])) {
            $report['brand_code'] = $diag['selected_fare_family_brand_code'];
        }
    }

    $report['preflight'] = [
        'app_env' => $appEnv,
        'allow_production_cert_controlled_send' => $allowProduction,
        'booking_id' => $report['booking_id'],
        'booking_reference' => $report['booking_reference'],
        'brand_code' => $report['brand_code'],
        'endpoint_host' => $report['endpoint_host'],
        'endpoint_path' => BF7C_ENDPOINT_PATH,
        'brand_node_shape' => $report['brand_node_shape'],
        'flags_before' => $bf7cOriginalFlagSnapshot,
        'attempt_counts_before' => $report['attempt_counts_before'],
    ];

    if ($skipSend) {
        $report['status'] = 'inspect_only';
        $report['sabre_classification'] = 'send_skipped_by_flag';
    } elseif (! ($connCheck['has_credentials'] ?? false) || ($connCheck['is_cert_host'] ?? false) !== true) {
        $report['status'] = 'not_sent';
        $report['http_status'] = 'not_sent';
        $report['sabre_classification'] = 'sabre_credentials_missing_or_cert_host_missing';
    } elseif (! $isProduction) {
        if (! ($connCheck['ok'] ?? false)) {
            $report['status'] = 'blocked_precheck';
            $report['http_status'] = 'not_sent';
            $report['sabre_classification'] = (string) ($connCheck['reason'] ?? 'connection_not_ready');
        } else {
            emitPreflightToStderr($report['preflight']);
            setBf7cTemporaryBookingSendFlags();
            $report['commands_run'][] = 'direct:compareBookingEndpointsForCommand(booking='.$booking->id.', send=true, endpoint='.BF7C_ENDPOINT_PATH.')';

            $rows = $sabreBooking->compareBookingEndpointsForCommand(
                $booking,
                true,
                true,
                BF7C_ENDPOINT_PATH,
                BF7C_PAYLOAD_STYLE,
            );
            $row = is_array($rows[0] ?? null) ? $rows[0] : [];

            $report['http_status'] = (string) ($row['http_status'] ?? 'not_sent');
            $report['pnr_created'] = ($row['pnr_created'] ?? false) === true;
            $report['pnr'] = is_string($row['pnr'] ?? null) && $row['pnr'] !== '' ? $row['pnr'] : null;
            $report['response_top_level_message'] = is_string($row['response_top_level_message'] ?? null) ? $row['response_top_level_message'] : null;
            $report['response_error_paths'] = is_array($row['response_error_paths'] ?? null) ? $row['response_error_paths'] : null;
            $report['response_error_messages'] = is_array($row['response_error_messages'] ?? null) ? $row['response_error_messages'] : null;
            $report['sabre_classification'] = classifySabreRow($row);
            $paths = array_map('strval', (array) ($report['response_error_paths'] ?? []));
            $messages = array_map('strval', (array) ($report['response_error_messages'] ?? []));
            $report['brand_object_error_still_present'] = brandObjectErrorStillPresent($paths, $messages);
            $report['brand_object_error_disappeared'] = $report['http_status'] !== 'not_sent' && ! $report['brand_object_error_still_present'];
            $report['status'] = 'live_attempted';
        }
    } else {
        emitPreflightToStderr($report['preflight']);
        setBf7cTemporaryBookingSendFlags();
        $report['commands_run'][] = 'direct:compareBookingEndpointsForCommand(booking='.$booking->id.', send=true, endpoint='.BF7C_ENDPOINT_PATH.')';

        $rows = $sabreBooking->compareBookingEndpointsForCommand(
            $booking,
            true,
            true,
            BF7C_ENDPOINT_PATH,
            BF7C_PAYLOAD_STYLE,
        );
        $row = is_array($rows[0] ?? null) ? $rows[0] : [];

        $report['http_status'] = (string) ($row['http_status'] ?? 'not_sent');
        $report['pnr_created'] = ($row['pnr_created'] ?? false) === true;
        $report['pnr'] = is_string($row['pnr'] ?? null) && $row['pnr'] !== '' ? $row['pnr'] : null;
        $report['response_top_level_message'] = is_string($row['response_top_level_message'] ?? null) ? $row['response_top_level_message'] : null;
        $report['response_error_paths'] = is_array($row['response_error_paths'] ?? null) ? $row['response_error_paths'] : null;
        $report['response_error_messages'] = is_array($row['response_error_messages'] ?? null) ? $row['response_error_messages'] : null;
        $report['sabre_classification'] = classifySabreRow($row);
        $paths = array_map('strval', (array) ($report['response_error_paths'] ?? []));
        $messages = array_map('strval', (array) ($report['response_error_messages'] ?? []));
        $report['brand_object_error_still_present'] = brandObjectErrorStillPresent($paths, $messages);
        $report['brand_object_error_disappeared'] = $report['http_status'] !== 'not_sent' && ! $report['brand_object_error_still_present'];
        $report['status'] = 'live_attempted';
    }
} catch (Throwable $e) {
    if ($report['status'] === 'pending') {
        $report['status'] = 'error';
    }
    $report['error'] = $e->getMessage();
} finally {
    if ($bf7cOriginalFlagSnapshot !== null) {
        applyFlagSnapshot($bf7cOriginalFlagSnapshot);
    }
    $report['flags_after'] = flagSnapshot();
    $report['attempt_counts_after'] = attemptCountsSnapshot();
    $report['ticketing_still_off'] = config('suppliers.sabre.ticketing_enabled') === false;
    $report['public_auto_pnr_still_off'] = config('suppliers.sabre.verified_multiseg_auto_pnr_enabled') === false
        && config('suppliers.sabre.cpnr_connecting_same_carrier_public_checkout_enabled') === false;
    $report['compare_gate_restored_off'] = config('suppliers.sabre.branded_fares_airprice_brand_shape_compare_enabled') === false;
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;

$exitOk = in_array($report['status'], ['live_attempted', 'inspect_only'], true);
exit($exitOk ? 0 : 1);
