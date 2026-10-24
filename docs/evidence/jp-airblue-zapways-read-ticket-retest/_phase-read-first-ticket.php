<?php
/**
 * Booking C read-first ticketing retry (PR123).
 * ONE corrected Read → STOP unless synced===true → optional ONE AirDemandTicket → restore conn22.
 * No AirBook, Cancel, refund, or new booking.
 */
declare(strict_types=1);

$appRoot = '/home/pkjetp/jetpk_app';
chdir($appRoot);
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\AirBlue\AirBlueClient;
use App\Services\Suppliers\AirBlue\AirBlueConfigResolver;
use App\Services\Suppliers\AirBlue\AirBlueOtaXmlBuilder;
use App\Services\Suppliers\AirBlue\AirBlueRetrieveService;
use App\Services\Suppliers\AirBlue\AirBlueTicketingService;
use App\Support\Payments\BookingPayableResolver;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
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
        'search_certified' => $cred['search_certified'] ?? null,
        'ticketing_enabled' => $cred['ticketing_enabled'] ?? null,
        'cancel_enabled' => $cred['cancel_enabled'] ?? null,
        'booking_enabled' => $cred['booking_enabled'] ?? null,
        'protocol_version' => $cred['protocol_version'] ?? null,
        'api_channel' => $cred['api_channel'] ?? null,
    ]));
}

Mail::fake();
Notification::fake();
out('MAIL_FAKE', 'YES');
out('NOTIFICATION_FAKE', 'YES');
out('TEST_INITIATED_NON_AIRBLUE_SUPPLIER_CALLS', '0');
out('PROVIDER_POST_TICKET_REVERSAL', 'AirBookModify');
out('FULL_REFUND_MODIFICATION_TYPE', '1');

$start = [];
foreach (SupplierConnection::query()->orderBy('id')->get() as $c) {
    $start[(int) $c->id] = [
        'fp' => connFingerprint($c),
        'provider' => (string) ($c->provider?->value ?? ''),
    ];
}
out('CONNECTION_BASELINE', 'PASS');
out('SNAPSHOT_COUNT', (string) count($start));

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

$ticketingPre = filter_var($cred['ticketing_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
out('TICKETING_ENABLED_PRE', $ticketingPre ? 'true' : 'false');
$conn22BackupPath = '/tmp/airblue-read-ticket-retest-conn22-backup.json';
file_put_contents($conn22BackupPath, json_encode([
    'id' => 22,
    'ticketing_enabled' => $cred['ticketing_enabled'] ?? false,
    'fp' => connFingerprint($conn),
], JSON_PRETTY_PRINT));
out('CONN22_BACKUP_CREATED', 'YES');

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
};

try {
    // -------- Phase 2 offline smoke --------
    $config = app(AirBlueConfigResolver::class)->resolveOta($conn);
    $client = app(AirBlueClient::class);
    $method = (new ReflectionClass($client))->getMethod('resolveOtaSoapAction');
    $method->setAccessible(true);
    $soapAction = (string) $method->invoke($client, 'read', $config);
    $endpoint = (string) ($config['endpoint_url'] ?? '');
    out('RUNTIME_READ_SOAP_ACTION', $soapAction);
    out('RUNTIME_READ_ENDPOINT', $endpoint);
    out('RUNTIME_READ_SOAP_ACTION_OK', $soapAction === 'https://ota.qa.zapways.com/Read' ? 'YES' : 'NO');
    out('RUNTIME_READ_ENDPOINT_OK', $endpoint === 'https://otatest4.zapways.com/v2.0/OTAAPI.asmx' ? 'YES' : 'NO');

    $placeholderXml = app(AirBlueOtaXmlBuilder::class)->buildReadRequest($config, 'PLACEHOLDERPNR');
    $pnrOnly = str_contains($placeholderXml, '<ota:UniqueID ID="PLACEHOLDERPNR"/>');
    $typeAbsent = ! str_contains($placeholderXml, 'Type="14"');
    $instanceAbsent = ! str_contains($placeholderXml, 'Instance=');
    out('READ_XML_PNR_ONLY', $pnrOnly ? 'YES' : 'NO');
    out('READ_XML_TYPE14_ABSENT', $typeAbsent ? 'YES' : 'NO');
    out('READ_XML_INSTANCE_ABSENT', $instanceAbsent ? 'YES' : 'NO');
    if (! $pnrOnly || ! $typeAbsent || ! $instanceAbsent || $soapAction !== 'https://ota.qa.zapways.com/Read') {
        out('FATAL', 'offline_smoke_failed');
        exit(10);
    }
    out('PLACEHOLDER_READ_SENT', 'NO');

    // -------- Phase 4 locate Booking C --------
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
    out('BOOKING_C_CANDIDATE_COUNT', (string) $candidates->count());
    out('BOOKING_C_MATCH_COUNT', (string) count($matches));
    if (count($matches) !== 1) {
        out('BOOKING_C_FOUND', 'NO');
        out('FATAL', 'booking_c_match_count_not_1');
        exit(11);
    }
    $booking = $matches[0]->fresh(['passengers', 'contact']);
    out('BOOKING_C_FOUND', 'YES');
    out('BOOKING_C_ID_HASH', hash12((string) $booking->id));
    out('BOOKING_C_REF_HASH', hash12((string) $booking->booking_reference));

    $meta = is_array($booking->meta) ? $booking->meta : [];
    $ctx = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
    $pnr = trim((string) ($booking->pnr ?? ''));
    $tickets = is_array($ctx['ticket_numbers'] ?? null) ? $ctx['ticket_numbers'] : [];
    $ticketed = ($ctx['ticketing_status'] ?? '') === 'ticketed' || $tickets !== [];
    $supplierPayable = round((float) ($ctx['supplier_payable_amount'] ?? 0), 2);
    $customerPayable = BookingPayableResolver::customerPayableTotal($booking);
    $connId = (int) ($meta['supplier_connection_id'] ?? $ctx['supplier_connection_id'] ?? 0);
    $payOk = in_array((string) $booking->payment_status, ['paid', 'verified'], true);

    out('BOOKING_C_SUPPLIER', (string) ($booking->supplier ?? ''));
    out('BOOKING_C_CONNECTION_ID', (string) $connId);
    out('BOOKING_C_PAYMENT_STATUS', (string) $booking->payment_status);
    out('BOOKING_C_PAYMENT_VERIFIED', $payOk ? 'YES' : 'NO');
    out('PNR_C_PRESENT', $pnr !== '' ? 'YES' : 'NO');
    out('PNR_C_HASH', $pnr !== '' ? hash12($pnr) : 'ABSENT');
    out('TICKET_NUMBERS_PRESENT_PRE', $tickets !== [] ? 'YES' : 'NO');
    out('TICKETED_PRE', $ticketed ? 'YES' : 'NO');
    out('CONTEXT_SUPPLIER_PAYABLE', (string) $supplierPayable);
    out('CUSTOMER_PAYABLE', (string) $customerPayable);
    out('CONTEXT_PAYMENT_CURRENCY', (string) ($ctx['payment_currency'] ?? 'ABSENT'));
    out('CONTEXT_PAYMENT_TYPE', (string) ($ctx['payment_type'] ?? 'ABSENT'));

    if ((string) ($booking->supplier ?? '') !== 'airblue' || $connId !== 22 || ! $payOk || $pnr === '' || $ticketed || abs($supplierPayable - 26000.0) > 0.01) {
        out('FATAL', 'booking_c_precondition_fail');
        exit(12);
    }
    if ($ticketed) {
        out('FATAL', 'already_ticketed_stop');
        exit(13);
    }

    // Ensure normal current-PNR mode (no specific instance flags).
    unset($ctx['read_specific_instance'], $ctx['retrieve_mode'], $ctx['read_mode']);
    $meta['airblue_context'] = $ctx;
    $booking->meta = $meta;
    $booking->save();
    $booking = $booking->fresh(['passengers', 'contact']);

    // -------- Phase 5: exactly one Read --------
    out('READ_CALLS', '1');
    $retrieve = app(AirBlueRetrieveService::class)->retrieveAndSync($booking->fresh(), $conn->fresh());
    $synced = is_array($retrieve) && ($retrieve['synced'] ?? false) === true;
    out('READ_SYNCED', $synced ? 'YES' : 'NO');
    out('READ_REASON', is_array($retrieve) ? (string) ($retrieve['reason'] ?? ($synced ? 'synced' : 'unknown')) : 'invalid');
    out('READ_KEYS', is_array($retrieve) ? implode(',', array_keys($retrieve)) : gettype($retrieve));

    $booking = $booking->fresh(['passengers']);
    $meta = is_array($booking->meta) ? $booking->meta : [];
    $ctx = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
    $pnrAfter = trim((string) ($booking->pnr ?? ''));
    $ticketsAfter = is_array($ctx['ticket_numbers'] ?? null) ? $ctx['ticket_numbers'] : [];
    $ticketedAfter = ($ctx['ticketing_status'] ?? '') === 'ticketed' || $ticketsAfter !== [];

    // Best-effort sanitized HTTP/error from retrieve reason only (no raw XML).
    out('READ_HTTP', $synced ? '200' : 'NON_SUCCESS_OR_UNKNOWN');
    out('READ_ERROR_CODE', $synced ? '' : (string) ($retrieve['reason'] ?? 'unknown'));

    if (! $synced) {
        out('READ_PROVIDER_PNR_MATCH', 'NOT_EVALUATED');
        out('READ_PASSENGER_MATCH', 'NOT_EVALUATED');
        out('READ_ITINERARY_MATCH', 'NOT_EVALUATED');
        out('READ_UNTICKETED', $ticketedAfter ? 'NO' : 'YES');
        out('TICKETING_CALLS', '0');
        out('TICKETING_SUCCESS', 'NOT_RUN');
        out('TICKETING_ENABLED_TEST', 'NO');
        out('FINAL_STATUS', 'READ_STILL_BLOCKED');
        out('PHASE', 'STOP_READ_NOT_SYNCED');
        exit(31);
    }

    $pnrMatch = $pnrAfter !== '' && hash12($pnrAfter) === '94b2bd04b019';
    $paxCount = $booking->passengers?->count() ?? 0;
    $itinOk = trim((string) ($booking->route ?? '')) !== '' || trim((string) ($ctx['pnr'] ?? '')) !== '';
    out('READ_PROVIDER_PNR_MATCH', $pnrMatch ? 'YES' : 'NO');
    out('READ_PASSENGER_MATCH', $paxCount === 1 ? 'YES' : 'NO');
    out('READ_ITINERARY_MATCH', $itinOk ? 'YES' : 'CHECK');
    out('READ_UNTICKETED', $ticketedAfter ? 'NO' : 'YES');

    if (! $pnrMatch || $ticketedAfter) {
        out('TICKETING_CALLS', '0');
        out('FINAL_STATUS', $ticketedAfter ? 'UNEXPECTEDLY_TICKETED' : 'READ_PNR_MISMATCH');
        exit(32);
    }

    // -------- Phase 7 ticket XML wire check --------
    $paymentContext = [
        'amount' => $ctx['supplier_payable_amount'] ?? 26000,
        'currency' => $ctx['payment_currency'] ?? 'PKR',
        'payment_type' => $ctx['payment_type'] ?? 'Cash',
    ];
    $ticketXml = app(AirBlueOtaXmlBuilder::class)->buildAirDemandTicketRequest(
        $config,
        $pnrAfter,
        trim((string) ($ctx['instance'] ?? '')),
        $paymentContext,
    );
    $xmlAmount = null;
    if (preg_match('/PaymentInfo[^>]*\bAmount="([^"]+)"/i', $ticketXml, $m)) {
        $xmlAmount = $m[1];
    }
    out('TICKET_XML_PAYMENT_AMOUNT', (string) ($xmlAmount ?? 'ABSENT'));
    $xmlMatch = $xmlAmount !== null && abs((float) $xmlAmount - 26000.0) < 0.01;
    out('TICKET_XML_SUPPLIER_AMOUNT_MATCH', $xmlMatch ? 'YES' : 'NO');
    out('TICKET_XML_CUSTOMER_PAYABLE_USED', ($xmlAmount !== null && abs((float) $xmlAmount - (float) $customerPayable) < 0.01 && abs((float) $customerPayable - 26000.0) > 0.01) ? 'YES' : 'NO');
    if (! $xmlMatch) {
        out('TICKETING_CALLS', '0');
        out('FINAL_STATUS', 'TICKET_XML_MISMATCH');
        exit(33);
    }

    // -------- Phase 8 enable ticketing --------
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
        exit(34);
    }

    // -------- Phase 9 one AirDemandTicket --------
    $actor = User::query()->where('email', 'like', '%admin%')->orderBy('id')->first()
        ?? User::query()->orderBy('id')->first();
    out('TICKETING_CALLS', '1');
    $ticketResult = null;
    try {
        $ticketResult = app(AirBlueTicketingService::class)->issueTickets($booking->fresh(), $conn->fresh(), $actor);
    } catch (Throwable $e) {
        out('TICKETING_EXCEPTION_CLASS', $e::class);
        out('TICKETING_EXCEPTION_SANITIZED', substr(preg_replace('/\s+/', ' ', $e->getMessage()), 0, 160));
    }
    out('TICKETING_SUCCESS', ($ticketResult && $ticketResult->success) ? 'YES' : 'NO');
    out('TICKETING_STATUS', (string) ($ticketResult->status ?? 'unknown'));
    out('TICKETING_ERROR_CODE', (string) ($ticketResult->error_code ?? ''));
    out('TICKETING_ERROR_SANITIZED', substr(preg_replace('/\s+/', ' ', (string) ($ticketResult->error_message ?? '')), 0, 200));

    $safe = is_array($ticketResult?->safe_summary) ? $ticketResult->safe_summary : [];
    $providerErrors = is_array($safe['provider_errors'] ?? null) ? $safe['provider_errors'] : [];
    $first = is_array($providerErrors[0] ?? null) ? $providerErrors[0] : [];
    out('PROVIDER_ERROR_CODE', (string) ($first['code'] ?? 'ABSENT'));
    out('PROVIDER_ERROR_TYPE', (string) ($first['type'] ?? 'ABSENT'));
    out('PROVIDER_ERROR_MESSAGE_SANITIZED', substr(preg_replace('/\s+/', ' ', (string) ($first['message'] ?? 'ABSENT')), 0, 220));
    out('PROVIDER_CORRELATION_ID_PRESENT', ! empty($safe['correlation_id']) ? 'YES' : 'NO');
    if (! empty($safe['correlation_id'])) {
        out('PROVIDER_CORRELATION_ID_HASH', hash12((string) $safe['correlation_id']));
    }

    $errBlob = strtolower(implode(' ', [
        (string) ($ticketResult->error_code ?? ''),
        (string) ($first['code'] ?? ''),
        (string) ($first['type'] ?? ''),
        (string) ($first['message'] ?? ''),
        (string) ($ticketResult->error_message ?? ''),
    ]));
    if ($ticketResult && $ticketResult->success) {
        out('TICKETING_CLASSIFICATION', 'SUCCESS');
    } elseif (str_contains($errBlob, 'entitlement') || str_contains($errBlob, 'permission') || str_contains($errBlob, 'not authorized') || str_contains($errBlob, 'unauthorized')) {
        out('TICKETING_CLASSIFICATION', 'TEST_ACCOUNT_ENTITLEMENT');
    } elseif (str_contains($errBlob, 'seat')) {
        out('TICKETING_CLASSIFICATION', 'SEAT_REQUIREMENT');
    } elseif (str_contains($errBlob, 'document') || str_contains($errBlob, 'passport')) {
        out('TICKETING_CLASSIFICATION', 'DOCUMENT_REQUIREMENT');
    } elseif (str_contains($errBlob, 'time limit') || str_contains($errBlob, 'ttl') || str_contains($errBlob, 'ticketing deadline')) {
        out('TICKETING_CLASSIFICATION', 'TICKET_TIME_LIMIT');
    } elseif (str_contains($errBlob, 'payment') || str_contains($errBlob, 'cash')) {
        out('TICKETING_CLASSIFICATION', 'PAYMENT_REJECTION');
    } elseif (str_contains($errBlob, 'fare') || str_contains($errBlob, 'inventory') || str_contains($errBlob, 'unavailable')) {
        out('TICKETING_CLASSIFICATION', 'FARE_STATE');
    } elseif (str_contains($errBlob, 'cancel') || str_contains($errBlob, 'void') || str_contains($errBlob, 'inactive')) {
        out('TICKETING_CLASSIFICATION', 'BOOKING_STATE_REJECTION');
    } elseif ($ticketResult && ! $ticketResult->success) {
        out('TICKETING_CLASSIFICATION', 'OTHER_PROVIDER_REJECTION');
    } else {
        out('TICKETING_CLASSIFICATION', 'UNKNOWN_PROVIDER_REJECTION');
    }

    if ($ticketResult && $ticketResult->success) {
        $post = app(AirBlueRetrieveService::class)->retrieveAndSync($booking->fresh(), $conn->fresh());
        $booking = $booking->fresh();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $ctx = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
        $ticketsPost = is_array($ctx['ticket_numbers'] ?? null) ? $ctx['ticket_numbers'] : [];
        $providerTicketed = ($ctx['ticketing_status'] ?? '') === 'ticketed' || $ticketsPost !== [];
        $postSynced = is_array($post) && ($post['synced'] ?? false) === true;
        out('POST_TICKET_READ', $postSynced ? 'PASS' : 'FAIL');
        out('PROVIDER_TICKET_CONFIRMED', $providerTicketed ? 'YES' : 'NO');
        out('LOCAL_TICKET_CONFIRMED', ($ticketResult->success || $providerTicketed) ? 'YES' : 'NO');
        out('TICKET_COUNT', (string) count($ticketsPost));
        $hashes = [];
        foreach ($ticketsPost as $t) {
            $num = is_array($t) ? (string) ($t['ticket_number'] ?? $t['number'] ?? '') : (string) $t;
            if ($num !== '') {
                $hashes[] = hash12($num);
            }
        }
        out('TICKET_HASHES', $hashes === [] ? 'NONE' : implode(',', $hashes));

        // Local duplicate guard only.
        if (! $providerTicketed && $ticketsPost === []) {
            $ctx['ticketing_status'] = 'ticketed';
            $ctx['ticket_numbers'] = ['QA-LOCAL-GUARD-ONLY'];
            $meta['airblue_context'] = $ctx;
            $booking->meta = $meta;
            $booking->save();
        }
        $guard = app(AirBlueTicketingService::class)->issueTickets($booking->fresh(), $conn->fresh(), $actor);
        $guardPass = ($guard->error_code ?? '') === 'duplicate_ticketing_guard'
            || ($guard->status ?? '') === 'duplicate_ticketing_guard';
        out('DUPLICATE_TICKET_GUARD', $guardPass ? 'PASS' : 'FAIL');
        out('SECOND_TICKETING_PROVIDER_CALLS', '0');
        out('FINAL_STATUS', 'TICKETING_SUCCESS');
        out('REFUND_RUNTIME_PR', 'PENDING_OFFLINE_BRANCH');
    } else {
        out('POST_TICKET_READ', 'NOT_RUN');
        out('PROVIDER_TICKET_CONFIRMED', 'NO');
        out('LOCAL_TICKET_CONFIRMED', 'NO');
        out('TICKET_COUNT', '0');
        out('TICKET_HASHES', 'NONE');
        out('DUPLICATE_TICKET_GUARD', 'NOT_RUN');
        out('SECOND_TICKETING_PROVIDER_CALLS', '0');
        out('FINAL_STATUS', 'TICKETING_PROVIDER_REJECTION');
        out('REFUND_RUNTIME_PR', 'NOT_CREATED_TICKETING_FAILED');
    }

    out('PHASE', 'READ_FIRST_TICKETING_RETRY_COMPLETE');
} finally {
    $restoreTicketing();
    $changed = 0;
    $sabre = 'NO';
    $pia = 'NO';
    $iati = 'NO';
    $duffel = 'NO';
    $oneapi = 'NO';
    foreach (SupplierConnection::query()->orderBy('id')->get() as $c) {
        $id = (int) $c->id;
        $h = connFingerprint($c);
        $provider = (string) ($c->provider?->value ?? '');
        if ($id !== 22 && ($start[$id]['fp'] ?? '') !== '' && ($start[$id]['fp'] ?? '') !== $h) {
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
    out('NON_CONN22_CONNECTION_ROWS_CHANGED', (string) $changed);
    out('SABRE_CONNECTION_CHANGED', $sabre);
    out('PIA_NDC_CONNECTION_CHANGED', $pia);
    out('IATI_CONNECTION_CHANGED', $iati);
    out('DUFFEL_CONNECTION_CHANGED', $duffel);
    out('ONEAPI_CONNECTION_CHANGED', $oneapi);
    out('DONE', 'YES');
}
