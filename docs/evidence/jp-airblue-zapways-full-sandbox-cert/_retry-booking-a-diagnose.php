<?php
/**
 * Fresh search + one AirBook with detailed sanitized HTTP classification.
 * Does not print SOAP body or credentials.
 */
declare(strict_types=1);

$appRoot = '/home/pkjetp/jetpk_app';
chdir($appRoot);
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Data\FlightSearchRequestData;
use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\AirBlue\AirBlueBookingService;
use App\Services\Suppliers\AirBlue\AirBlueFlightSearchService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

function out(string $k, string|int|bool|null $v): void
{
    if (is_bool($v)) {
        $v = $v ? 'YES' : 'NO';
    }
    echo $k.'='.(string) $v.PHP_EOL;
}

out('EXT_EFFECTIVE', filter_var(env('AIRBLUE_EXTERNAL_CALLS_ENABLED', false), FILTER_VALIDATE_BOOLEAN) ? 'YES' : 'NO');
out('CLI_EFFECTIVE', filter_var(env('AIRBLUE_CLI_LIVE_ENABLED', false), FILTER_VALIDATE_BOOLEAN) ? 'YES' : 'NO');

$conn = SupplierConnection::find(22);
$host = parse_url((string) $conn->base_url, PHP_URL_HOST) ?: '';
out('HOST', $host);
if (($conn->environment?->value ?? '') !== 'sandbox' || $host !== 'otatest4.zapways.com') {
    out('FATAL', 'STOP_ENV_DRIFT');
    exit(2);
}

// Fresh search (counts as another search call)
$date = now()->addDays(50)->format('Y-m-d');
$criteria = [
    'origin' => 'ISB',
    'destination' => 'KHI',
    'depart_date' => $date,
    'adults' => 1,
    'children' => 0,
    'infants' => 0,
    'currency' => 'PKR',
    'trip_type' => 'one_way',
];
$search = app(AirBlueFlightSearchService::class)->search(FlightSearchRequestData::fromArray($criteria), $conn);
out('FRESH_SEARCH_DATE', $date);
out('FRESH_OFFERS', (string) count($search->offers));
if ($search->offers === []) {
    out('FATAL', 'no_offers');
    exit(3);
}
$offerArr = $search->offers[0]->toArray();
$providerContext = $offerArr['raw_payload']['provider_context'] ?? [];
if (! is_array($providerContext) || empty($providerContext['priced_itineraries'])) {
    out('FATAL', 'no_provider_context');
    exit(3);
}
out('FRESH_OFFER_ID_HASH', substr(hash('sha256', (string) ($offerArr['offer_id'] ?? '')), 0, 12));
$total = (float) ($offerArr['fare_breakdown']['supplier_total'] ?? 0);

$ref = 'AB-SANDBOX-A2-'.gmdate('YmdHis');
$booking = null;
DB::transaction(function () use (&$booking, $offerArr, $providerContext, $ref, $total, $conn, $date) {
    $booking = new Booking;
    $booking->booking_reference = $ref;
    $booking->agency_id = $conn->agency_id;
    $booking->status = BookingStatus::Paid;
    $booking->payment_status = 'paid';
    $booking->supplier = SupplierProvider::Airblue->value;
    $booking->currency = 'PKR';
    $booking->route = 'ISB-KHI';
    $booking->airline = 'PA';
    $booking->travel_date = $date;
    $booking->source_channel = 'sandbox_cert';
    $booking->selected_fare_total = $total;
    $booking->payable_after_promo = $total;
    $booking->payable_before_promo = $total;
    $booking->amount_paid = $total;
    $booking->balance_due = 0;
    $booking->submitted_at = now();
    $booking->confirmed_at = now();
    $booking->meta = [
        'qa_fixture' => true,
        'local_qa' => true,
        'airblue_sandbox_certification' => true,
        'cert_loop' => 'JP-AIRBLUE-ZAPWAYS-FULL-SANDBOX-CERT',
        'cert_booking_role' => 'A_UNTICKETED_CANCEL',
        'supplier_provider' => 'airblue',
        'supplier_connection_id' => 22,
        'airblue_context' => array_merge($providerContext, [
            'protocol_version' => '2.0',
            'api_channel' => 'zapways_ota',
        ]),
    ];
    $booking->save();

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
    $contact->email = 'airblue.sandbox.qa+a2.'.substr(hash('sha256', $ref), 0, 8).'@example.test';
    $contact->phone = '+923001112233';
    $contact->country = 'PK';
    $contact->save();
});

out('BOOKING_A2_ID', (string) $booking->id);
out('BOOKING_A2_ID_HASH', substr(hash('sha256', (string) $booking->id), 0, 12));

// Build XML and capture HTTP status via one controlled call path
$actor = User::query()->orderBy('id')->first();
$bookingService = app(AirBlueBookingService::class);

// Wrap Log to capture next http_status from air-blue channel if logged
// Also build request and do a one-shot HTTP probe classification via client internals is hard;
// instead catch by replaying through service and reading exception context via temporary listener.
$httpStatusSeen = null;
Log::listen(function ($message) use (&$httpStatusSeen): void {
    $ctx = method_exists($message, 'context') ? $message->context() : [];
    if (isset($ctx['http_status'])) {
        $httpStatusSeen = (int) $ctx['http_status'];
    }
});

$result = $bookingService->createSupplierBooking($booking->fresh(['passengers', 'contact']), $conn, $actor);
out('BOOKING_A2_SUCCESS', $result->success ? 'YES' : 'NO');
out('BOOKING_A2_STATUS', (string) $result->status);
out('BOOKING_A2_ERROR_CODE', (string) ($result->error_code ?? ''));
out('BOOKING_A2_ERROR_SAFE', substr((string) ($result->error_message ?? ''), 0, 160));
out('BOOKING_A2_HTTP_STATUS_FROM_LOG', $httpStatusSeen !== null ? (string) $httpStatusSeen : 'unknown');
out('BOOKING_A2_PNR_PRESENT', trim((string) ($result->pnr ?? '')) !== '' ? 'YES' : 'NO');
$safe = is_array($result->safe_summary) ? $result->safe_summary : [];
$ctx = is_array($safe['airblue_context'] ?? null) ? $safe['airblue_context'] : [];
out('BOOKING_A2_INSTANCE_PRESENT', trim((string) ($ctx['instance'] ?? '')) !== '' ? 'YES' : 'NO');

// Persist if success
if ($result->success) {
    $meta = is_array($booking->meta) ? $booking->meta : [];
    $air = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
    $pnr = trim((string) ($result->pnr ?? ''));
    $instance = trim((string) ($ctx['instance'] ?? ''));
    $meta['airblue_context'] = array_merge($air, $ctx, [
        'pnr' => $pnr,
        'instance' => $instance,
        'order_id' => trim((string) ($ctx['order_id'] ?? $pnr)),
        'booking_outcome' => 'success',
    ]);
    $booking->pnr = $pnr;
    $booking->supplier_reference = $pnr;
    $booking->meta = $meta;
    $booking->save();
}

// Tail air-blue log for http_status near now
$logDir = $appRoot.'/storage/logs';
$files = glob($logDir.'/air-blue*.log') ?: [];
$files[] = $logDir.'/laravel.log';
foreach ($files as $f) {
    if (! is_file($f)) {
        continue;
    }
    $lines = @file($f, FILE_IGNORE_NEW_LINES) ?: [];
    $slice = array_slice($lines, -80);
    foreach ($slice as $line) {
        if (stripos($line, 'http_status') !== false || stripos($line, 'supplier_http_error') !== false || stripos($line, 'air_book') !== false) {
            $red = preg_replace('/(password|client_key|agent_password|Authorization|Bearer|ClientKey)[=:\"][^,\"\s]+/i', '$1=REDACTED', $line);
            // keep short
            echo 'LOG_HIT='.substr((string) $red, 0, 300).PHP_EOL;
        }
    }
}

out('DONE', 'YES');
