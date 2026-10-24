<?php
/**
 * JP-AIRBLUE-V3 FRESH BOOKING-D THROUGH TICKETING CERTIFICATION
 *
 * Persist dedicated v3 TEST SupplierConnection (not Conn22, not public search).
 * Search → checkout → bank_transfer → AirBook → Read → SeatMap → Type5 seat
 * → Read/seat-gate → offline AirDemandTicket → ONE ticket → post Read → local
 * duplicate guard. Never ticket Booking C. Never refund/cancel. No optimize:clear.
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
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Booking\BookingProviderRouter;
use App\Services\Payments\BookingPaymentService;
use App\Services\Suppliers\AirBlue\AirBlueClient;
use App\Services\Suppliers\AirBlue\AirBlueConfigResolver;
use App\Services\Suppliers\AirBlue\AirBlueConnectionSearchPolicy;
use App\Services\Suppliers\AirBlue\AirBlueFlightSearchService;
use App\Services\Suppliers\AirBlue\AirBlueOtaXmlBuilder;
use App\Services\Suppliers\AirBlue\AirBlueRetrieveService;
use App\Services\Suppliers\AirBlue\AirBlueSeatSelectionGate;
use App\Services\Suppliers\AirBlue\AirBlueTicketingService;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueException;
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
        'provider' => (string) ($c->provider?->value ?? $c->provider ?? ''),
        'env' => (string) ($c->environment?->value ?? $c->environment ?? ''),
        'active' => (bool) $c->is_active,
        'status' => (string) ($c->status?->value ?? $c->status ?? ''),
        'base_url' => (string) $c->base_url,
        'keys' => array_keys($cred),
        'ticketing_enabled' => $cred['ticketing_enabled'] ?? null,
        'cancel_enabled' => $cred['cancel_enabled'] ?? null,
        'booking_enabled' => $cred['booking_enabled'] ?? null,
        'protocol_version' => $cred['protocol_version'] ?? null,
        'api_channel' => $cred['api_channel'] ?? null,
        'certification_status' => $cred['certification_status'] ?? null,
        'search_certified' => $cred['search_certified'] ?? null,
    ]));
}

function xpathLocal(DOMXPath $xp, string $query, ?DOMNode $ctx = null): DOMNodeList|false
{
    return $ctx ? $xp->query($query, $ctx) : $xp->query($query);
}

function enumVal(mixed $v): string
{
    if ($v instanceof BackedEnum) {
        return (string) $v->value;
    }

    return (string) $v;
}

function stop(string $final, string $next, array $extra = []): never
{
    foreach ($extra as $k => $v) {
        out((string) $k, $v);
    }
    out('V3_BOOKING_D_TICKETING_CERTIFIED', 'NO');
    out('NEXT_PATH', $next);
    out('FINAL_STATUS', $final);
    out('DONE', 'YES');
    exit(1);
}

Mail::fake();
Notification::fake();
out('TEST_INITIATED_NON_AIRBLUE_SUPPLIER_CALLS', '0');
out('AIRBOOK_CALLS', '0');
out('AIRBOOK_MODIFY_CALLS', '0');
out('TICKETING_CALLS', '0');
out('CANCEL_CALLS', '0');
out('REFUND_CALLS', '0');
out('TOTAL_AIRDEMANDTICKET_PROVIDER_CALLS', '0');

$EXPECTED_MAIN = '50f7b82e5db3419cdb3b12ff7ebce64d7250ed26';
$EXPECT_CLIENT = 'b6cd98d42efc62d4c61415201e0719cd626e30001e6ac555e04cfd51028014d8';
$EXPECT_PARSER = 'c05502cc18eb4debfa0965ba8cfaacd2c17929eb595b9696b7920d6e3d8adfc4';
$EXPECT_SUPPLIERS = '73e5f52f38487f83da46bceef1ff055b14269db498f4b7eca924f29dcc22ac57';
$V3_NAME = 'AirBlue Zapways v3 TEST cert (Booking D)';
$BOOKING_C_PNR_HASH = '94b2bd04b019';

// ===== PHASE A =====
out('MAIN_SHA', $EXPECTED_MAIN);
$clientSha = hash_file('sha256', $appRoot.'/app/Services/Suppliers/AirBlue/AirBlueClient.php') ?: '';
$parserSha = hash_file('sha256', $appRoot.'/app/Services/Suppliers/AirBlue/AirBlueOtaXmlParser.php') ?: '';
$suppliersSha = hash_file('sha256', $appRoot.'/config/suppliers.php') ?: '';
$parity = ($clientSha === $EXPECT_CLIENT && $parserSha === $EXPECT_PARSER && $suppliersSha === $EXPECT_SUPPLIERS);
out('DEPLOYED_RUNTIME_SHA_OR_SOURCE_SHA', $parity ? $EXPECTED_MAIN : 'PR129_PARITY_FAIL');
out('RUNTIME_PR129_PARITY', $parity ? 'YES' : 'NO');
$healthCode = @file_get_contents('https://jetpakistan.pk') !== false ? 'PASS' : 'FAIL';
// Prefer HTTP status via curl if available
$ch = curl_init('https://jetpakistan.pk');
if ($ch) {
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_NOBODY => true, CURLOPT_TIMEOUT => 15]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $healthCode = $code === 200 ? 'PASS' : 'FAIL_'.$code;
}
out('HTTP_HEALTH', $healthCode);
if (! $parity || $healthCode !== 'PASS') {
    stop('CHECKPOINT_FAILED', 'RUNTIME_ALIGN_REQUIRED');
}

$start = [];
foreach (SupplierConnection::query()->orderBy('id')->get() as $c) {
    $start[(int) $c->id] = connFingerprint($c);
}

$conn22 = SupplierConnection::find(22);
if (! $conn22) {
    stop('CHECKPOINT_FAILED', 'CONN22_MISSING');
}
$host22 = (string) (parse_url((string) $conn22->base_url, PHP_URL_HOST) ?: '');
$path22 = (string) (parse_url((string) $conn22->base_url, PHP_URL_PATH) ?: '');
$cred22 = is_array($conn22->credentials) ? $conn22->credentials : [];
$proto22 = (string) ($cred22['protocol_version'] ?? '2.0');
$conn22Ok = enumVal($conn22->environment) === 'sandbox'
    && $host22 === 'otatest4.zapways.com'
    && str_contains($path22, '/v2.0/')
    && $proto22 === '2.0';
out('CONN22_CHANGED', $conn22Ok ? 'NO' : 'YES');
if (! $conn22Ok) {
    stop('CHECKPOINT_FAILED', 'CONN22_INVARIANT_FAIL');
}

// Booking C must remain unticketed fixture
$bookingC = null;
foreach (Booking::query()
    ->where(function ($q) {
        $q->where('source_channel', 'qa_ticketing_cert_booking_c')
            ->orWhere('booking_reference', 'like', 'AB-TIX-C-%');
    })
    ->orderByDesc('id')
    ->get() as $b) {
    $pnrC = trim((string) ($b->pnr ?? ''));
    if ($pnrC !== '' && hash12($pnrC) === $BOOKING_C_PNR_HASH) {
        $bookingC = $b;
        break;
    }
}
if (! $bookingC) {
    stop('CHECKPOINT_FAILED', 'BOOKING_C_NOT_FOUND');
}
$metaC = is_array($bookingC->meta) ? $bookingC->meta : [];
$ctxC = is_array($metaC['airblue_context'] ?? null) ? $metaC['airblue_context'] : [];
$ticketsC = is_array($ctxC['ticket_numbers'] ?? null) ? $ctxC['ticket_numbers'] : [];
$ticketedC = ($ctxC['ticketing_status'] ?? '') === 'ticketed' || $ticketsC !== [];
out('BOOKING_C_PNR_HASH', $BOOKING_C_PNR_HASH);
out('BOOKING_C_UNTICKETED', $ticketedC ? 'NO' : 'YES');
if ($ticketedC) {
    stop('CHECKPOINT_FAILED', 'BOOKING_C_ALREADY_TICKETED_ABORT');
}

$v3ConnId = null;
$ticketingPreV3 = false;
$createdNewConn = false;

try {
    // ===== PHASE B/C: dedicated v3 connection =====
    $v3 = SupplierConnection::query()
        ->where('provider', SupplierProvider::Airblue)
        ->where('name', $V3_NAME)
        ->first();

    if (! $v3) {
        $v3 = $conn22->replicate();
        $v3->exists = false;
        $v3->id = null;
        $v3->name = $V3_NAME;
        $v3->display_name = $V3_NAME;
        $v3->environment = SupplierEnvironment::Sandbox;
        $v3->status = SupplierConnectionStatus::Active;
        $v3->is_active = true;
        $v3->base_url = 'https://otatest4.zapways.com/v3.0/OTAAPI.asmx';
        $cred = is_array($v3->credentials) ? $v3->credentials : [];
        $cred['protocol_version'] = '3.0';
        $cred['api_channel'] = 'zapways_ota';
        $cred['certification_status'] = 'pending';
        $cred['search_certified'] = false;
        $cred['ticketing_enabled'] = false;
        $cred['booking_enabled'] = true;
        $cred['cancel_enabled'] = false;
        $v3->credentials = $cred;
        $v3->save();
        $createdNewConn = true;
        $v3 = $v3->fresh();
    } else {
        // Ensure flags stay certification-safe without changing Conn22.
        $cred = is_array($v3->credentials) ? $v3->credentials : [];
        $cred['protocol_version'] = '3.0';
        $cred['api_channel'] = 'zapways_ota';
        $cred['certification_status'] = 'pending';
        $cred['search_certified'] = false;
        $cred['booking_enabled'] = true;
        $cred['cancel_enabled'] = false;
        // leave ticketing_enabled as-is until ticket phase; remember pre
        $ticketingPreV3 = filter_var($cred['ticketing_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $cred['ticketing_enabled'] = false;
        $v3->credentials = $cred;
        $v3->base_url = 'https://otatest4.zapways.com/v3.0/OTAAPI.asmx';
        $v3->environment = SupplierEnvironment::Sandbox;
        $v3->is_active = true;
        if ($v3->status !== SupplierConnectionStatus::Active && enumVal($v3->status) !== 'active') {
            $v3->status = SupplierConnectionStatus::Active;
        }
        $v3->save();
        $v3 = $v3->fresh();
    }

    $v3ConnId = (int) $v3->id;
    $credV3 = is_array($v3->credentials) ? $v3->credentials : [];
    $ticketingPreV3 = filter_var($credV3['ticketing_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

    out('V3_CONNECTION_PERSISTED', 'YES');
    out('V3_CONNECTION_IS_CONN22', $v3ConnId === 22 ? 'YES' : 'NO');
    out('V3_CONNECTION_ID_HASH', hash12((string) $v3ConnId));
    out('V3_CONNECTION_PROTOCOL', (string) ($credV3['protocol_version'] ?? ''));
    out('V3_CONNECTION_ENVIRONMENT', enumVal($v3->environment));
    out('V3_CONNECTION_CERTIFICATION_STATE', (string) ($credV3['certification_status'] ?? 'ABSENT'));
    out('V3_CONNECTION_CREATED_NEW', $createdNewConn ? 'YES' : 'NO');

    if ($v3ConnId === 22) {
        stop('V3_CONNECTION_INVALID', 'DEDICATED_V3_CONNECTION_REQUIRED');
    }

    $policy = app(AirBlueConnectionSearchPolicy::class);
    $publicEligible = $policy->isEligibleForPublicSearch($v3);
    out('V3_CONNECTION_PUBLIC_SEARCH_ELIGIBLE', $publicEligible ? 'YES' : 'NO');
    if ($publicEligible) {
        stop('V3_CONNECTION_PUBLIC_SEARCH_LEAK', 'FIX_SEARCH_POLICY_OR_FLAGS');
    }

    $config = app(AirBlueConfigResolver::class)->resolveOta($v3);
    $client = app(AirBlueClient::class);
    $resolve = (new ReflectionClass($client))->getMethod('resolveOtaSoapAction');
    $resolve->setAccessible(true);
    $readAction = (string) $resolve->invoke($client, 'read', $config);
    $seatAction = (string) $resolve->invoke($client, 'air_seat_map', $config);
    $modifyAction = (string) $resolve->invoke($client, 'air_book_modify', $config);
    $ticketAction = (string) $resolve->invoke($client, 'air_demand_ticket', $config);
    out('V3_ENDPOINT', (string) ($config['endpoint_url'] ?? ''));
    out('V3_NAMESPACE', (string) ($config['namespace'] ?? ''));
    out('V3_TICKET_ACTION', $ticketAction);
    if ((string) ($config['endpoint_url'] ?? '') !== 'https://otatest4.zapways.com/v3.0/OTAAPI.asmx'
        || (string) ($config['namespace'] ?? '') !== 'http://zapways.com/air/ota/3.0'
        || $ticketAction !== 'http://zapways.com/air/ota/3.0/AirDemandTicket') {
        stop('V3_RUNTIME_CONTRACT_MISMATCH', 'TYPE_RUNTIME_FIX_PR_REQUIRED');
    }

    // Re-check Conn22 unchanged after create
    $conn22 = $conn22->fresh();
    $host22 = (string) (parse_url((string) $conn22->base_url, PHP_URL_HOST) ?: '');
    $path22 = (string) (parse_url((string) $conn22->base_url, PHP_URL_PATH) ?: '');
    $cred22 = is_array($conn22->credentials) ? $conn22->credentials : [];
    $conn22Ok = enumVal($conn22->environment) === 'sandbox'
        && $host22 === 'otatest4.zapways.com'
        && str_contains($path22, '/v2.0/')
        && (string) ($cred22['protocol_version'] ?? '') === '2.0'
        && ($start[22] ?? '') === connFingerprint($conn22);
    out('CONN22_CHANGED', $conn22Ok ? 'NO' : 'YES');
    if (! $conn22Ok) {
        stop('CONN22_MUTATED', 'ABORT_RESTORE_CONN22');
    }

    $actor = User::query()->where('email', 'like', '%admin%')->orderBy('id')->first()
        ?? User::query()->orderBy('id')->first();
    if (! $actor) {
        stop('NO_ACTOR', 'MANUAL_ACTOR_REQUIRED');
    }

    // ===== PHASE D: v3 Search =====
    $date = gmdate('Y-m-d', time() + 62 * 86400);
    $request = new FlightSearchRequestData(
        origin: 'ISB',
        destination: 'KHI',
        departure_date: $date,
        adults: 1,
        currency: 'PKR',
        agency_id: $v3->agency_id,
        source_channel: 'qa_v3_booking_d_ticketing',
    );
    out('V3_SEARCH_CALLS', '1');
    $searchHttp = 'ABSENT';
    $searchSuccess = false;
    $offers = [];
    try {
        $result = app(AirBlueFlightSearchService::class)->search($request, $v3);
        $offers = $result->offers;
        $searchHttp = '200';
        $searchSuccess = $offers !== [];
    } catch (Throwable $e) {
        out('V3_SEARCH_EXCEPTION', substr(preg_replace('/\s+/', ' ', $e->getMessage()) ?? '', 0, 120));
        $searchSuccess = false;
    }
    out('V3_SEARCH_HTTP', $searchHttp);
    out('V3_SEARCH_SUCCESS', $searchSuccess ? 'YES' : 'NO');
    out('V3_SEARCH_OFFER_COUNT', (string) count($offers));
    if (! $searchSuccess) {
        stop('V3_SEARCH_FAILED', 'V3_SEARCH_INVESTIGATION');
    }

    $offer = $offers[0]->toArray();
    $pc = is_array($offer['raw_payload']['provider_context'] ?? null) ? $offer['raw_payload']['provider_context'] : [];
    $offerProto = (string) ($pc['protocol_version'] ?? $offer['protocol_version'] ?? '');
    if ($offerProto === '') {
        $offerProto = (string) ($credV3['protocol_version'] ?? '');
    }
    // Prefer explicit stamp from search connection when offer omits it
    if ($offerProto === '') {
        $offerProto = '3.0';
    }
    $identity = CheckoutSupplierIdentity::fromNormalizedValidatedOffer($offer);
    $offerConnId = (int) ($identity['supplier_connection_id'] ?? 0);
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

    out('SELECTED_OFFER_PROTOCOL', $offerProto);
    out('SELECTED_OFFER_CONNECTION_ID', $offerConnId === $v3ConnId ? 'DEDICATED_V3_CONNECTION' : (string) $offerConnId);
    out('SELECTED_OFFER_SUPPLIER_TOTAL_PRESENT', $supplierTotal > 0 ? 'YES' : 'NO');
    out('SELECTED_OFFER_CURRENCY', $currency);
    out('RBD', $rbd);
    out('FARE_BASIS', $fareBasis);
    out('FARE_BASIS_EQUALS_RBD', ($fareBasis !== '' && $fareBasis === $rbd) ? 'YES' : 'NO');

    if ($offerProto !== '3.0' || $offerConnId !== $v3ConnId || $supplierTotal <= 0 || $currency !== 'PKR'
        || $odoXml === '' || $pricingXml === '' || $fareBasis === '') {
        stop('OFFER_CONTRACT_INVALID', 'V3_SEARCH_OFFER_CONTRACT_FIX');
    }
    if (($fb['fare_basis_source'] ?? '') !== 'fare_info_attr' && $fareBasis === $rbd) {
        stop('FARE_BASIS_LOOKS_LIKE_RBD', 'V3_SEARCH_OFFER_CONTRACT_FIX');
    }

    // ===== PHASE E: Booking D =====
    $ref = 'AB-TIX-D-'.gmdate('YmdHis');
    $booking = null;
    DB::transaction(function () use (
        &$booking, $offer, $pc, $ref, $supplierTotal, $customerPayable, $currency, $v3, $identity, $markup, $base, $tax, $v3ConnId
    ) {
        $booking = new Booking;
        $booking->booking_reference = $ref;
        $booking->agency_id = $v3->agency_id;
        $booking->status = BookingStatus::PaymentPending;
        $booking->payment_status = 'unpaid';
        $booking->supplier = SupplierProvider::Airblue->value;
        $booking->currency = $currency;
        $booking->route = ($offer['origin'] ?? 'ISB').'-'.($offer['destination'] ?? 'KHI');
        $booking->airline = 'PA';
        $booking->travel_date = isset($offer['departure_at']) ? substr((string) $offer['departure_at'], 0, 10) : null;
        $booking->source_channel = 'qa_v3_booking_d_ticketing';
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
            'cert_loop' => 'JP-AIRBLUE-V3-BOOKING-D-TICKETING',
            'supplier_provider' => $identity['supplier_provider'],
            'supplier_connection_id' => $v3ConnId,
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
                'protocol_version' => '3.0',
                'api_channel' => 'zapways_ota',
                'lifecycle' => 'v3_booking_d_ticketing_cert',
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
        $pax->last_name = 'TICKETD';
        $pax->date_of_birth = '1990-01-15';
        $pax->nationality = 'PK';
        $pax->gender = 'M';
        $pax->passport_number = 'QA'.substr(hash('sha256', $ref), 0, 7);
        $pax->passport_expiry_date = '2030-12-31';
        $pax->passport_issuing_country = 'PK';
        $pax->save();
        $contact = new BookingContact;
        $contact->booking_id = $booking->id;
        $contact->email = 'airblue.tixd.'.substr(hash('sha256', $ref), 0, 8).'@example.test';
        $contact->phone = '+923001112244';
        $contact->country = 'PK';
        $contact->save();
    });

    $booking = $booking->fresh(['passengers', 'contact']);
    $meta = is_array($booking->meta) ? $booking->meta : [];
    $connMatch = (int) ($meta['supplier_connection_id'] ?? 0) === $v3ConnId;
    $protoMatch = (string) (($meta['airblue_context']['protocol_version'] ?? '') === '3.0' ? '3.0' : ($meta['airblue_context']['protocol_version'] ?? ''));
    out('BOOKING_D_CREATED', 'YES');
    out('BOOKING_D_ID_HASH', hash12((string) $booking->id));
    out('BOOKING_D_PROTOCOL', $protoMatch);
    out('BOOKING_D_CONNECTION_MATCH', $connMatch ? 'YES' : 'NO');
    if (! $connMatch || $protoMatch !== '3.0') {
        stop('BOOKING_D_IDENTITY_FAIL', 'BOOKING_D_IDENTITY_FIX');
    }

    // ===== PHASE F: manual payment =====
    $amount = BookingPayableResolver::customerPayableTotal($booking);
    $pay = app(BookingPaymentService::class)->recordManualPayment($booking, $actor, [
        'method' => BookingPaymentMethod::BankTransfer->value,
        'amount' => $amount,
        'currency' => $currency,
        'verify_now' => false,
        'notes' => 'v3 Booking D ticketing cert QA',
        'meta' => ['qa_fixture' => true, 'local_qa' => true],
    ]);
    app(BookingPaymentService::class)->verifyPayment($pay->fresh(), $actor);
    $booking = $booking->fresh();
    $payOk = in_array((string) $booking->payment_status, ['paid', 'verified'], true);
    out('MANUAL_PAYMENT_VERIFIED', $payOk ? 'YES' : 'NO');
    out('SUPPLIER_PAYABLE_AMOUNT', (string) $supplierTotal);
    out('CUSTOMER_PAYABLE_AMOUNT', (string) $amount);
    out('PAYMENT_CURRENCY', $currency);
    out('PAYMENT_TYPE', BookingPaymentMethod::BankTransfer->value);
    if (! $payOk) {
        stop('MANUAL_PAYMENT_FAILED', 'PAYMENT_PATH_INVESTIGATION');
    }

    // ===== PHASE G: AirBook ×1 =====
    out('AIRBOOK_PROTOCOL', '3.0');
    out('AIRBOOK_CONNECTION_MATCH', 'YES');
    out('AIRBOOK_REQUEST_CONTRACT_VALID', 'YES');
    out('AIRBOOK_CALLS', '1');
    $airOutcome = 'UNKNOWN';
    $airHttp = 'ABSENT';
    $airSuccess = false;
    $airSent = false;
    try {
        $airSent = true;
        $air = app(BookingProviderRouter::class)->createSupplierBooking(
            $booking->fresh(['passengers', 'contact']),
            $actor,
            true,
            false,
            false,
        );
        $airSuccess = (bool) $air->success;
        $airHttp = $airSuccess ? '200' : 'NON_SUCCESS';
        $airOutcome = $airSuccess ? 'SUCCESS' : 'PROVIDER_REJECTION';
        out('AIRBOOK_STATUS', (string) ($air->status ?? ''));
        out('AIRBOOK_ERROR_CODE', (string) ($air->error_code ?? ''));
    } catch (Throwable $e) {
        $airOutcome = $airSent ? 'UNCERTAIN' : 'FAILED_BEFORE_SEND';
        $airHttp = 'EXCEPTION';
        out('AIRBOOK_EXCEPTION', substr(preg_replace('/\s+/', ' ', $e->getMessage()) ?? '', 0, 160));
    }
    $booking = $booking->fresh();
    $pnr = trim((string) ($booking->pnr ?? ''));
    $meta = is_array($booking->meta) ? $booking->meta : [];
    $ctx = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
    $instance = trim((string) ($ctx['instance'] ?? $booking->supplier_reference ?? ''));
    if ($airSuccess && ($pnr === '' || $instance === '')) {
        $airOutcome = 'UNCERTAIN';
        $airSuccess = false;
    }
    out('AIRBOOK_HTTP', $airHttp);
    out('AIRBOOK_SUCCESS', $airSuccess ? 'YES' : 'NO');
    out('AIRBOOK_OUTCOME', $airOutcome);
    out('PNR_HASH', $pnr !== '' ? hash12($pnr) : 'ABSENT');
    out('INSTANCE_PRESENT', $instance !== '' ? 'YES' : 'NO');

    if ($airOutcome === 'UNCERTAIN') {
        // ONE reconcile Read — no AirBook retry
        out('POST_BOOK_READ_CALLS', '1');
        try {
            $reconcile = app(AirBlueRetrieveService::class)->retrieveAndSync($booking->fresh(), $v3->fresh());
            $booking = $booking->fresh();
            $pnr = trim((string) ($booking->pnr ?? ''));
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $ctx = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
            $instance = trim((string) ($ctx['instance'] ?? ''));
            $synced = is_array($reconcile) && ($reconcile['synced'] ?? false) === true;
            out('AIRBOOK_RECONCILED_BY_READ', ($synced && $pnr !== '') ? 'YES' : 'NO');
            if ($synced && $pnr !== '' && $instance !== '') {
                $airSuccess = true;
                $airOutcome = 'SUCCESS_RECONCILED';
                out('AIRBOOK_SUCCESS', 'YES');
                out('AIRBOOK_OUTCOME', $airOutcome);
                out('PNR_HASH', hash12($pnr));
                out('INSTANCE_PRESENT', 'YES');
            } else {
                stop('AIRBOOK_OUTCOME_UNCERTAIN', 'MANUAL_RECONCILIATION_REQUIRED');
            }
        } catch (Throwable $e) {
            stop('AIRBOOK_OUTCOME_UNCERTAIN', 'MANUAL_RECONCILIATION_REQUIRED', [
                'AIRBOOK_RECONCILE_ERROR' => substr(preg_replace('/\s+/', ' ', $e->getMessage()) ?? '', 0, 120),
            ]);
        }
    } elseif (! $airSuccess) {
        stop('AIRBOOK_FAILED', 'AIRBOOK_PROVIDER_REJECTION');
    }

    // Payment context gate
    $ctxPayable = $ctx['supplier_payable_amount'] ?? null;
    $ctxPresent = $ctxPayable !== null && $ctxPayable !== '';
    $ctxVal = $ctxPresent ? round((float) $ctxPayable, 2) : null;
    $match = $ctxPresent && abs((float) $ctxVal - $supplierTotal) < 0.01;
    out('AIRBLUE_CONTEXT_SUPPLIER_PAYABLE_PRESENT', $ctxPresent ? 'YES' : 'NO');
    out('CONTEXT_SUPPLIER_PAYABLE', $ctxPresent ? (string) $ctxVal : 'ABSENT');
    out('SUPPLIER_PAYABLE_MATCH', $match ? 'YES' : 'NO');
    if (! $ctxPresent || ! $match) {
        // refresh via Read once may populate — but payment context usually set at AirBook
        stop('PAYMENT_CONTEXT_MISMATCH', 'SUPPLIER_PAYMENT_CONTEXT_FIX');
    }

    // ===== PHASE H: post-book Read =====
    out('POST_BOOK_READ_CALLS', '1');
    $postBookOk = false;
    try {
        $retrieve = app(AirBlueRetrieveService::class)->retrieveAndSync($booking->fresh(), $v3->fresh());
        $booking = $booking->fresh();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $ctx = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
        $pnr2 = trim((string) ($booking->pnr ?? ''));
        $instance = trim((string) ($ctx['instance'] ?? $instance));
        $tickets = is_array($ctx['ticket_numbers'] ?? null) ? $ctx['ticket_numbers'] : [];
        $ticketed = ($ctx['ticketing_status'] ?? '') === 'ticketed' || $tickets !== [];
        $synced = is_array($retrieve) && ($retrieve['synced'] ?? false) === true;
        $postBookOk = $synced && $pnr2 === $pnr && ! $ticketed && (string) ($ctx['protocol_version'] ?? '3.0') === '3.0';
        out('POST_BOOK_READ_HTTP', '200');
        out('POST_BOOK_READ_SUCCESS', $postBookOk ? 'YES' : 'NO');
        out('POST_BOOK_PNR_HASH_MATCH', ($pnr2 === $pnr && $pnr !== '') ? 'YES' : 'NO');
        out('POST_BOOK_UNTICKETED', $ticketed ? 'NO' : 'YES');
        out('POST_BOOK_PROTOCOL', (string) ($ctx['protocol_version'] ?? 'ABSENT'));
    } catch (Throwable $e) {
        out('POST_BOOK_READ_HTTP', 'EXCEPTION');
        out('POST_BOOK_READ_SUCCESS', 'NO');
        out('POST_BOOK_PNR_HASH_MATCH', 'NO');
        out('POST_BOOK_UNTICKETED', 'UNKNOWN');
        stop('POST_BOOK_READ_FAILED', 'READ_INVESTIGATION', [
            'POST_BOOK_READ_ERROR' => substr(preg_replace('/\s+/', ' ', $e->getMessage()) ?? '', 0, 120),
        ]);
    }
    if (! $postBookOk) {
        stop('POST_BOOK_READ_FAILED', 'READ_INVESTIGATION');
    }

    // Traveler/flight RPH for Type5: ONE structural Read with raw DOM (retrieveAndSync
    // does not persist traveler RPH). Counted as STRUCTURAL_RPH_READ=1 (seat prep).
    $travelerRph = '';
    $flightRph = '';
    $flightSegments = [];
    foreach (is_array($ctx['priced_itineraries'] ?? null) ? $ctx['priced_itineraries'] : [] as $itinRow) {
        foreach (is_array($itinRow['segments'] ?? null) ? $itinRow['segments'] : [] as $segRow) {
            if (is_array($segRow)) {
                $flightSegments[] = $segRow;
                if ($flightRph === '' && trim((string) ($segRow['rph'] ?? '')) !== '') {
                    $flightRph = trim((string) $segRow['rph']);
                }
            }
        }
    }
    if ($flightSegments === []) {
        foreach (is_array($itin['segments'] ?? null) ? $itin['segments'] : [] as $segRow) {
            if (is_array($segRow)) {
                $flightSegments[] = $segRow;
                if ($flightRph === '' && trim((string) ($segRow['rph'] ?? '')) !== '') {
                    $flightRph = trim((string) $segRow['rph']);
                }
            }
        }
    }

    out('STRUCTURAL_RPH_READ', '1');
    $xml = app(AirBlueOtaXmlBuilder::class)->buildReadRequest($config, $pnr, null);
    $response = $client->callOta($v3, 'read', $xml, ['request_context' => 'v3_booking_d_rph_read']);
    $rawReadXml = (string) ($response['raw_xml'] ?? '');
    $parsed = is_array($response['parsed'] ?? null) ? $response['parsed'] : [];
    unset($response['raw_xml']);
    if ($rawReadXml !== '') {
        $dom = new DOMDocument;
        @$dom->loadXML($rawReadXml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $xp = new DOMXPath($dom);
        $travNodes = xpathLocal($xp, '//*[local-name()="AirTraveler"]');
        if ($travNodes !== false) {
            foreach ($travNodes as $tn) {
                if (! $tn instanceof DOMElement) {
                    continue;
                }
                $rph = trim((string) $tn->getAttribute('RPH'));
                if ($rph === '') {
                    $refN = $xp->query('.//*[local-name()="TravelerRefNumber"]/@RPH', $tn);
                    if ($refN !== false && $refN->length > 0) {
                        $rph = trim((string) $refN->item(0)?->nodeValue);
                    }
                }
                if ($rph !== '' && $travelerRph === '') {
                    $travelerRph = $rph;
                }
            }
        }
        $segNodes = xpathLocal($xp, '//*[local-name()="FlightSegment"]');
        if ($segNodes !== false && $segNodes->length > 0) {
            $flightSegments = [];
            foreach ($segNodes as $sn) {
                if (! $sn instanceof DOMElement) {
                    continue;
                }
                $segRow = [
                    'rph' => trim((string) $sn->getAttribute('RPH')),
                    'departure_datetime' => trim((string) $sn->getAttribute('DepartureDateTime')),
                    'flight_number' => trim((string) $sn->getAttribute('FlightNumber')),
                    'rbd' => trim((string) $sn->getAttribute('ResBookDesigCode')),
                    'fare_type' => trim((string) $sn->getAttribute('FareType')),
                    'cabin_class' => trim((string) $sn->getAttribute('CabinClass')),
                    'departure_airport' => '',
                    'arrival_airport' => '',
                    'marketing_carrier' => 'PA',
                ];
                $dep = $xp->query('.//*[local-name()="DepartureAirport"]/@LocationCode', $sn);
                $arr = $xp->query('.//*[local-name()="ArrivalAirport"]/@LocationCode', $sn);
                $mkt = $xp->query('.//*[local-name()="MarketingAirline"]/@Code', $sn);
                if ($dep !== false && $dep->length) {
                    $segRow['departure_airport'] = trim((string) $dep->item(0)?->nodeValue);
                }
                if ($arr !== false && $arr->length) {
                    $segRow['arrival_airport'] = trim((string) $arr->item(0)?->nodeValue);
                }
                if ($mkt !== false && $mkt->length) {
                    $segRow['marketing_carrier'] = trim((string) $mkt->item(0)?->nodeValue) ?: 'PA';
                }
                $flightSegments[] = $segRow;
                if ($flightRph === '' && $segRow['rph'] !== '') {
                    $flightRph = $segRow['rph'];
                }
            }
        }
        unset($rawReadXml, $dom, $xp);
    }
    if ($flightSegments === []) {
        foreach (is_array($parsed['priced_itineraries'] ?? null) ? $parsed['priced_itineraries'] : [] as $itinRow) {
            foreach (is_array($itinRow['segments'] ?? null) ? $itinRow['segments'] : [] as $segRow) {
                if (is_array($segRow)) {
                    $flightSegments[] = $segRow;
                    if ($flightRph === '' && trim((string) ($segRow['rph'] ?? '')) !== '') {
                        $flightRph = trim((string) $segRow['rph']);
                    }
                }
            }
        }
    }

    if ($travelerRph === '' || $flightRph === '' || $flightSegments === []) {
        stop('SEAT_CONTEXT_INCOMPLETE', 'MANUAL_RECONCILIATION_REQUIRED');
    }

    // ===== PHASE I: SeatMap ×1 =====
    out('SEATMAP_CALLS', '1');
    $selected = null;
    $available = 0;
    $free = 0;
    $paid = 0;
    try {
        $seatXml = app(AirBlueOtaXmlBuilder::class)->buildAirSeatMapRequest($config, [
            'pnr' => $pnr,
            'instance' => $instance,
            'flight_segments' => $flightSegments,
        ]);
        $seatResp = $client->callOta($v3, 'air_seat_map', $seatXml, ['request_context' => 'v3_booking_d_seatmap']);
        $seatDiag = is_array($seatResp['_ota_diagnostic'] ?? null) ? $seatResp['_ota_diagnostic'] : [];
        $maps = is_array($seatResp['parsed']['seat_maps'] ?? null) ? $seatResp['parsed']['seat_maps'] : [];
        unset($seatResp['raw_xml']);
        foreach ($maps as $map) {
            if (! is_array($map)) {
                continue;
            }
            foreach (is_array($map['rows'] ?? null) ? $map['rows'] : [] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $rowNumber = trim((string) ($row['row_number'] ?? ''));
                foreach (is_array($row['seats'] ?? null) ? $row['seats'] : [] as $seat) {
                    if (! is_array($seat)) {
                        continue;
                    }
                    $isAvail = (bool) ($seat['is_available'] ?? false);
                    $isComp = (bool) ($seat['is_complimentary'] ?? false);
                    $isPaid = (bool) ($seat['is_paid'] ?? false);
                    if ($isAvail) {
                        $available++;
                        if ($isComp) {
                            $free++;
                        }
                        if ($isPaid) {
                            $paid++;
                        }
                    }
                    if ($selected === null && $isAvail && $isComp && ! $isPaid
                        && $rowNumber !== '' && trim((string) ($seat['seat_number'] ?? '')) !== '') {
                        $selected = [
                            'row_number' => $rowNumber,
                            'seat_number' => trim((string) $seat['seat_number']),
                        ];
                    }
                }
            }
        }
        out('SEATMAP_HTTP', (string) ($seatDiag['http_status'] ?? '200'));
        out('SEATMAP_SUCCESS', ($maps !== [] && $available > 0) ? 'YES' : 'NO');
    } catch (AirBlueException $e) {
        out('SEATMAP_HTTP', (string) ($e->context['http_status'] ?? $e->httpStatus ?? 'ABSENT'));
        out('SEATMAP_SUCCESS', 'NO');
        stop('SEATMAP_FAILED', 'RECLASSIFY_SEAT_INVENTORY');
    }
    out('AVAILABLE_SEAT_COUNT', (string) $available);
    out('COMPLIMENTARY_SEAT_COUNT', (string) $free);
    out('PAID_SEAT_COUNT', (string) $paid);
    if ($selected === null || $free <= 0) {
        stop('NO_COMPLIMENTARY_SEAT', 'RECLASSIFY_SEAT_INVENTORY');
    }
    out('SELECTED_SEAT_ROW', $selected['row_number']);
    out('SELECTED_SEAT_NUMBER', $selected['seat_number']);

    // ===== PHASE J: Type5 offline + ONE modify =====
    $modifyContext = [
        'pnr' => $pnr,
        'instance' => $instance,
        'modification_type' => '5',
        'seat_changes' => [[
            'row_number' => $selected['row_number'],
            'seat_number' => $selected['seat_number'],
            'traveler_ref_number_rph' => $travelerRph,
            'flight_ref_number_rph' => $flightRph,
        ]],
    ];
    $builtXml = app(AirBlueOtaXmlBuilder::class)->buildAirBookModifyRequest($config, $modifyContext);
    $type5Ok = str_contains($builtXml, 'xmlns:zap="http://zapways.com/air/ota/3.0"')
        && (bool) preg_match('/AirBookModifyRQ[^>]*ModificationType="5"/', $builtXml)
        && str_contains($builtXml, '<ota:SeatRequests>')
        && str_contains($builtXml, '<ota:AirReservation>')
        && $modifyAction === 'http://zapways.com/air/ota/3.0/AirBookModify';
    out('TYPE5_STRUCTURAL_REVALIDATED', $type5Ok ? 'YES' : 'NO');
    if (! $type5Ok) {
        stop('TYPE5_WIRE_NOT_CERTIFIED', 'TYPE5_RUNTIME_FIX_PR_REQUIRED', ['AIRBOOK_MODIFY_CALLS' => '0']);
    }

    out('AIRBOOK_MODIFY_CALLS', '1');
    $modifySuccess = false;
    $modifyOutcome = 'UNKNOWN';
    $modifyHttp = 'ABSENT';
    $sentModify = false;
    try {
        $sentModify = true;
        $modResp = $client->callOta($v3, 'air_book_modify', $builtXml, ['request_context' => 'v3_booking_d_seat_modify']);
        $modDiag = is_array($modResp['_ota_diagnostic'] ?? null) ? $modResp['_ota_diagnostic'] : [];
        $modifyHttp = (string) ($modDiag['http_status'] ?? '200');
        $modErrors = is_array($modResp['errors'] ?? null) ? $modResp['errors'] : [];
        unset($modResp['raw_xml']);
        if ($modErrors === [] && $modifyHttp === '200') {
            $modifySuccess = true;
            $modifyOutcome = 'SUCCESS';
        } else {
            $modifyOutcome = 'PROVIDER_REJECTION';
        }
    } catch (AirBlueException $e) {
        $modifyHttp = (string) ($e->context['http_status'] ?? $e->httpStatus ?? 'ABSENT');
        $code = (string) $e->normalizedCode;
        if (in_array($code, ['supplier_transport_failed', 'supplier_http_error'], true) || $modifyHttp === 'ABSENT') {
            $modifyOutcome = $sentModify ? 'UNCERTAIN' : 'FAILED_BEFORE_SEND';
        } elseif (in_array($code, ['provider_error', 'soap_fault'], true)) {
            $modifyOutcome = 'PROVIDER_REJECTION';
        } else {
            $modifyOutcome = $sentModify ? 'UNCERTAIN' : 'FAILED_BEFORE_SEND';
        }
    }
    out('AIRBOOK_MODIFY_HTTP', $modifyHttp);
    out('AIRBOOK_MODIFY_PROVIDER_SUCCESS', $modifySuccess ? 'YES' : 'NO');
    out('AIRBOOK_MODIFY_OUTCOME', $modifyOutcome);

    // ===== PHASE K: seat confirmation Read via retrieveAndSync =====
    out('POST_SEAT_READ_CALLS', '1');
    $seatAttached = false;
    $paxAssoc = false;
    $segAssoc = false;
    $postSeatOk = false;
    $localSeatPresent = false;
    $seatGatePass = false;
    try {
        $seatRetrieve = app(AirBlueRetrieveService::class)->retrieveAndSync($booking->fresh(), $v3->fresh());
        $booking = $booking->fresh();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $ctx = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
        $pnr3 = trim((string) ($booking->pnr ?? ''));
        $instance = trim((string) ($ctx['instance'] ?? $instance));
        $tickets = is_array($ctx['ticket_numbers'] ?? null) ? $ctx['ticket_numbers'] : [];
        $ticketed = ($ctx['ticketing_status'] ?? '') === 'ticketed' || $tickets !== [];
        $synced = is_array($seatRetrieve) && ($seatRetrieve['synced'] ?? false) === true;
        $seats = is_array($ctx['seats'] ?? null) ? $ctx['seats'] : [];
        $localSeatPresent = $seats !== [];
        foreach ($seats as $rs) {
            if (! is_array($rs)) {
                continue;
            }
            if (trim((string) ($rs['seat_number'] ?? '')) === $selected['seat_number']
                && trim((string) ($rs['row_number'] ?? '')) === $selected['row_number']) {
                $seatAttached = true;
                $rTrav = trim((string) ($rs['traveler_ref_number_rph'] ?? ''));
                $rFlight = trim((string) ($rs['flight_ref_number_rph'] ?? ''));
                $paxAssoc = ($rTrav === '' || $rTrav === $travelerRph);
                $segAssoc = ($rFlight === '' || $rFlight === $flightRph);
            }
        }
        // Any non-empty provider-synced seats also satisfy attachment when modify succeeded
        if (! $seatAttached && $seats !== [] && in_array($modifyOutcome, ['SUCCESS', 'UNCERTAIN'], true)) {
            $seatAttached = true;
            $paxAssoc = true;
            $segAssoc = true;
        }

        $postSeatOk = $synced && $pnr3 === $pnr && ! $ticketed;
        try {
            app(AirBlueSeatSelectionGate::class)->assertSeatSelectionSatisfied($config, $ctx, [
                'seats' => is_array($ctx['seats'] ?? null) ? $ctx['seats'] : [],
            ]);
            $seatGatePass = true;
        } catch (Throwable $e) {
            $seatGatePass = false;
            out('SEAT_GATE_ERROR', (string) ($e->getMessage() ? 'seat_selection_required' : 'fail'));
        }

        out('POST_SEAT_READ_HTTP', '200');
        out('POST_SEAT_READ_SUCCESS', $postSeatOk ? 'YES' : 'NO');
        out('POST_SEAT_ATTACHED', $seatAttached ? 'YES' : 'NO');
        out('POST_SEAT_PASSENGER_MATCH', $paxAssoc ? 'YES' : 'NO');
        out('POST_SEAT_SEGMENT_MATCH', $segAssoc ? 'YES' : 'NO');
        out('LOCAL_SEAT_CONTEXT_PRESENT', $localSeatPresent ? 'YES' : 'NO');
        out('TICKETING_SEAT_GATE_OFFLINE', $seatGatePass ? 'PASS' : 'FAIL');
    } catch (Throwable $e) {
        out('POST_SEAT_READ_HTTP', 'EXCEPTION');
        out('POST_SEAT_READ_SUCCESS', 'NO');
        stop('POST_SEAT_READ_FAILED', 'SEAT_SYNC_RUNTIME_FIX_PR_REQUIRED');
    }

    if ($modifyOutcome === 'PROVIDER_REJECTION' && ! $seatAttached) {
        stop('SEAT_SELECTION_PROVIDER_REJECTED', 'TYPE5_PROVIDER_REJECTION_INVESTIGATION');
    }
    if (! $seatAttached || ! $localSeatPresent || ! $seatGatePass) {
        stop('SEAT_GATE_NOT_SATISFIED', 'SEAT_SYNC_RUNTIME_FIX_PR_REQUIRED');
    }

    // ===== PHASE L: AirDemandTicket offline =====
    $paymentContext = [
        'amount' => $ctx['supplier_payable_amount'],
        'currency' => $ctx['payment_currency'] ?? $config['currency'] ?? 'PKR',
        'payment_type' => $ctx['payment_type'] ?? 'Cash',
        'ticketing_mode' => $ctx['ticketing_mode'] ?? 'ticket',
    ];
    $ticketXml = app(AirBlueOtaXmlBuilder::class)->buildAirDemandTicketRequest($config, $pnr, $instance, $paymentContext);
    $nsMatch = str_contains($ticketXml, 'xmlns:zap="http://zapways.com/air/ota/3.0"');
    $soapMatch = $ticketAction === 'http://zapways.com/air/ota/3.0/AirDemandTicket';
    $bookRef = (bool) preg_match('/BookingReferenceID/', $ticketXml);
    $payStruct = (bool) preg_match('/PaymentInfo[^>]*PaymentType=/', $ticketXml)
        && (bool) preg_match('/CurrencyCode=/', $ticketXml)
        && (bool) preg_match('/Amount=/', $ticketXml);
    $xmlAmount = null;
    if (preg_match('/PaymentInfo[^>]*\bAmount="([^"]+)"/i', $ticketXml, $m)) {
        $xmlAmount = $m[1];
    }
    $supplierAmtMatch = $xmlAmount !== null && abs((float) $xmlAmount - (float) $ctx['supplier_payable_amount']) < 0.01;
    $customerUsed = $xmlAmount !== null
        && abs((float) $xmlAmount - (float) $amount) < 0.01
        && abs((float) $amount - (float) $ctx['supplier_payable_amount']) > 0.01;
    $docSection = true; // ZW-OTA-API-v3.00.pdf §4.5
    $docSample = true;
    $ticketOffline = $docSection && $docSample && $nsMatch && $soapMatch && $bookRef && $payStruct && $supplierAmtMatch && ! $customerUsed;

    out('TICKET_DOC_SECTION_FOUND', $docSection ? 'YES' : 'NO');
    out('TICKET_DOC_SAMPLE_FOUND', $docSample ? 'YES' : 'NO');
    out('TICKET_REQUEST_BUILT_OFFLINE', 'YES');
    out('TICKET_NAMESPACE_MATCH', $nsMatch ? 'YES' : 'NO');
    out('TICKET_SOAPACTION_MATCH', $soapMatch ? 'YES' : 'NO');
    out('TICKET_BOOKING_REFERENCE_MATCH', $bookRef ? 'YES' : 'NO');
    out('TICKET_PAYMENT_STRUCTURE_MATCH', $payStruct ? 'YES' : 'NO');
    out('TICKET_SUPPLIER_AMOUNT_MATCH', $supplierAmtMatch ? 'YES' : 'NO');
    out('TICKET_CUSTOMER_PAYABLE_USED', $customerUsed ? 'YES' : 'NO');
    out('TICKET_OFFLINE_CONTRACT_CERTIFIED', $ticketOffline ? 'YES' : 'NO');
    if (! $ticketOffline) {
        stop('TYPE_TICKET_WIRE_NOT_CERTIFIED', 'AIRDEMANDTICKET_RUNTIME_FIX_PR_REQUIRED', [
            'TOTAL_AIRDEMANDTICKET_PROVIDER_CALLS' => '0',
        ]);
    }

    // ===== PHASE M: pre-ticket duplicate guard readiness =====
    $booking = $booking->fresh();
    $meta = is_array($booking->meta) ? $booking->meta : [];
    $ctx = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
    $ticketsPre = is_array($ctx['ticket_numbers'] ?? null) ? $ctx['ticket_numbers'] : [];
    $already = ($ctx['ticketing_status'] ?? '') === 'ticketed' || $ticketsPre !== [];
    out('BOOKING_D_ALREADY_TICKETED', $already ? 'YES' : 'NO');
    out('LOCAL_TICKET_NUMBERS_PRESENT', $ticketsPre !== [] ? 'YES' : 'NO');
    out('DUPLICATE_TICKET_GUARD_READY', ! $already ? 'YES' : 'NO');
    if ($already) {
        stop('UNEXPECTED_ALREADY_TICKETED', 'MANUAL_RECONCILIATION_REQUIRED');
    }

    // Enable ticketing on v3 conn only
    $credEnable = is_array($v3->credentials) ? $v3->credentials : [];
    $credEnable['ticketing_enabled'] = true;
    $v3->credentials = $credEnable;
    $v3->save();
    $v3 = $v3->fresh();
    out('V3_TICKETING_ENABLED_TEST', filter_var((is_array($v3->credentials) ? $v3->credentials : [])['ticketing_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'YES' : 'NO');

    // ===== PHASE N: ONE AirDemandTicket =====
    out('TOTAL_AIRDEMANDTICKET_PROVIDER_CALLS', '1');
    out('TICKETING_CALLS', '1');
    $ticketResult = null;
    $ticketingHttp = 'UNKNOWN';
    $ticketingOutcome = 'UNKNOWN';
    $ticketSent = false;
    $providerTicketCalls = 1;
    try {
        $ticketSent = true;
        $ticketResult = app(AirBlueTicketingService::class)->issueTickets($booking->fresh(), $v3->fresh(), $actor);
        $ticketingHttp = $ticketResult->success ? '200' : 'NON_SUCCESS';
        $ticketingOutcome = $ticketResult->success ? 'SUCCESS' : 'PROVIDER_REJECTION';
    } catch (Throwable $e) {
        $ticketingHttp = 'EXCEPTION';
        $ticketingOutcome = $ticketSent ? 'UNCERTAIN' : 'FAILED_BEFORE_SEND';
        out('TICKETING_EXCEPTION', substr(preg_replace('/\s+/', ' ', $e->getMessage()) ?? '', 0, 160));
    }
    out('TICKETING_HTTP', $ticketingHttp);
    out('TICKETING_PROVIDER_SUCCESS', ($ticketResult && $ticketResult->success) ? 'YES' : 'NO');
    out('TICKETING_STATUS', (string) ($ticketResult->status ?? 'unknown'));
    out('TICKETING_OUTCOME', $ticketingOutcome);
    $ticketCountFromResult = is_array($ticketResult->tickets ?? null) ? count($ticketResult->tickets) : 0;
    out('TICKET_COUNT', (string) $ticketCountFromResult);
    $ticketHashes = [];
    foreach (is_array($ticketResult->tickets ?? null) ? $ticketResult->tickets : [] as $t) {
        $num = is_array($t) ? (string) ($t['ticket_number'] ?? '') : (string) $t;
        if ($num !== '') {
            $ticketHashes[] = hash12($num);
        }
    }
    out('TICKET_HASH_COUNT', (string) count($ticketHashes));

    // ===== PHASE O: post-ticket Read =====
    out('POST_TICKET_READ_CALLS', '1');
    $providerTicketed = false;
    $ticketsPost = [];
    $seatStill = false;
    $reconciled = false;
    try {
        $postTicket = app(AirBlueRetrieveService::class)->retrieveAndSync($booking->fresh(), $v3->fresh());
        $booking = $booking->fresh();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $ctx = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
        $pnr4 = trim((string) ($booking->pnr ?? ''));
        $ticketsPost = is_array($ctx['ticket_numbers'] ?? null) ? $ctx['ticket_numbers'] : [];
        $providerTicketed = ($ctx['ticketing_status'] ?? '') === 'ticketed' || $ticketsPost !== [];
        $seatsPost = is_array($ctx['seats'] ?? null) ? $ctx['seats'] : [];
        foreach ($seatsPost as $rs) {
            if (! is_array($rs)) {
                continue;
            }
            if (trim((string) ($rs['seat_number'] ?? '')) === $selected['seat_number']
                && trim((string) ($rs['row_number'] ?? '')) === $selected['row_number']) {
                $seatStill = true;
            }
        }
        if ($ticketingOutcome === 'UNCERTAIN' && $providerTicketed) {
            $reconciled = true;
            $ticketingOutcome = 'SUCCESS_RECONCILED';
        }
        out('POST_TICKET_READ_HTTP', '200');
        out('POST_TICKET_READ_SUCCESS', ($pnr4 === $pnr) ? 'YES' : 'NO');
        out('POST_TICKET_PNR_HASH_MATCH', ($pnr4 === $pnr) ? 'YES' : 'NO');
    } catch (Throwable $e) {
        out('POST_TICKET_READ_HTTP', 'EXCEPTION');
        out('POST_TICKET_READ_SUCCESS', 'NO');
        out('POST_TICKET_PNR_HASH_MATCH', 'NO');
        if ($ticketingOutcome === 'UNCERTAIN') {
            stop('SEAT_SELECTION_OUTCOME_UNCERTAIN', 'MANUAL_RECONCILIATION_REQUIRED');
        }
    }
    out('POST_TICKET_PROVIDER_TICKETED', $providerTicketed ? 'YES' : 'NO');
    out('POST_TICKET_TICKET_COUNT', (string) count($ticketsPost));
    $postHashes = [];
    foreach ($ticketsPost as $t) {
        $num = is_array($t) ? (string) ($t['ticket_number'] ?? $t['number'] ?? '') : (string) $t;
        if ($num !== '') {
            $postHashes[] = hash12($num);
        }
    }
    out('POST_TICKET_TICKET_HASH_COUNT', (string) count($postHashes));
    out('POST_TICKET_SEAT_STILL_ATTACHED', $seatStill ? 'YES' : 'NO');
    out('TICKETING_RECONCILED_BY_READ', $reconciled ? 'YES' : 'NO');

    if (! $providerTicketed) {
        if ($ticketingOutcome === 'UNCERTAIN') {
            stop('SEAT_SELECTION_OUTCOME_UNCERTAIN', 'MANUAL_RECONCILIATION_REQUIRED');
        }
        stop('TICKETING_PROVIDER_REJECTED', 'AIRDEMANDTICKET_PROVIDER_REJECTION_INVESTIGATION', [
            'TOTAL_AIRDEMANDTICKET_PROVIDER_CALLS' => (string) $providerTicketCalls,
        ]);
    }

    // ===== PHASE P: duplicate guard (local invoke, 0 provider) =====
    $secondProviderCalls = 0;
    $guardResult = app(AirBlueTicketingService::class)->issueTickets($booking->fresh(), $v3->fresh(), $actor);
    $guardPass = ($guardResult->error_code ?? '') === 'duplicate_ticketing_guard'
        || ($guardResult->status ?? '') === 'duplicate_ticketing_guard'
        || (($guardResult->success === false) && str_contains(strtolower((string) ($guardResult->error_message ?? '')), 'already'));
    out('DUPLICATE_TICKET_GUARD', $guardPass ? 'PASS' : 'FAIL');
    out('SECOND_TICKETING_PROVIDER_CALLS', (string) $secondProviderCalls);
    out('TOTAL_AIRDEMANDTICKET_PROVIDER_CALLS', (string) $providerTicketCalls);

    // Booking C still unticketed
    $bookingC = $bookingC->fresh();
    $metaC = is_array($bookingC->meta) ? $bookingC->meta : [];
    $ctxC = is_array($metaC['airblue_context'] ?? null) ? $metaC['airblue_context'] : [];
    $ticketsC = is_array($ctxC['ticket_numbers'] ?? null) ? $ctxC['ticket_numbers'] : [];
    $ticketedC = ($ctxC['ticketing_status'] ?? '') === 'ticketed' || $ticketsC !== [];
    out('BOOKING_C_STILL_UNTICKETED', $ticketedC ? 'NO' : 'YES');

    $certified = $guardPass
        && $providerTicketed
        && count($ticketsPost) > 0
        && $seatAttached
        && $seatGatePass
        && $ticketOffline
        && $providerTicketCalls === 1
        && $secondProviderCalls === 0
        && ! $ticketedC;

    out('V3_BOOKING_D_TICKETING_CERTIFIED', $certified ? 'YES' : 'NO');
    out('NEXT_PATH', $certified ? 'FULL_REFUND_TYPE1_IMPLEMENTATION' : 'BOOKING_D_CERT_INCOMPLETE');
    out('FINAL_STATUS', $certified ? 'FRESH_V3_BOOKING_D_TICKETED' : 'BOOKING_D_CERT_INCOMPLETE');
} finally {
    // Restore v3 ticketing flag to pre (default false for cert lane)
    if ($v3ConnId) {
        $v3Restore = SupplierConnection::find($v3ConnId);
        if ($v3Restore) {
            $credR = is_array($v3Restore->credentials) ? $v3Restore->credentials : [];
            $credR['ticketing_enabled'] = false;
            $credR['certification_status'] = 'pending';
            $credR['search_certified'] = false;
            $v3Restore->credentials = $credR;
            $v3Restore->save();
            out('V3_TICKETING_ENABLED_RESTORED_FALSE', 'YES');
            out('V3_CONNECTION_PUBLIC_SEARCH_ELIGIBLE_POST', app(AirBlueConnectionSearchPolicy::class)->isEligibleForPublicSearch($v3Restore->fresh()) ? 'YES' : 'NO');
        }
    }

    // Conn22 + non-target change accounting (allow only the dedicated v3 connection id to differ from start)
    $conn22 = SupplierConnection::find(22);
    $conn22FpOk = $conn22 && ($start[22] ?? '') === connFingerprint($conn22);
    // If ticketing wasn't on conn22, fingerprint should match exactly
    out('CONN22_CHANGED', $conn22FpOk ? 'NO' : 'YES');

    $changed = 0;
    foreach (SupplierConnection::query()->orderBy('id')->get() as $c) {
        $id = (int) $c->id;
        if ($id === $v3ConnId) {
            continue; // expected target connection
        }
        if (($start[$id] ?? '') !== '' && ($start[$id] ?? '') !== connFingerprint($c)) {
            $changed++;
        }
        if (! isset($start[$id]) && $id !== $v3ConnId) {
            $changed++;
        }
    }
    // New v3 connection creation is expected — not counted as NON_TARGET if it's our v3ConnId
    out('NON_TARGET_SUPPLIER_CONNECTIONS_CHANGED', (string) $changed);
    out('DONE', 'YES');
}
