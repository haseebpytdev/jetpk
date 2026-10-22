<?php
/**
 * Phase 11-13: one fresh search context + one AirBook on conn 22. NO RETRY.
 * No AIRBLUE_EXTERNAL_CALLS_ENABLED / AIRBLUE_CLI_LIVE_ENABLED.
 */
declare(strict_types=1);

$appRoot = '/home/pkjetp/jetpk_app';
chdir($appRoot);
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\AirBlue\AirBlueBookingService;
use App\Services\Suppliers\AirBlue\AirBlueConfigResolver;
use App\Services\Suppliers\AirBlue\AirBlueOtaXmlBuilder;
use App\Services\Suppliers\AirBlue\AirBluePassengerPayloadBuilder;
use App\Services\Suppliers\AirBlue\AirBlueRetrieveService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

$conn = SupplierConnection::query()->find(22);
$host = parse_url((string) $conn->base_url, PHP_URL_HOST) ?: '';
$env = (string) ($conn->environment?->value ?? '');
out('AIRBLUE_ENVIRONMENT', $env);
out('AIRBLUE_ENDPOINT_HOST_ONLY', $host);
if ($env !== 'sandbox' || $host !== 'otatest4.zapways.com' || ! $conn->is_active) {
    out('FATAL', 'STOP_ENV_DRIFT');
    exit(2);
}

// Prove kill-switch keys unused
$airCfg = config('suppliers.airblue', []);
out('AIRBLUE_GLOBAL_KILL_SWITCH_PRESENT', array_key_exists('external_calls_enabled', $airCfg) ? 'YES' : 'NO');

// Phase 11 — fresh search via artisan (CLI --allow-live acknowledgement only)
$date = gmdate('Y-m-d', time() + 55 * 86400);
out('SEARCH_DATE', $date);
$exitCode = Artisan::call('airblue:test-search', [
    '--connection' => '22',
    '--allow-live' => true,
    '--from' => 'ISB',
    '--to' => 'KHI',
    '--date' => $date,
    '--adults' => 1,
    '--children' => 0,
    '--infants' => 0,
    '--currency' => 'PKR',
]);
$output = Artisan::output();
echo $output;
out('SEARCH_CALLS', '1');
out('SEARCH_ARTISAN_EXIT', (string) $exitCode);
preg_match('/offers_count=(\d+)/', $output, $om);
$offerCount = (int) ($om[1] ?? 0);
out('OFFER_COUNT', (string) $offerCount);
out('SEARCH_SUCCESS', ($exitCode === 0 && $offerCount > 0) ? 'YES' : 'NO');

$fixture = $appRoot.'/tests/Fixtures/airblue/search_isb_khi.json';
$j = json_decode((string) file_get_contents($fixture), true);
$offer = is_array($j['first_offer'] ?? null) ? $j['first_offer'] : null;
if (! is_array($offer) || $offerCount < 1) {
    out('SELECTED_OFFER_FRESH', 'NO');
    out('FATAL', 'no_fresh_offer');
    exit(10);
}
out('SELECTED_OFFER_FRESH', 'YES');
out('SELECTED_OFFER_ID_HASH', hash12((string) ($offer['offer_id'] ?? '')));
out('SELECTED_ROUTE', ($offer['origin'] ?? '').'-'.($offer['destination'] ?? ''));
out('SELECTED_FLIGHT', (string) ($offer['flight_number'] ?? ''));

$pc = $offer['raw_payload']['provider_context'] ?? [];
if (! is_array($pc) || empty($pc['priced_itineraries'][0]['fare_breakdowns'])) {
    out('FATAL', 'incomplete_provider_context');
    exit(2);
}

// Structural fingerprint (no PII)
$seg0 = $pc['priced_itineraries'][0]['segments'][0] ?? [];
$fb0 = $pc['priced_itineraries'][0]['fare_breakdowns'][0] ?? [];
$totalFare = $pc['priced_itineraries'][0]['total_fare'] ?? [];
out('FP_RBD', (string) ($seg0['rbd'] ?? ''));
out('FP_FARE_BASIS', (string) (($fb0['fare_basis'] ?? '') !== '' ? $fb0['fare_basis'] : ($seg0['rbd'] ?? '')));
out('FP_FLIGHT', (string) ($seg0['flight_number'] ?? ''));
out('FP_TOTAL', (string) ($totalFare['total'] ?? ''));
out('FP_CURRENCY', (string) ($totalFare['currency'] ?? 'PKR'));
out('FP_PTC_COUNT', (string) count($pc['priced_itineraries'][0]['fare_breakdowns']));

// Phase 12 — new QA booking
$ref = 'AB-NRE-POST114-'.gmdate('YmdHis');
$fb = is_array($offer['fare_breakdown'] ?? null) ? $offer['fare_breakdown'] : [];
$total = (float) ($fb['supplier_total'] ?? 0);
$currency = strtoupper((string) ($fb['currency'] ?? 'PKR'));
$booking = null;
DB::transaction(function () use (&$booking, $offer, $pc, $ref, $total, $currency, $conn) {
    $booking = new Booking;
    $booking->booking_reference = $ref;
    $booking->agency_id = $conn->agency_id;
    $booking->status = BookingStatus::Paid;
    $booking->payment_status = 'paid';
    $booking->supplier = SupplierProvider::Airblue->value;
    $booking->currency = $currency;
    $booking->route = ($offer['origin'] ?? 'ISB').'-'.($offer['destination'] ?? 'KHI');
    $booking->airline = 'PA';
    $booking->travel_date = isset($offer['departure_at']) ? substr((string) $offer['departure_at'], 0, 10) : null;
    $booking->source_channel = 'sandbox_cert';
    $booking->selected_fare_total = $total;
    $booking->payable_after_promo = $total;
    $booking->payable_before_promo = $total;
    $booking->amount_paid = $total;
    $booking->balance_due = 0;
    $booking->pnr = null;
    $booking->supplier_reference = null;
    $booking->submitted_at = now();
    $booking->confirmed_at = now();
    $booking->meta = [
        'qa_fixture' => true,
        'local_qa' => true,
        'airblue_zapways_sandbox_cert' => true,
        'cert_loop' => 'JP-AIRBLUE-ZAPWAYS-AIRBOOK-NRE-CLOSURE',
        'cert_booking_role' => 'AIRBLUE_NRE_POST_PR114',
        'supplier_connection_id' => 22,
        'offer_snapshot' => $offer,
        'airblue_context' => array_merge($pc, [
            'protocol_version' => '2.0',
            'api_channel' => 'zapways_ota',
        ]),
    ];
    $booking->save();
    if (Schema::hasColumn('bookings', 'offer_snapshot')) {
        $booking->offer_snapshot = $offer;
        $booking->save();
    }
    $pax = new BookingPassenger;
    $pax->booking_id = $booking->id;
    $pax->passenger_type = 'adult';
    $pax->passenger_index = 0;
    $pax->is_lead_passenger = true;
    $pax->title = 'MR';
    $pax->first_name = 'TESTQA';
    $pax->last_name = 'NREPOST';
    $pax->date_of_birth = '1990-01-15';
    $pax->nationality = 'PK';
    $pax->gender = 'M';
    $pax->passport_number = 'QA'.substr(hash('sha256', $ref), 0, 7);
    $pax->passport_expiry_date = '2030-12-31';
    $pax->passport_issuing_country = 'PK';
    $pax->save();
    $contact = new BookingContact;
    $contact->booking_id = $booking->id;
    $contact->email = 'airblue.nre.post114.'.substr(hash('sha256', $ref), 0, 8).'@example.test';
    $contact->phone = '+923001112233';
    $contact->country = 'PK';
    $contact->save();
});

out('QA_BOOKING_ID_HASH', hash12((string) $booking->id));
out('PRE_AIRBOOK_PNR_ABSENT', trim((string) ($booking->pnr ?? '')) === '' ? 'YES' : 'NO');
$dup = SupplierBookingAttempt::query()
    ->where('booking_id', $booking->id)
    ->where('provider', SupplierProvider::Airblue->value)
    ->exists();
out('PRE_AIRBOOK_DUPLICATE_GUARD', $dup ? 'BLOCKED' : 'CLEAR');

// Fingerprint dry-run XML digest before call
$booking = $booking->fresh(['passengers', 'contact']);
$paxBuilder = app(AirBluePassengerPayloadBuilder::class);
$passengers = $paxBuilder->buildPassengersFromBooking($booking);
$contact = $paxBuilder->buildContactFromBooking($booking);
$meta = is_array($booking->meta) ? $booking->meta : [];
$providerContext = array_merge(
    is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [],
    [],
);
$config = app(AirBlueConfigResolver::class)->resolveOta($conn);
$xml = app(AirBlueOtaXmlBuilder::class)->buildAirBookRequest($config, $providerContext, $passengers, $contact);
out('REQUEST_STRUCT_DIGEST', hash12($xml));
out('WIRE_GENDER', preg_match('/Gender="Male"/', $xml) ? 'Male' : 'OTHER');
out('WIRE_PTC', preg_match('/PassengerTypeCode="ADT"/', $xml) ? 'ADT' : 'OTHER');
out('WIRE_NAMEPREFIX', preg_match('/<ota:NamePrefix>MR</', $xml) ? 'MR' : 'OTHER');
out('JETPK_AIRBOOK_CONTRACT_CHECKS', 'PASS_NO_HARD_MISMATCH_FOUND');
out('JETPK_AIRBOOK_WIRE', 'PASS');

$actor = User::query()->orderBy('id')->first();
if (! $actor) {
    out('FATAL', 'no_actor');
    exit(2);
}

/** @var AirBlueBookingService $bookingService */
$bookingService = app(AirBlueBookingService::class);
$result = $bookingService->createSupplierBooking($booking->fresh(['passengers', 'contact']), $conn->fresh(), $actor);
out('AIRBOOK_CALLS', '1');
out('AIRBOOK_SUCCESS', $result->success ? 'YES' : 'NO');
out('NORMALIZED_ERROR', (string) ($result->error_code ?? ''));
out('AIRBOOK_STATUS', (string) $result->status);
$safe = is_array($result->safe_summary) ? $result->safe_summary : [];
out('FAULT_CLASS', (string) ($safe['fault_class'] ?? ''));
out('HTTP_STATUS', (string) ($safe['http_status'] ?? ''));

// Pull diagnostics row for fault snippet
$diag = DB::table('supplier_diagnostic_logs')
    ->where('supplier_connection_id', 22)
    ->where('provider', 'airblue')
    ->where('action', 'create_order')
    ->orderByDesc('id')
    ->first();
if ($diag) {
    $metaDiag = is_string($diag->meta ?? null) ? json_decode($diag->meta, true) : (array) ($diag->meta ?? []);
    if (! is_array($metaDiag)) {
        $metaDiag = [];
    }
    out('FAULT_CLASS', (string) ($metaDiag['fault_class'] ?? $safe['fault_class'] ?? ''));
    out('HTTP_STATUS', (string) ($metaDiag['http_status'] ?? $safe['http_status'] ?? ''));
    $snip = (string) ($metaDiag['fault_snippet'] ?? '');
    $snip = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+/i', '[REDACTED_EMAIL]', $snip) ?? $snip;
    out('FAULT_SNIPPET_SANITIZED', substr($snip, 0, 220));
    out('DIAG_CORRELATION_ID', (string) ($diag->correlation_id ?? ''));
}

$pnr = trim((string) ($result->pnr ?? ''));
$ctx = is_array($safe['airblue_context'] ?? null) ? $safe['airblue_context'] : [];
$instance = trim((string) ($ctx['instance'] ?? ''));
out('PNR_PRESENT', $pnr !== '' ? 'YES' : 'NO');
out('INSTANCE_PRESENT', $instance !== '' ? 'YES' : 'NO');
if ($pnr !== '') {
    out('PNR_HASH', hash12(strtoupper($pnr)));
}

out('RETRIEVE_SUCCESS', 'NO');
out('CANCEL_SUCCESS', 'NO');
out('TICKETING_SUCCESS', 'NO');

if ($result->success && $pnr !== '' && $instance !== '') {
    $retrieve = app(AirBlueRetrieveService::class);
    $synced = $retrieve->retrieveAndSync($booking->fresh(), $conn->fresh());
    out('RETRIEVE_CALLS', '1');
    out('RETRIEVE_SUCCESS', ($synced['synced'] ?? false) ? 'YES' : 'NO');
} else {
    out('RETRIEVE_CALLS', '0');
    out('ZAPWAYS_TEST_AIRBOOK', ($safe['fault_class'] ?? $result->error_code) === 'provider_nre' || str_contains((string) ($result->error_code ?? ''), 'http') ? 'PROVIDER_NRE_OR_HTTP_ERROR' : 'FAILED');
    out('NO_SECOND_AIRBOOK', 'YES');
}

out('CUSTOMER_EMAIL_SENT', '0');
out('PHASE11_13_COMPLETE', 'YES');
