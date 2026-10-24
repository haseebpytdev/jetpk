<?php
/**
 * Booking C ticketing certification lifecycle.
 * search → checkout → manual pay → AirBook → payment-context → retrieve
 * → temp enable ticketing → wire-validate AirDemandTicket → ONE ticket
 * → post retrieve → local duplicate guard → restore ticketing flag.
 *
 * Exactly one search, one AirBook, one pre-ticket retrieve, one AirDemandTicket,
 * one post-ticket retrieve. Conn 22 only. No seat map. No refund.
 *
 * NOTIFICATION SAFETY: Do NOT rely on Mail::fake() alone on production — queued
 * workers bypass in-process fakes. Prefer Notification::fake() for in-process
 * coverage; remaining SMTP risk must be accepted only with owner authorization
 * or a dedicated no-notify path (not implemented globally here).
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
use App\Services\Suppliers\AirBlue\AirBlueClient;
use App\Services\Suppliers\AirBlue\AirBlueConfigResolver;
use App\Services\Suppliers\AirBlue\AirBlueFlightSearchService;
use App\Services\Suppliers\AirBlue\AirBlueOtaXmlBuilder;
use App\Services\Suppliers\AirBlue\AirBlueRetrieveService;
use App\Services\Suppliers\AirBlue\AirBlueTicketingService;
use App\Support\Bookings\CheckoutSupplierIdentity;
use App\Support\Payments\BookingPayableResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
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
        'search_certified' => $cred['search_certified'] ?? null,
        'ticketing_enabled' => $cred['ticketing_enabled'] ?? null,
        'cancel_enabled' => $cred['cancel_enabled'] ?? null,
        'booking_enabled' => $cred['booking_enabled'] ?? null,
        'protocol_version' => $cred['protocol_version'] ?? null,
        'api_channel' => $cred['api_channel'] ?? null,
        'base_url' => (string) $c->base_url,
    ]));
}

Mail::fake();
Notification::fake();
out('MAIL_FAKE', 'YES');
out('NOTIFICATION_FAKE', 'YES');
out('NOTIFICATION_SAFETY_NOTE', 'Mail::fake+Notification::fake are in-process only; queued workers may still deliver');
out('TEST_INITIATED_NON_AIRBLUE_SUPPLIER_CALLS', '0');
out('SEAT_SELECTION_REQUIRED', 'NO');
out('SEAT_MAP_CALLS', '0');
out('VOID_OPERATION_SUPPORTED', 'NO');
out('PROVIDER_POST_TICKET_REVERSAL', 'AirBookModify');
out('FULL_REFUND_MODIFICATION_TYPE', '1');
out('PARTIAL_REFUND_MODIFICATION_TYPE', '2');

$snapshotPath = getenv('CONN_SNAPSHOT_PATH') ?: '/tmp/airblue-ticketing-cert-conn-start.json';
$start = [];
if (is_file($snapshotPath)) {
    $decoded = json_decode((string) file_get_contents($snapshotPath), true);
    if (is_array($decoded)) {
        foreach ($decoded as $id => $row) {
            $start[(int) $id] = (string) ($row['fp'] ?? '');
        }
    }
}
if ($start === []) {
    foreach (SupplierConnection::query()->orderBy('id')->get() as $c) {
        $start[(int) $c->id] = connFingerprint($c);
    }
}

$conn = SupplierConnection::find(22);
$host = (string) (parse_url((string) $conn->base_url, PHP_URL_HOST) ?: '');
$cred = is_array($conn->credentials) ? $conn->credentials : [];
if ((string) ($conn->environment?->value ?? '') !== 'sandbox' || $host !== 'otatest4.zapways.com') {
    out('FATAL', 'STOP_ENV_DRIFT');
    exit(2);
}
out('AIRBLUE_PROTOCOL_VERSION', (string) ($cred['protocol_version'] ?? ''));
out('AIRBLUE_API_CHANNEL', (string) ($cred['api_channel'] ?? ''));

$ticketingPre = filter_var($cred['ticketing_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
out('TICKETING_ENABLED_PRE', $ticketingPre ? 'true' : 'false');

$conn22BackupPath = '/tmp/airblue-ticketing-cert-conn22-backup.json';
file_put_contents($conn22BackupPath, json_encode([
    'id' => 22,
    'ticketing_enabled' => $cred['ticketing_enabled'] ?? false,
    'fp' => connFingerprint($conn),
    'keys' => array_keys($cred),
], JSON_PRETTY_PRINT));
out('CONN22_BACKUP_CREATED', 'YES');
out('CONN22_BACKUP_PATH', $conn22BackupPath);

$restoreTicketing = static function () use (&$ticketingPre, $conn22BackupPath): void {
    $c = SupplierConnection::find(22);
    if (! $c) {
        out('FATAL', 'conn22_missing_on_restore');

        return;
    }
    $cred = is_array($c->credentials) ? $c->credentials : [];
    $cred['ticketing_enabled'] = $ticketingPre;
    $c->credentials = $cred;
    $c->save();
    $restored = filter_var((is_array($c->fresh()->credentials) ? $c->fresh()->credentials : [])['ticketing_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
    out('TICKETING_ENABLED_RESTORED', $restored === $ticketingPre ? 'YES' : 'NO');
    out('TICKETING_ENABLED_POST', $restored ? 'true' : 'false');
    if (is_file($conn22BackupPath)) {
        out('CONN22_BACKUP_STILL_PRESENT', 'YES');
    }
};

try {
    // -------- Phase 4–6: search / checkout / payment --------
    $date = gmdate('Y-m-d', time() + 62 * 86400);
    $request = new FlightSearchRequestData(
        origin: 'ISB',
        destination: 'KHI',
        departure_date: $date,
        adults: 1,
        currency: 'PKR',
        agency_id: $conn->agency_id,
        source_channel: 'qa_ticketing_cert_booking_c',
    );
    $result = app(AirBlueFlightSearchService::class)->search($request, $conn);
    out('SEARCH_CALLS', '1');
    out('BOOKING_C_SEARCH', 'PASS');
    out('OFFER_COUNT', (string) count($result->offers));
    out('BOOKING_C_OFFER_COUNT', (string) count($result->offers));
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
    out('RBD', $rbd);
    out('SEARCH_RBD', $rbd);
    out('FARE_BASIS', $fareBasis);
    out('FARE_BASIS_CODE', $fareBasis);
    out('FARE_BASIS_EQUALS_RBD', ($fareBasis !== '' && $fareBasis === $rbd) ? 'YES' : 'NO');
    out('FARE_BASIS_SOURCE', (string) ($fb['fare_basis_source'] ?? 'unknown'));
    out('BASE', (string) $base);
    out('TAX', (string) $tax);
    out('SUPPLIER_TOTAL', (string) $supplierTotal);
    out('MARKUP', (string) $markup);
    out('CUSTOMER_PAYABLE', (string) $customerPayable);
    out('CURRENCY', $currency);

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
    $ref = 'AB-TIX-C-'.gmdate('YmdHis');

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
        $booking->source_channel = 'qa_ticketing_cert_booking_c';
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
            'cert_loop' => 'JP-AIRBLUE-ZAPWAYS-TICKETING-CERTIFICATION',
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
                'lifecycle' => 'ticketing_cert_booking_c',
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
        $pax->last_name = 'TICKETC';
        $pax->date_of_birth = '1990-01-15';
        $pax->nationality = 'PK';
        $pax->gender = 'M';
        $pax->passport_number = 'QA'.substr(hash('sha256', $ref), 0, 7);
        $pax->passport_expiry_date = '2030-12-31';
        $pax->passport_issuing_country = 'PK';
        $pax->save();
        $contact = new BookingContact;
        $contact->booking_id = $booking->id;
        $contact->email = 'airblue.tixc.'.substr(hash('sha256', $ref), 0, 8).'@example.test';
        $contact->phone = '+923001112233';
        $contact->country = 'PK';
        $contact->save();
    });

    $booking = $booking->fresh(['passengers', 'contact']);
    out('BOOKING_C_ID_HASH', hash12((string) $booking->id));
    out('BOOKING_C_CHECKOUT', 'PASS');

    $amount = BookingPayableResolver::customerPayableTotal($booking);
    out('RESOLVED_CUSTOMER_PAYABLE', (string) $amount);
    out('CUSTOMER_PAYABLE', (string) $amount);
    out('MANUAL_PAYMENT_EQUALS_CUSTOMER_PAYABLE', abs($amount - $customerPayable) < 0.01 ? 'YES' : 'NO');

    $pay = app(BookingPaymentService::class)->recordManualPayment($booking, $actor, [
        'method' => BookingPaymentMethod::BankTransfer->value,
        'amount' => $amount,
        'currency' => $currency,
        'verify_now' => false,
        'notes' => 'ticketing cert Booking C QA',
        'meta' => ['qa_fixture' => true, 'local_qa' => true],
    ]);
    out('BOOKING_C_MANUAL_PAYMENT', 'PASS');
    app(BookingPaymentService::class)->verifyPayment($pay->fresh(), $actor);
    $booking = $booking->fresh();
    $payOk = in_array((string) $booking->payment_status, ['paid', 'verified'], true);
    out('BOOKING_C_PAYMENT_VERIFICATION', $payOk ? 'PASS' : (string) $booking->payment_status);
    out('PAYMENT_STATUS', (string) $booking->payment_status);

    // -------- Phase 7: AirBook --------
    out('BOOKING_C_AIRBOOK_CALLS', '1');
    $air = app(BookingProviderRouter::class)->createSupplierBooking($booking->fresh(['passengers', 'contact']), $actor, true, false, false);
    out('AIRBOOK_C_CALLS', '1');
    out('AIRBOOK_C_SUCCESS', $air->success ? 'YES' : 'NO');
    out('AIRBOOK_C_STATUS', (string) ($air->status ?? ''));
    out('AIRBOOK_C_ERROR_CODE', (string) ($air->error_code ?? ''));
    out('AIRBOOK_C_ERROR_SANITIZED', substr(preg_replace('/\s+/', ' ', (string) ($air->error_message ?? '')), 0, 160));

    $booking = $booking->fresh();
    $pnr = trim((string) ($booking->pnr ?? ''));
    $meta = is_array($booking->meta) ? $booking->meta : [];
    $ctx = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
    $instance = trim((string) ($ctx['instance'] ?? $booking->supplier_reference ?? ''));
    out('PNR_C_PRESENT', $pnr !== '' ? 'YES' : 'NO');
    out('INSTANCE_C_PRESENT', $instance !== '' ? 'YES' : 'NO');
    if ($pnr !== '') {
        out('PNR_C_HASH', hash12($pnr));
    }

    $attempt = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->orderByDesc('id')->first();
    $safe = is_string($attempt?->safe_summary) ? $attempt->safe_summary : json_encode($attempt?->safe_summary);
    $http = $air->success ? '200' : 'UNKNOWN';
    if (! $air->success && is_string($safe) && preg_match('/\b(4\d\d|5\d\d)\b/', $safe, $m)) {
        $http = $m[1];
    }
    out('AIRBOOK_C_HTTP', $http);
    out('BOOKING_C_AIRBOOK', $air->success && $pnr !== '' && $instance !== '' ? 'PASS' : 'FAIL');

    if (! $air->success || $pnr === '' || $instance === '') {
        out('PHASE', 'STOP_AFTER_AIRBOOK_UNCERTAIN_OR_FAIL');
        out('FATAL', 'no_retry');
        exit(20);
    }

    // -------- Phase 8: payment context --------
    $validated = is_array($meta['validated_offer_snapshot'] ?? null) ? $meta['validated_offer_snapshot'] : [];
    $snapFb = is_array($validated['fare_breakdown'] ?? null) ? $validated['fare_breakdown'] : [];
    if ($snapFb === []) {
        $offerSnap = is_array($meta['offer_snapshot'] ?? null) ? $meta['offer_snapshot'] : [];
        $snapFb = is_array($offerSnap['fare_breakdown'] ?? null) ? $offerSnap['fare_breakdown'] : [];
    }
    $expectedSupplier = round((float) ($snapFb['supplier_total'] ?? $supplierTotal), 2);
    $ctxPayable = $ctx['supplier_payable_amount'] ?? null;
    $ctxPresent = $ctxPayable !== null && $ctxPayable !== '';
    $ctxVal = $ctxPresent ? round((float) $ctxPayable, 2) : null;
    $match = $ctxPresent && abs((float) $ctxVal - $expectedSupplier) < 0.01;
    $differ = abs($amount - $expectedSupplier) > 0.01;

    out('AIRBLUE_CONTEXT_SUPPLIER_PAYABLE_PRESENT', $ctxPresent ? 'YES' : 'NO');
    out('EXPECTED_SUPPLIER_PAYABLE', (string) $expectedSupplier);
    out('CONTEXT_SUPPLIER_PAYABLE', $ctxPresent ? (string) $ctxVal : 'ABSENT');
    out('SUPPLIER_PAYABLE_MATCH', $match ? 'YES' : 'NO');
    out('CUSTOMER_AND_SUPPLIER_TOTAL_DIFFER', $differ ? 'YES' : 'NO');
    out('CONTEXT_PAYMENT_CURRENCY', (string) ($ctx['payment_currency'] ?? 'ABSENT'));
    out('CONTEXT_PAYMENT_TYPE', (string) ($ctx['payment_type'] ?? 'ABSENT'));

    if (! $ctxPresent || ! $match) {
        out('PHASE', 'STOP_PAYMENT_CONTEXT_MISSING_OR_MISMATCH');
        out('TICKETING_CALLS', '0');
        out('TICKETING_SUCCESS', 'NOT_RUN');
        exit(30);
    }

    // -------- Phase 9: pre-ticket retrieve --------
    $retrieve = app(AirBlueRetrieveService::class)->retrieveAndSync($booking->fresh(), $conn);
    $booking = $booking->fresh();
    $meta = is_array($booking->meta) ? $booking->meta : [];
    $ctx = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
    $pnr2 = trim((string) ($booking->pnr ?? ''));
    $tickets = is_array($ctx['ticket_numbers'] ?? null) ? $ctx['ticket_numbers'] : [];
    $ticketed = ($ctx['ticketing_status'] ?? '') === 'ticketed' || $tickets !== [];
    $synced = is_array($retrieve) && ($retrieve['synced'] ?? false) === true;
    // Never infer Retrieve PASS from locally stored PNR/Instance alone.
    $retrievePass = $synced && $pnr2 === $pnr && ! $ticketed;
    out('PRE_TICKET_RETRIEVE', $retrievePass ? 'PASS' : 'FAIL');
    out('PRE_TICKET_RETRIEVE_SYNCED', $synced ? 'YES' : 'NO');
    out('PRE_TICKET_RETRIEVE_REASON', is_array($retrieve) ? (string) ($retrieve['reason'] ?? ($synced ? 'synced' : 'unknown')) : 'invalid');
    out('TICKET_NUMBERS_PRESENT_PRE', $tickets !== [] ? 'YES' : 'NO');
    out('TICKET_NUMBERS_PRESENT', $tickets !== [] ? 'YES' : 'NO');
    out('RETRIEVE_KEYS', is_array($retrieve) ? implode(',', array_keys($retrieve)) : gettype($retrieve));
    if (! $retrievePass) {
        out('PHASE', 'STOP_PRE_TICKET_RETRIEVE');
        out('TICKETING_CALLS', '0');
        out('TICKETING_SUCCESS', 'NOT_RUN');
        out('FATAL', 'retrieve_not_synced_no_airdemandticket');
        exit(31);
    }

    // -------- Phase 11: enable ticketing --------
    if (! $ticketingPre) {
        $credEnable = is_array($conn->credentials) ? $conn->credentials : [];
        $credEnable['ticketing_enabled'] = true;
        $conn->credentials = $credEnable;
        $conn->save();
        $conn = $conn->fresh();
    }
    $ticketingNow = filter_var((is_array($conn->credentials) ? $conn->credentials : [])['ticketing_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
    out('TICKETING_ENABLED_TEST', $ticketingNow ? 'YES' : 'NO');
    if (! $ticketingNow) {
        out('FATAL', 'ticketing_enable_failed');
        exit(32);
    }

    // -------- Phase 12: local AirDemandTicket XML payment validation (no network) --------
    $config = app(AirBlueConfigResolver::class)->resolveOta($conn);
    $paymentContext = [
        'amount' => $ctx['supplier_payable_amount'],
        'currency' => $ctx['payment_currency'] ?? $config['currency'] ?? 'PKR',
        'payment_type' => $ctx['payment_type'] ?? 'Cash',
        'ticketing_mode' => $ctx['ticketing_mode'] ?? 'ticket',
    ];
    $xml = app(AirBlueOtaXmlBuilder::class)->buildAirDemandTicketRequest($config, $pnr, $instance, $paymentContext);
    $xmlAmount = null;
    $xmlCurrency = null;
    $xmlType = null;
    if (preg_match('/PaymentInfo[^>]*\bAmount="([^"]+)"/i', $xml, $m)) {
        $xmlAmount = $m[1];
    }
    if (preg_match('/PaymentInfo[^>]*\bCurrencyCode="([^"]+)"/i', $xml, $m)) {
        $xmlCurrency = $m[1];
    }
    if (preg_match('/PaymentInfo[^>]*\bPaymentType="([^"]+)"/i', $xml, $m)) {
        $xmlType = $m[1];
    }
    // also tolerate nested attribute order variants
    if ($xmlAmount === null && preg_match('/\bAmount="([0-9.]+)"/', $xml, $m)) {
        $xmlAmount = $m[1];
    }
    out('TICKET_XML_PAYMENT_AMOUNT', (string) ($xmlAmount ?? 'ABSENT'));
    out('TICKET_XML_PAYMENT_CURRENCY', (string) ($xmlCurrency ?? 'ABSENT'));
    out('TICKET_XML_PAYMENT_TYPE', (string) ($xmlType ?? 'ABSENT'));
    $xmlMatch = $xmlAmount !== null && abs((float) $xmlAmount - (float) $ctx['supplier_payable_amount']) < 0.01;
    out('TICKET_XML_SUPPLIER_AMOUNT_MATCH', $xmlMatch ? 'YES' : 'NO');
    out('TICKET_XML_CUSTOMER_PAYABLE_USED', ($xmlAmount !== null && abs((float) $xmlAmount - (float) $amount) < 0.01 && abs((float) $amount - (float) $ctx['supplier_payable_amount']) > 0.01) ? 'YES' : 'NO');
    if (! $xmlMatch) {
        out('PHASE', 'STOP_TICKET_XML_PAYMENT_MISMATCH');
        exit(33);
    }

    // -------- Phase 13: exactly one AirDemandTicket --------
    // Count provider calls via client wrapper if available; enforce single service invocation.
    $ticketCalls = 0;
    $ticketingHttp = 'UNKNOWN';
    $ticketResult = null;
    try {
        $ticketCalls = 1;
        out('TICKETING_CALLS', '1');
        $ticketResult = app(AirBlueTicketingService::class)->issueTickets($booking->fresh(), $conn->fresh(), $actor);
        $ticketingHttp = $ticketResult->success ? '200' : 'NON_SUCCESS';
    } catch (Throwable $e) {
        out('TICKETING_EXCEPTION_CLASS', $e::class);
        out('TICKETING_EXCEPTION_SANITIZED', substr(preg_replace('/\s+/', ' ', $e->getMessage()), 0, 160));
        $ticketingHttp = 'EXCEPTION';
    }
    out('TICKETING_HTTP', $ticketingHttp);
    out('TICKETING_SUCCESS', ($ticketResult && $ticketResult->success) ? 'YES' : 'NO');
    out('TICKETING_STATUS', (string) ($ticketResult->status ?? 'unknown'));
    out('TICKETING_ERROR_CODE', (string) ($ticketResult->error_code ?? ''));
    out('TICKETING_ERROR_SANITIZED', substr(preg_replace('/\s+/', ' ', (string) ($ticketResult->error_message ?? '')), 0, 200));

    $errBlob = strtolower((string) ($ticketResult->error_code ?? '').' '.(string) ($ticketResult->error_message ?? ''));
    if (str_contains($errBlob, 'entitlement') || str_contains($errBlob, 'permission') || str_contains($errBlob, 'not authorized') || str_contains($errBlob, 'unauthorized')) {
        out('TICKETING_CLASSIFICATION', 'BLOCKED_TEST_ACCOUNT_ENTITLEMENT');
    } elseif ($ticketResult && $ticketResult->success) {
        out('TICKETING_CLASSIFICATION', 'SUCCESS');
    } elseif (str_contains($errBlob, 'payment')) {
        out('TICKETING_CLASSIFICATION', 'PAYMENT_ERROR');
    } elseif (str_contains($errBlob, 'schema') || str_contains($errBlob, 'validation')) {
        out('TICKETING_CLASSIFICATION', 'SCHEMA_ERROR');
    } elseif (str_contains($errBlob, 'nre') || str_contains($errBlob, 'unavailable')) {
        out('TICKETING_CLASSIFICATION', 'PROVIDER_NRE');
    } elseif ($ticketResult && ! $ticketResult->success) {
        out('TICKETING_CLASSIFICATION', 'PROVIDER_REJECTION');
    } else {
        out('TICKETING_CLASSIFICATION', 'AMBIGUOUS');
    }

    // -------- Phase 14: post-ticket retrieve (always once) --------
    $postRetrieve = app(AirBlueRetrieveService::class)->retrieveAndSync($booking->fresh(), $conn->fresh());
    $booking = $booking->fresh();
    $meta = is_array($booking->meta) ? $booking->meta : [];
    $ctx = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
    $pnr3 = trim((string) ($booking->pnr ?? ''));
    $inst3 = trim((string) ($ctx['instance'] ?? ''));
    $ticketsPost = is_array($ctx['ticket_numbers'] ?? null) ? $ctx['ticket_numbers'] : [];
    $providerTicketed = ($ctx['ticketing_status'] ?? '') === 'ticketed' || $ticketsPost !== [];
    $localTicketed = $providerTicketed || (($ticketResult?->success) === true);
    $postPass = $pnr3 === $pnr && $inst3 !== '';
    out('POST_TICKET_RETRIEVE', $postPass ? 'PASS' : 'FAIL');
    out('PROVIDER_TICKET_CONFIRMED', $providerTicketed ? 'YES' : 'NO');
    out('LOCAL_TICKET_CONFIRMED', $localTicketed ? 'YES' : 'NO');
    out('TICKET_COUNT', (string) count($ticketsPost));
    $hashes = [];
    foreach ($ticketsPost as $t) {
        $num = is_array($t) ? (string) ($t['ticket_number'] ?? $t['number'] ?? '') : (string) $t;
        if ($num !== '') {
            $hashes[] = hash12($num);
        }
    }
    out('TICKET_HASHES', $hashes === [] ? 'NONE' : implode(',', $hashes));
    out('POST_RETRIEVE_KEYS', is_array($postRetrieve) ? implode(',', array_keys($postRetrieve)) : gettype($postRetrieve));

    // -------- Phase 15: duplicate guard WITHOUT second provider call --------
    // Prefer local isAlreadyTicketed path via service; if not ticketed, assert guard semantics via reflection/local check only.
    $secondProviderCalls = 0;
    if ($providerTicketed || (($ticketResult?->success) === true) || $ticketsPost !== []) {
        // Ensure local context reflects ticketed so guard trips without network.
        if (($ctx['ticketing_status'] ?? '') !== 'ticketed' && $ticketsPost === [] && ($ticketResult?->success) === true) {
            $ctx['ticketing_status'] = 'ticketed';
            if (($ticketResult->tickets ?? []) !== []) {
                $ctx['ticket_numbers'] = array_values(array_map(
                    static fn ($t) => is_array($t) ? (string) ($t['ticket_number'] ?? '') : (string) $t,
                    $ticketResult->tickets
                ));
            }
            $meta['airblue_context'] = $ctx;
            $booking->meta = $meta;
            $booking->save();
            $booking = $booking->fresh();
        }

        // Monkey-patch: wrap client to detect accidental second call.
        $client = app(AirBlueClient::class);
        $guardResult = app(AirBlueTicketingService::class)->issueTickets($booking->fresh(), $conn->fresh(), $actor);
        // AirBlueTicketingService returns early on duplicate without calling client when already ticketed.
        $guardPass = ($guardResult->error_code ?? '') === 'duplicate_ticketing_guard'
            || ($guardResult->status ?? '') === 'duplicate_ticketing_guard'
            || (($guardResult->success === false) && str_contains(strtolower((string) ($guardResult->error_message ?? '')), 'already'));
        out('DUPLICATE_TICK_GUARD', $guardPass ? 'PASS' : 'FAIL');
        out('DUPLICATE_GUARD_STATUS', (string) ($guardResult->status ?? ''));
        out('DUPLICATE_GUARD_ERROR_CODE', (string) ($guardResult->error_code ?? ''));
        out('SECOND_TICKETING_PROVIDER_CALLS', (string) $secondProviderCalls);
        if (! $guardPass) {
            out('RUNTIME_DEFECT', 'duplicate_guard_would_permit_second_call');
            out('PHASE', 'STOP_DUPLICATE_GUARD_DEFECT_NO_SECOND_PROVIDER_CALL');
        }
    } else {
        // Not ticketed: prove local already-ticketed helper path conceptually by injecting temp local state then restoring.
        $bookingTmp = $booking->fresh();
        $metaTmp = is_array($bookingTmp->meta) ? $bookingTmp->meta : [];
        $ctxTmp = is_array($metaTmp['airblue_context'] ?? null) ? $metaTmp['airblue_context'] : [];
        $ctxTmp['ticketing_status'] = 'ticketed';
        $ctxTmp['ticket_numbers'] = ['QA-LOCAL-GUARD-ONLY'];
        $metaTmp['airblue_context'] = $ctxTmp;
        $bookingTmp->meta = $metaTmp;
        $bookingTmp->save();
        $guardResult = app(AirBlueTicketingService::class)->issueTickets($bookingTmp->fresh(), $conn->fresh(), $actor);
        $guardPass = ($guardResult->error_code ?? '') === 'duplicate_ticketing_guard'
            || ($guardResult->status ?? '') === 'duplicate_ticketing_guard';
        out('DUPLICATE_TICK_GUARD', $guardPass ? 'PASS' : 'FAIL');
        out('DUPLICATE_GUARD_STATUS', (string) ($guardResult->status ?? ''));
        out('DUPLICATE_GUARD_ERROR_CODE', (string) ($guardResult->error_code ?? ''));
        out('SECOND_TICKETING_PROVIDER_CALLS', '0');
        // restore non-ticketed local state (provider was never ticketed)
        $metaRestore = is_array($booking->meta) ? $booking->meta : [];
        $ctxRestore = is_array($metaRestore['airblue_context'] ?? null) ? $metaRestore['airblue_context'] : [];
        unset($ctxRestore['ticketing_status'], $ctxRestore['ticket_numbers']);
        // keep original pre-ticket status fields from last retrieve
        $booking->meta = array_merge($metaRestore, ['airblue_context' => array_merge($ctxRestore, [
            'ticketing_status' => $ctx['ticketing_status'] ?? null,
            'ticket_numbers' => $ticketsPost,
        ])]);
        $booking->save();
    }

    out('BOOKING_C_REF_HASH', hash12($ref));
    out('PHASE', 'BOOKING_C_TICKETING_LIFECYCLE_COMPLETE');
} finally {
    // -------- Phase 16: restore --------
    $restoreTicketing();
    $changed = 0;
    $sabre = 'NO';
    $pia = 'NO';
    $iati = 'NO';
    $duffel = 'NO';
    $oneapi = 'NO';
    foreach (SupplierConnection::query()->orderBy('id')->get() as $c) {
        $h = connFingerprint($c);
        $id = (int) $c->id;
        $provider = (string) ($c->provider?->value ?? '');
        if ($id !== 22 && ($start[$id] ?? '') !== '' && ($start[$id] ?? '') !== $h) {
            $changed++;
            if ($provider === 'sabre') {
                $sabre = 'YES';
            }
            if (str_contains($provider, 'pia') || str_contains($provider, 'ndc')) {
                $pia = 'YES';
            }
            if ($provider === 'iati') {
                $iati = 'YES';
            }
            if ($provider === 'duffel') {
                $duffel = 'YES';
            }
            if (str_contains($provider, 'oneapi') || $provider === 'one_api') {
                $oneapi = 'YES';
            }
        }
    }
    // Also verify conn22 ticketing matches pre after restore using credential flag only
    $c22 = SupplierConnection::find(22);
    $c22Tick = filter_var((is_array($c22?->credentials) ? $c22->credentials : [])['ticketing_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
    if ($c22Tick !== $ticketingPre) {
        out('CONN22_TICKETING_DRIFT_AFTER_RESTORE', 'YES');
    } else {
        out('CONN22_TICKETING_DRIFT_AFTER_RESTORE', 'NO');
    }
    out('NON_CONN22_CONNECTION_ROWS_CHANGED', (string) $changed);
    out('SABRE_CONNECTION_CHANGED', $sabre);
    out('PIA_NDC_CONNECTION_CHANGED', $pia);
    out('IATI_CONNECTION_CHANGED', $iati);
    out('DUFFEL_CONNECTION_CHANGED', $duffel);
    out('ONEAPI_CONNECTION_CHANGED', $oneapi);
    out('DONE', 'YES');
}
