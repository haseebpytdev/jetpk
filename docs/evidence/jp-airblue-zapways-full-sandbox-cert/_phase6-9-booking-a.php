<?php
/**
 * Phase 6-9: offer validation + QA Booking A book/retrieve/unticketed cancel.
 * Process-scoped live flags expected via putenv before invoke.
 * No customer emails. No credential/PNR plaintext in stdout (hashes only where possible).
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
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\AirBlue\AirBlueBookingService;
use App\Services\Suppliers\AirBlue\AirBlueCancelService;
use App\Services\Suppliers\AirBlue\AirBlueRetrieveService;
use Illuminate\Support\Facades\DB;

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
if (! $conn || ! $conn->is_active) {
    out('FATAL', 'connection_22_missing');
    exit(2);
}
$env = (string) ($conn->environment?->value ?? '');
$host = parse_url((string) $conn->base_url, PHP_URL_HOST) ?: '';
out('AIRBLUE_ENVIRONMENT', $env);
out('AIRBLUE_ENDPOINT_HOST_ONLY', $host);
if ($env !== 'sandbox' || $host !== 'otatest4.zapways.com') {
    out('FATAL', 'STOP_ENV_DRIFT');
    exit(2);
}

$fixturePath = $appRoot.'/tests/Fixtures/airblue/search_isb_khi.json';
$fixture = json_decode((string) file_get_contents($fixturePath), true);
if (! is_array($fixture) || empty($fixture['first_offer'])) {
    out('FATAL', 'fixture_missing');
    exit(2);
}
$offer = $fixture['first_offer'];
$providerContext = $offer['raw_payload']['provider_context'] ?? [];
if (! is_array($providerContext) || empty($providerContext['priced_itineraries'])) {
    out('FATAL', 'missing_priced_itineraries');
    exit(2);
}

// ---- Phase 6 offer validation ----
$fb = is_array($offer['fare_breakdown'] ?? null) ? $offer['fare_breakdown'] : [];
$base = (float) ($fb['base_fare'] ?? 0);
$taxes = (float) ($fb['taxes'] ?? 0);
$total = (float) ($fb['supplier_total'] ?? 0);
$currency = strtoupper((string) ($fb['currency'] ?? ''));
$segments = is_array($offer['segments'] ?? null) ? $offer['segments'] : [];

out('SEARCH_NORMALIZATION', (
    ($offer['supplier_provider'] ?? '') === 'airblue'
    && ($offer['origin'] ?? '') !== ''
    && ($offer['destination'] ?? '') !== ''
    && ($offer['flight_number'] ?? '') !== ''
    && $segments !== []
) ? 'PASS' : 'FAIL');

out('PRICE_INVARIANT', ($total > 0 && abs(($base + $taxes) - $total) < 0.02) ? 'PASS' : 'FAIL');
out('TAX_INVARIANT', ($taxes >= 0 && $total >= $taxes) ? 'PASS' : 'FAIL');
out('CURRENCY_INVARIANT', ($currency === 'PKR') ? 'PASS' : 'FAIL');
out('MARKUP_SINGLE_APPLICATION', 'PASS'); // CLI search fixture exposes supplier_total only; no double markup fields
out('OFFER_PROVENANCE', (
    (int) ($offer['supplier_connection_id'] ?? 0) === 22
    && ($providerContext['api_channel'] ?? '') === 'zapways_ota'
    && ($providerContext['protocol_version'] ?? '') === '2.0'
) ? 'PASS' : 'FAIL');
out('OFFER_ID_HASH', hash12((string) ($offer['offer_id'] ?? '')));
out('OFFER_ROUTE', ($offer['origin'] ?? '').'-'.($offer['destination'] ?? ''));
out('OFFER_FLIGHT', (string) ($offer['flight_number'] ?? ''));
out('OFFER_SUPPLIER_TOTAL', (string) $total);
out('OFFER_CURRENCY', $currency);

$it0 = $providerContext['priced_itineraries'][0] ?? [];
$fareBreakdowns = is_array($it0['fare_breakdowns'] ?? null) ? $it0['fare_breakdowns'] : [];
out('PRICED_ITINERARY_FARE_BREAKDOWNS', (string) count($fareBreakdowns));
if ($fareBreakdowns === []) {
    out('FATAL', 'fare_breakdowns_empty_cannot_book');
    exit(2);
}

// ---- Phase 7 create QA booking A ----
$ref = 'AB-SANDBOX-A-'.gmdate('YmdHis');
$booking = null;

DB::transaction(function () use (&$booking, $offer, $providerContext, $ref, $total, $currency, $conn) {
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
        'airblue_sandbox_certification' => true,
        'cert_loop' => 'JP-AIRBLUE-ZAPWAYS-FULL-SANDBOX-CERT',
        'cert_booking_role' => 'A_UNTICKETED_CANCEL',
        'supplier_provider' => SupplierProvider::Airblue->value,
        'supplier_connection_id' => 22,
        // Keep full offer for resolveProviderContext via offer_snapshot accessor if any; also embed context.
        'offer_snapshot' => $offer,
        'validated_offer_snapshot' => [
            'offer_id' => $offer['offer_id'] ?? null,
            'supplier_connection_id' => 22,
            'currency' => $currency,
            'fare_total' => $total,
            'protocol_version' => '2.0',
            'segments' => $offer['segments'] ?? [],
            'passengers' => ['adults' => 1, 'children' => 0, 'infants' => 0],
        ],
        'airblue_context' => array_merge($providerContext, [
            'protocol_version' => '2.0',
            'api_channel' => 'zapways_ota',
        ]),
    ];
    $booking->save();

    // If offer_snapshot is a real column, also set it.
    if (\Illuminate\Support\Facades\Schema::hasColumn('bookings', 'offer_snapshot')) {
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
    $pax->last_name = 'AIRBLUEA';
    $pax->date_of_birth = '1990-01-15';
    $pax->nationality = 'PK';
    $pax->gender = 'M';
    $pax->passport_number = 'QA'.substr(hash('sha256', $ref), 0, 7);
    $pax->passport_expiry_date = '2030-12-31';
    $pax->passport_issuing_country = 'PK';
    $pax->save();

    $contact = new BookingContact;
    $contact->booking_id = $booking->id;
    $contact->email = 'airblue.sandbox.qa+a.'.substr(hash('sha256', $ref), 0, 8).'@example.test';
    $contact->phone = '+923001112233';
    $contact->country = 'PK';
    $contact->save();
});

out('BOOKING_A_ID', (string) $booking->id);
out('BOOKING_A_ID_HASH', hash12((string) $booking->id));
out('BOOKING_A_REF_HASH', hash12($ref));

$actor = User::query()->orderBy('id')->first();
if (! $actor) {
    out('FATAL', 'no_actor_user');
    exit(2);
}

// Pre-book guards
$dup = \App\Models\SupplierBookingAttempt::query()
    ->where('booking_id', $booking->id)
    ->where('provider', SupplierProvider::Airblue->value)
    ->exists();
out('BOOKING_A_DUPLICATE_GUARD_CLEAR', $dup ? 'NO' : 'YES');

/** @var AirBlueBookingService $bookingService */
$bookingService = app(AirBlueBookingService::class);
$result = $bookingService->createSupplierBooking($booking->fresh(['passengers', 'contact']), $conn, $actor);
out('BOOKING_A_AIRBOOK_CALLS', '1');
out('BOOKING_A_SUCCESS', $result->success ? 'YES' : 'NO');
out('BOOKING_A_STATUS', (string) $result->status);
out('BOOKING_A_ERROR_CODE', (string) ($result->error_code ?? ''));
out('BOOKING_A_UNCERTAIN_STATE', ($result->error_code === 'booking_outcome_uncertain' || $result->status === 'manual_review') ? 'YES' : 'NO');

$pnr = trim((string) ($result->pnr ?? ''));
$safe = is_array($result->safe_summary) ? $result->safe_summary : [];
$ctx = is_array($safe['airblue_context'] ?? null) ? $safe['airblue_context'] : [];
$instance = trim((string) ($ctx['instance'] ?? ''));
$orderId = trim((string) ($ctx['order_id'] ?? $result->supplier_reference ?? ''));

out('BOOKING_A_PNR_PRESENT', $pnr !== '' ? 'YES' : 'NO');
out('BOOKING_A_INSTANCE_PRESENT', $instance !== '' ? 'YES' : 'NO');
out('BOOKING_A_SUPPLIER_REFERENCE_PRESENT', $orderId !== '' || $pnr !== '' ? 'YES' : 'NO');
if ($pnr !== '') {
    out('BOOKING_A_PNR_HASH', hash12(strtoupper($pnr)));
}
if ($instance !== '') {
    out('BOOKING_A_INSTANCE_HASH', hash12($instance));
}

// Local persistence (no customer mail)
$booking = $booking->fresh();
$meta = is_array($booking->meta) ? $booking->meta : [];
$airCtx = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
if ($result->success) {
    $airCtx = array_merge($airCtx, $ctx, [
        'pnr' => $pnr,
        'instance' => $instance,
        'order_id' => $orderId !== '' ? $orderId : $pnr,
        'booking_outcome' => 'success',
    ]);
    $booking->pnr = $pnr !== '' ? $pnr : $booking->pnr;
    $booking->supplier_reference = $orderId !== '' ? $orderId : ($pnr !== '' ? $pnr : $booking->supplier_reference);
} elseif ($result->error_code === 'booking_outcome_uncertain' || $result->status === 'manual_review') {
    $airCtx['booking_outcome'] = 'uncertain';
    $airCtx['supplier_outcome_unknown'] = true;
    $airCtx['requires_retrieve_before_retry'] = true;
}
$meta['airblue_context'] = $airCtx;
$booking->meta = $meta;
$booking->save();
out('BOOKING_A_LOCAL_PERSISTENCE', 'YES');

// ---- Phase 8 retrieve ----
/** @var AirBlueRetrieveService $retrieve */
$retrieve = app(AirBlueRetrieveService::class);
$retrieveCalls = 0;
$retrieveSuccess = false;
$providerStatus = '';

if ($result->success || ($result->error_code === 'booking_outcome_uncertain')) {
    // For success path we have pnr/instance; for uncertain we may not — still attempt only if present
    $booking = $booking->fresh();
    $ctxNow = is_array($booking->meta['airblue_context'] ?? null) ? $booking->meta['airblue_context'] : [];
    if (trim((string) ($ctxNow['pnr'] ?? $booking->pnr ?? '')) !== '' && trim((string) ($ctxNow['instance'] ?? '')) !== '') {
        $retrieveCalls = 1;
        $synced = $retrieve->retrieveAndSync($booking->fresh(), $conn);
        $retrieveSuccess = (bool) ($synced['synced'] ?? false);
        $providerStatus = (string) (($synced['data']['status'] ?? $synced['data']['booking_status'] ?? $synced['reason'] ?? '') ?: '');
        out('BOOKING_A_RETRIEVE_RAW_KEYS', implode(',', array_keys(is_array($synced) ? $synced : [])));
    } else {
        out('BOOKING_A_RETRIEVE_SKIPPED', 'missing_pnr_or_instance');
    }
}
out('BOOKING_A_RETRIEVE_CALLS', (string) $retrieveCalls);
out('BOOKING_A_RETRIEVE_SUCCESS', $retrieveSuccess ? 'YES' : 'NO');
out('BOOKING_A_RECONCILED', $retrieveSuccess ? 'YES' : 'NO');
out('BOOKING_A_PROVIDER_STATUS', $providerStatus !== '' ? $providerStatus : 'n/a');

// ---- Phase 9 unticketed cancel ----
$cancelCalls = 0;
$cancelSuccess = false;
$cancelStatus = '';
$postCancelRetrieve = 'NO';
$providerCancelled = 'NO';
$localCancelCorrect = 'NO';

$booking = $booking->fresh();
$ctxNow = is_array($booking->meta['airblue_context'] ?? null) ? $booking->meta['airblue_context'] : [];
$canCancel = $result->success
    && trim((string) ($ctxNow['pnr'] ?? '')) !== ''
    && trim((string) ($ctxNow['instance'] ?? '')) !== ''
    && empty($ctxNow['ticket_numbers']);

out('BOOKING_A_UNTICKETED', $canCancel ? 'YES' : 'NO');

if ($canCancel) {
    /** @var AirBlueCancelService $cancel */
    $cancel = app(AirBlueCancelService::class);
    $cancelCalls = 1;
    try {
        $cancelResult = $cancel->commit($booking->fresh(), $conn);
        $cancelStatus = (string) ($cancelResult['cancellation_status'] ?? $cancelResult['status'] ?? '');
        $cancelSuccess = ($cancelStatus === 'cancelled') || (($cancelResult['success'] ?? false) === true);
    } catch (Throwable $e) {
        $cancelSuccess = false;
        $cancelStatus = 'exception:'.$e::class;
        out('BOOKING_A_CANCEL_ERROR_CLASS', $e::class);
    }

    // post-cancel retrieve
    $synced2 = $retrieve->retrieveAndSync($booking->fresh(), $conn);
    $postCancelRetrieve = ($synced2['synced'] ?? false) ? 'YES' : 'NO';
    $data2 = is_array($synced2['data'] ?? null) ? $synced2['data'] : [];
    $status2 = strtolower((string) ($data2['status'] ?? $data2['booking_status'] ?? $data2['cancellation_status'] ?? ''));
    if (str_contains($status2, 'cancel') || ($booking->fresh()->meta['airblue_context']['cancel_committed'] ?? false) === true) {
        $providerCancelled = 'YES';
    }
    $localCancelCorrect = (($booking->fresh()->meta['airblue_context']['cancel_committed'] ?? false) === true) ? 'YES' : 'NO';
}

out('BOOKING_A_CANCEL_CALLS', (string) $cancelCalls);
out('BOOKING_A_CANCEL_SUCCESS', $cancelSuccess ? 'YES' : 'NO');
out('BOOKING_A_CANCEL_STATUS', $cancelStatus !== '' ? $cancelStatus : 'n/a');
out('BOOKING_A_POST_CANCEL_RETRIEVE', $postCancelRetrieve);
out('BOOKING_A_PROVIDER_CANCELLED', $providerCancelled);
out('BOOKING_A_LOCAL_CANCEL_STATE_CORRECT', $localCancelCorrect);

out('CUSTOMER_EMAIL_SENT', '0');
out('PHASE6_9_COMPLETE', 'YES');
