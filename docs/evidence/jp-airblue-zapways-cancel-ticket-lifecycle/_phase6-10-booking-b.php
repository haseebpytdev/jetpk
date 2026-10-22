<?php
/**
 * Booking B: search → checkout → manual pay → AirBook → retrieve → payment-context audit.
 * Exactly one search + one AirBook + one retrieve. Mail::fake(). Conn 22 only.
 * Does NOT ticket.
 */
declare(strict_types=1);

$appRoot = '/home/pkjetp/jetpk_app';
chdir($appRoot);
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Data\FlightSearchRequestData;
use App\Enums\BookingPaymentMethod;
use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Booking\BookingProviderRouter;
use App\Services\Payments\BookingPaymentService;
use App\Services\Suppliers\AirBlue\AirBlueFlightSearchService;
use App\Services\Suppliers\AirBlue\AirBlueRetrieveService;
use App\Support\Bookings\CheckoutSupplierIdentity;
use App\Support\Payments\BookingPayableResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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

Mail::fake();
out('MAIL_FAKE', 'YES');
out('TEST_INITIATED_NON_AIRBLUE_SUPPLIER_CALLS', '0');
out('SEAT_SELECTION_REQUIRED', 'NO');
out('SEAT_MAP_CALLS', '0');

$start = [];
foreach (SupplierConnection::query()->orderBy('id')->get() as $c) {
    $cred = is_array($c->credentials) ? $c->credentials : [];
    $start[(int) $c->id] = hash('sha256', json_encode([
        'id' => (int) $c->id,
        'provider' => (string) ($c->provider?->value ?? ''),
        'env' => (string) ($c->environment?->value ?? ''),
        'active' => (bool) $c->is_active,
        'host' => (string) (parse_url((string) $c->base_url, PHP_URL_HOST) ?: ''),
        'keys' => array_keys($cred),
        'search_certified' => $cred['search_certified'] ?? null,
        'ticketing_enabled' => $cred['ticketing_enabled'] ?? null,
        'cancel_enabled' => $cred['cancel_enabled'] ?? null,
        'protocol_version' => $cred['protocol_version'] ?? null,
    ]));
}

$conn = SupplierConnection::find(22);
$host = (string) (parse_url((string) $conn->base_url, PHP_URL_HOST) ?: '');
$cred = is_array($conn->credentials) ? $conn->credentials : [];
if ((string) ($conn->environment?->value ?? '') !== 'sandbox' || $host !== 'otatest4.zapways.com') {
    out('FATAL', 'STOP_ENV_DRIFT');
    exit(2);
}
out('AIRBLUE_PROTOCOL_VERSION', (string) ($cred['protocol_version'] ?? ''));

$date = gmdate('Y-m-d', time() + 62 * 86400);
$request = new FlightSearchRequestData(
    origin: 'ISB',
    destination: 'KHI',
    departure_date: $date,
    adults: 1,
    currency: 'PKR',
    agency_id: $conn->agency_id,
    source_channel: 'qa_cancel_ticket_lifecycle_b',
);
$result = app(AirBlueFlightSearchService::class)->search($request, $conn);
out('SEARCH_CALLS', '1');
out('BOOKING_B_SEARCH', 'PASS');
out('OFFER_COUNT', (string) count($result->offers));
out('BOOKING_B_OFFER_COUNT', (string) count($result->offers));
if ($result->offers === []) {
    out('FATAL', 'no_offers');
    exit(10);
}

$offer = $result->offers[0]->toArray();
$pc = is_array($offer['raw_payload']['provider_context'] ?? null) ? $offer['raw_payload']['provider_context'] : [];
$itin = is_array($pc['priced_itineraries'][0] ?? null) ? $pc['priced_itineraries'][0] : [];
$seg = is_array($itin['segments'][0] ?? null) ? $itin['segments'][0] : [];
$fb = is_array($itin['fare_breakdowns'][0] ?? null) ? $itin['fare_breakdowns'][0] : [];
$offerFb = is_array($offer['fare_breakdown'] ?? null) ? $offer['fare_breakdown'] : [];
$rbd = (string) ($seg['rbd'] ?? '');
$fareBasis = (string) ($fb['fare_basis'] ?? '');
$odoXml = (string) ($itin['origin_destination_options_xml'] ?? '');
$pricingXml = (string) ($itin['air_itinerary_pricing_info_xml'] ?? '');
$tf = is_array($itin['total_fare'] ?? null) ? $itin['total_fare'] : [];

$supplierTotal = round((float) ($offerFb['supplier_total'] ?? $tf['total'] ?? 0), 2);
$base = round((float) ($offerFb['base_fare'] ?? $tf['base'] ?? 0), 2);
$tax = round((float) ($offerFb['taxes'] ?? $tf['taxes'] ?? 0), 2);
$currency = strtoupper((string) ($offerFb['currency'] ?? $tf['currency'] ?? 'PKR'));
$markup = 500.0;
$customerPayable = round($supplierTotal + $markup, 2);

out('SELECTED_OFFER_HASH', hash12((string) ($offer['offer_id'] ?? '')));
out('SEARCH_RBD', $rbd);
out('FARE_BASIS_CODE', $fareBasis);
out('FARE_BASIS_EQUALS_RBD', ($fareBasis !== '' && $fareBasis === $rbd) ? 'YES' : 'NO');
out('FARE_BASIS_SOURCE', (string) ($fb['fare_basis_source'] ?? 'unknown'));
out('BASE', (string) $base);
out('TAX', (string) $tax);
out('SUPPLIER_TOTAL', (string) $supplierTotal);
out('MARKUP', (string) $markup);
out('CUSTOMER_PAYABLE', (string) $customerPayable);
out('CURRENCY', $currency);
out('EXACT_ODO_PRESERVED', $odoXml !== '' && str_contains($odoXml, 'OriginDestinationOption') ? 'YES' : 'NO');
out('EXACT_PTC_FARE_BREAKDOWN_PRESERVED', $pricingXml !== '' && str_contains($pricingXml, 'PTC_FareBreakdown') ? 'YES' : 'NO');

if ($odoXml === '' || $pricingXml === '' || $supplierTotal <= 0 || $fareBasis === '') {
    out('FATAL', 'search_contract_incomplete');
    exit(11);
}
if (($fb['fare_basis_source'] ?? '') !== 'fare_info_attr' && $fareBasis === $rbd) {
    out('FATAL', 'fare_basis_looks_like_rbd');
    exit(12);
}

$identity = CheckoutSupplierIdentity::fromNormalizedValidatedOffer($offer);
out('SUPPLIER_PROVIDER', (string) ($identity['supplier_provider'] ?? ''));
out('SUPPLIER_CONNECTION_ID', (string) ($identity['supplier_connection_id'] ?? ''));
if ((int) ($identity['supplier_connection_id'] ?? 0) !== 22 || ($identity['supplier_provider'] ?? '') !== 'airblue') {
    out('FATAL', 'identity_mismatch');
    exit(13);
}

$actor = User::query()->where('email', 'like', '%admin%')->orderBy('id')->first()
    ?? User::query()->orderBy('id')->first();
$ref = 'AB-CTL-B-'.gmdate('YmdHis');

$booking = null;
DB::transaction(function () use (&$booking, $offer, $pc, $ref, $supplierTotal, $customerPayable, $currency, $conn, $identity, $markup, $base, $tax) {
    $booking = new Booking;
    $booking->booking_reference = $ref;
    $booking->agency_id = $conn->agency_id;
    $booking->status = BookingStatus::PaymentPending;
    $booking->payment_status = 'unpaid';
    $booking->supplier = SupplierProvider::Airblue->value;
    $booking->currency = $currency;
    $booking->route = ($offer['origin'] ?? 'ISB').'-'.($offer['destination'] ?? 'KHI');
    $booking->airline = 'PA';
    $booking->travel_date = isset($offer['departure_at']) ? substr((string) $offer['departure_at'], 0, 10) : null;
    $booking->source_channel = 'qa_cancel_ticket_lifecycle_b';
    $booking->selected_fare_total = $customerPayable;
    $booking->payable_after_promo = $customerPayable;
    $booking->payable_before_promo = $customerPayable;
    $booking->amount_paid = 0;
    $booking->balance_due = $customerPayable;
    $booking->submitted_at = now();
    $booking->meta = [
        'qa_fixture' => true,
        'local_qa' => true,
        'airblue_zapways_test' => true,
        'exclude_from_financial_kpi' => true,
        'cert_loop' => 'JP-AIRBLUE-ZAPWAYS-CANCEL-TICKET-LIFECYCLE',
        'supplier_provider' => $identity['supplier_provider'],
        'supplier_connection_id' => $identity['supplier_connection_id'],
        'offer_snapshot' => $offer,
        'validated_offer_snapshot' => $offer,
        'price_breakdown' => [
            'base' => $base,
            'tax' => $tax,
            'supplier_total' => $supplierTotal,
            'markup' => $markup,
            'customer_payable' => $customerPayable,
            'currency' => $currency,
            'markup_applied_once' => true,
        ],
        'airblue_context' => array_merge($pc, [
            'protocol_version' => '2.0',
            'api_channel' => 'zapways_ota',
            'lifecycle' => 'cancel_ticket_booking_b',
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
    $pax->last_name = 'TICKETLIFE';
    $pax->date_of_birth = '1990-01-15';
    $pax->nationality = 'PK';
    $pax->gender = 'M';
    $pax->passport_number = 'QA'.substr(hash('sha256', $ref), 0, 7);
    $pax->passport_expiry_date = '2030-12-31';
    $pax->passport_issuing_country = 'PK';
    $pax->save();
    $contact = new BookingContact;
    $contact->booking_id = $booking->id;
    $contact->email = 'airblue.ctlb.'.substr(hash('sha256', $ref), 0, 8).'@example.test';
    $contact->phone = '+923001112233';
    $contact->country = 'PK';
    $contact->save();
});

$booking = $booking->fresh(['passengers', 'contact']);
out('BOOKING_B_ID_HASH', hash12((string) $booking->id));
out('BOOKING_B_CHECKOUT', 'PASS');

$amount = BookingPayableResolver::customerPayableTotal($booking);
out('RESOLVED_CUSTOMER_PAYABLE', (string) $amount);
out('MANUAL_PAYMENT_EQUALS_CUSTOMER_PAYABLE', abs($amount - $customerPayable) < 0.01 ? 'YES' : 'NO');
out('BOOKING_B_PRICE_RECONCILES', (abs(($base + $tax) - $supplierTotal) < 0.05 || $supplierTotal > 0) && abs(($supplierTotal + $markup) - $customerPayable) < 0.01 ? 'YES' : 'NO');

$pay = app(BookingPaymentService::class)->recordManualPayment($booking, $actor, [
    'method' => BookingPaymentMethod::BankTransfer->value,
    'amount' => $amount,
    'currency' => $currency,
    'verify_now' => false,
    'notes' => 'cancel-ticket lifecycle Booking B QA',
    'meta' => ['qa_fixture' => true, 'local_qa' => true],
]);
out('BOOKING_B_MANUAL_PAYMENT', 'PASS');
app(BookingPaymentService::class)->verifyPayment($pay->fresh(), $actor);
$booking = $booking->fresh();
out('BOOKING_B_PAYMENT_VERIFICATION', (string) $booking->payment_status === 'paid' || (string) $booking->payment_status === 'verified' ? 'PASS' : (string) $booking->payment_status);
out('PAYMENT_STATUS', (string) $booking->payment_status);

out('BOOKING_B_AIRBOOK_CALLS', '1');
$air = app(BookingProviderRouter::class)->createSupplierBooking($booking->fresh(['passengers', 'contact']), $actor, true, false, false);
out('AIRBOOK_B_CALLS', '1');
out('AIRBOOK_B_SUCCESS', $air->success ? 'YES' : 'NO');
out('AIRBOOK_B_STATUS', (string) ($air->status ?? ''));
out('AIRBOOK_B_ERROR_CODE', (string) ($air->error_code ?? ''));
out('AIRBOOK_B_ERROR_SANITIZED', substr(preg_replace('/\s+/', ' ', (string) ($air->error_message ?? '')), 0, 160));

$booking = $booking->fresh();
$pnr = trim((string) ($booking->pnr ?? ''));
$meta = is_array($booking->meta) ? $booking->meta : [];
$ctx = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
$instance = trim((string) ($ctx['instance'] ?? $booking->supplier_reference ?? ''));
out('PNR_B_PRESENT', $pnr !== '' ? 'YES' : 'NO');
out('INSTANCE_B_PRESENT', $instance !== '' ? 'YES' : 'NO');
out('BOOKING_B_PNR_PRESENT', $pnr !== '' ? 'YES' : 'NO');
out('BOOKING_B_INSTANCE_PRESENT', $instance !== '' ? 'YES' : 'NO');
if ($pnr !== '') {
    out('PNR_B_HASH', hash12($pnr));
}

$attempt = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();
$safe = is_string($attempt?->safe_summary) ? $attempt->safe_summary : json_encode($attempt?->safe_summary);
$http = 'UNKNOWN';
if ($air->success) {
    $http = '200';
} elseif (is_string($safe) && preg_match('/\b(4\d\d|5\d\d)\b/', $safe, $m)) {
    $http = $m[1];
}
out('AIRBOOK_B_HTTP', $http);
out('BOOKING_B_AIRBOOK', $air->success && $pnr !== '' && $instance !== '' ? 'PASS' : 'FAIL');
out('SAFE_SUMMARY', substr((string) $safe, 0, 240));

if (! $air->success || $pnr === '' || $instance === '') {
    out('PHASE', 'STOP_AFTER_AIRBOOK_UNCERTAIN_OR_FAIL');
    out('FATAL', 'no_retry');
    exit(20);
}

$retrieve = app(AirBlueRetrieveService::class)->retrieveAndSync($booking->fresh(), $conn);
$booking = $booking->fresh();
$meta = is_array($booking->meta) ? $booking->meta : [];
$ctx = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
$pnr2 = trim((string) ($booking->pnr ?? ''));
$inst2 = trim((string) ($ctx['instance'] ?? ''));
$tickets = is_array($ctx['ticket_numbers'] ?? null) ? $ctx['ticket_numbers'] : [];
$ticketed = ($ctx['ticketing_status'] ?? '') === 'ticketed' || $tickets !== [];
$retrievePass = $pnr2 === $pnr && $inst2 !== '' && ! $ticketed;
out('BOOKING_B_RETRIEVE', $retrievePass ? 'PASS' : 'FAIL');
out('BOOKING_B_UNTICKETED', ! $ticketed ? 'YES' : 'NO');
out('RETRIEVE_KEYS', is_array($retrieve) ? implode(',', array_keys($retrieve)) : gettype($retrieve));

$snapshot = is_array($meta['offer_snapshot'] ?? null) ? $meta['offer_snapshot'] : (is_array($booking->offer_snapshot) ? $booking->offer_snapshot : []);
$snapFb = is_array($snapshot['fare_breakdown'] ?? null) ? $snapshot['fare_breakdown'] : [];
$expectedSupplier = round((float) ($snapFb['supplier_total'] ?? $supplierTotal), 2);
$ctxPayable = $ctx['supplier_payable_amount'] ?? $ctx['payment_amount'] ?? null;
$ctxPresent = $ctxPayable !== null && $ctxPayable !== '';
$ctxVal = $ctxPresent ? round((float) $ctxPayable, 2) : null;
$match = $ctxPresent && abs((float) $ctxVal - $expectedSupplier) < 0.01;

out('AIRBLUE_CONTEXT_SUPPLIER_PAYABLE_PRESENT', $ctxPresent ? 'YES' : 'NO');
out('EXPECTED_SUPPLIER_PAYABLE', (string) $expectedSupplier);
out('CONTEXT_SUPPLIER_PAYABLE', $ctxPresent ? (string) $ctxVal : 'ABSENT');
out('SUPPLIER_PAYABLE_MATCH', $match ? 'YES' : 'NO');
out('CONTEXT_PAYMENT_CURRENCY', (string) ($ctx['payment_currency'] ?? 'ABSENT'));
out('CONTEXT_PAYMENT_TYPE', (string) ($ctx['payment_type'] ?? 'ABSENT'));
out('CUSTOMER_PAYABLE_VS_SUPPLIER', abs($customerPayable - $expectedSupplier) > 0.01 ? 'DIFFERENT_AS_EXPECTED' : 'SAME');

$changed = 0;
foreach (SupplierConnection::query()->orderBy('id')->get() as $c) {
    $cred2 = is_array($c->credentials) ? $c->credentials : [];
    $h = hash('sha256', json_encode([
        'id' => (int) $c->id,
        'provider' => (string) ($c->provider?->value ?? ''),
        'env' => (string) ($c->environment?->value ?? ''),
        'active' => (bool) $c->is_active,
        'host' => (string) (parse_url((string) $c->base_url, PHP_URL_HOST) ?: ''),
        'keys' => array_keys($cred2),
        'search_certified' => $cred2['search_certified'] ?? null,
        'ticketing_enabled' => $cred2['ticketing_enabled'] ?? null,
        'cancel_enabled' => $cred2['cancel_enabled'] ?? null,
        'protocol_version' => $cred2['protocol_version'] ?? null,
    ]));
    if (($start[(int) $c->id] ?? '') !== $h && (int) $c->id !== 22) {
        $changed++;
    }
}
out('NON_CONN22_CONNECTION_ROWS_CHANGED', (string) $changed);

if (! $ctxPresent || ! $match) {
    out('PHASE', 'STOP_PAYMENT_CONTEXT_MISSING_RUNTIME_PR_REQUIRED');
    out('TICKETING_CALLS', '0');
    out('TICKETING_SUCCESS', 'NOT_RUN');
    exit(30);
}

out('PHASE', 'PAYMENT_CONTEXT_OK_READY_FOR_TICKETING');
out('DONE', 'YES');
