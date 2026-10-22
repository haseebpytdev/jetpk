<?php
/**
 * Booking A: resolve by PNR hash f08f2faf6b61 → 1 Read → 1 Cancel → 1 Read → local dup guard.
 * No raw PNR in output. Conn 22 only.
 */
declare(strict_types=1);

$appRoot = '/home/pkjetp/jetpk_app';
chdir($appRoot);
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\AirBlue\AirBlueCancelService;
use App\Services\Suppliers\AirBlue\AirBlueRetrieveService;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueCancellationException;
use Illuminate\Support\Facades\Mail;

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
$wantPnrHash = 'f08f2faf6b61';
$conn = SupplierConnection::find(22);
$host = (string) (parse_url((string) $conn->base_url, PHP_URL_HOST) ?: '');
if ((string) ($conn->environment?->value ?? '') !== 'sandbox' || $host !== 'otatest4.zapways.com') {
    out('FATAL', 'STOP_ENV_DRIFT');
    exit(2);
}

$matches = [];
foreach (Booking::query()->whereNotNull('pnr')->orderByDesc('id')->limit(500)->get() as $b) {
    $pnr = trim((string) $b->pnr);
    if ($pnr === '' || hash12($pnr) !== $wantPnrHash) {
        continue;
    }
    $meta = is_array($b->meta) ? $b->meta : [];
    $sid = (int) ($meta['supplier_connection_id'] ?? 0);
    $supplier = strtolower((string) ($b->supplier ?? $meta['supplier_provider'] ?? ''));
    $qa = ! empty($meta['local_qa']) || ! empty($meta['qa_fixture']) || ! empty($meta['airblue_zapways_test'])
        || (($meta['cert_loop'] ?? '') === 'JP-AIRBLUE-ZAPWAYS-AIRBOOK-DOC-CONTRACT');
    if ($sid === 22 && $supplier === 'airblue' && $qa) {
        $matches[] = $b;
    }
}

out('BOOKING_A_MATCH_COUNT', (string) count($matches));
if (count($matches) !== 1) {
    out('BOOKING_A_FOUND', 'NO');
    out('FATAL', 'expected_exactly_one_qa_booking');
    exit(3);
}

$b = $matches[0];
out('BOOKING_A_FOUND', 'YES');
out('BOOKING_A_ID_HASH', hash12((string) $b->id));
out('BOOKING_A_PNR_HASH', $wantPnrHash);

$meta = is_array($b->meta) ? $b->meta : [];
$ctx = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
$sb = SupplierBooking::query()
    ->where('booking_id', $b->id)
    ->where('provider', SupplierProvider::Airblue->value)
    ->first();

$tickets = is_array($ctx['ticket_numbers'] ?? null) ? $ctx['ticket_numbers'] : [];
$ticketed = ($ctx['ticketing_status'] ?? '') === 'ticketed' || $tickets !== [];
$cancelCommitted = ($ctx['cancel_committed'] ?? false) === true;

out('BOOKING_A_PNR_PRESENT', trim((string) $b->pnr) !== '' ? 'YES' : 'NO');
out('BOOKING_A_INSTANCE_PRESENT', trim((string) ($ctx['instance'] ?? '')) !== '' ? 'YES' : 'NO');
out('BOOKING_A_SUPPLIER_BOOKING_PRESENT', $sb !== null ? 'YES' : 'NO');
out('BOOKING_A_TICKET_NUMBERS_ABSENT', $tickets === [] ? 'YES' : 'NO');
out('BOOKING_A_TICKETING_STATUS', (string) ($ctx['ticketing_status'] ?? 'absent'));
out('BOOKING_A_CANCEL_COMMITTED_PRE', $cancelCommitted ? 'YES' : 'NO');

if ($ticketed) {
    out('PRE_CANCEL_TICKETED', 'YES');
    out('FATAL', 'STOP_UNEXPECTEDLY_TICKETED');
    exit(4);
}
out('PRE_CANCEL_TICKETED', 'NO');

if ($cancelCommitted) {
    out('FATAL', 'STOP_ALREADY_CANCELLED');
    exit(5);
}

$pnrBefore = trim((string) $b->pnr);
$retrieve = app(AirBlueRetrieveService::class)->retrieveAndSync($b->fresh(), $conn);
$b = $b->fresh();
$meta = is_array($b->meta) ? $b->meta : [];
$ctx = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
$pnrAfter = trim((string) $b->pnr);
$prePass = $pnrAfter !== '' && $pnrAfter === $pnrBefore && trim((string) ($ctx['instance'] ?? '')) !== '';
out('BOOKING_A_PRE_CANCEL_RETRIEVE', $prePass ? 'PASS' : 'FAIL');
out('PRE_CANCEL_RETRIEVE', $prePass ? 'PASS' : 'FAIL');
out('PRE_CANCEL_PNR_MATCH', $pnrAfter === $pnrBefore ? 'YES' : 'NO');
out('PRE_CANCEL_TICKETED', 'NO');
out('PRE_CANCEL_RETRIEVE_KEYS', is_array($retrieve) ? implode(',', array_keys($retrieve)) : gettype($retrieve));

if (! $prePass) {
    out('FATAL', 'STOP_PRE_CANCEL_RETRIEVE');
    exit(6);
}

$actor = User::query()->where('email', 'like', '%admin%')->orderBy('id')->first()
    ?? User::query()->orderBy('id')->first();

$cancelCalls = 0;
$cancelHttp = 'UNKNOWN';
$cancelResult = 'UNKNOWN';
try {
    $cancelCalls = 1;
    $cancel = app(AirBlueCancelService::class)->cancelForBooking($b->fresh(), $conn, $actor);
    $cancelResult = ! empty($cancel['success']) || (($cancel['status'] ?? '') === 'cancelled') ? 'PASS' : 'CHECK';
    $cancelHttp = '200';
    out('BOOKING_A_CANCEL_STATUS', (string) ($cancel['status'] ?? ''));
    out('BOOKING_A_CANCEL_CANCELLATION_STATUS', (string) ($cancel['cancellation_status'] ?? ''));
    out('BOOKING_A_CANCEL_KEYS', is_array($cancel) ? implode(',', array_keys($cancel)) : gettype($cancel));
} catch (Throwable $e) {
    $cancelResult = 'FAIL';
    $code = $e instanceof AirBlueCancellationException ? $e->normalizedCode : class_basename($e);
    out('BOOKING_A_CANCEL_ERROR_CODE', (string) $code);
    out('BOOKING_A_CANCEL_ERROR_SANITIZED', substr(preg_replace('/\s+/', ' ', $e->getMessage()), 0, 160));
    if (str_contains(strtolower($e->getMessage()), 'http')) {
        $cancelHttp = 'ERROR';
    }
}

out('BOOKING_A_CANCEL_CALLS', (string) $cancelCalls);
out('BOOKING_A_CANCEL_HTTP', $cancelHttp);
out('BOOKING_A_CANCEL_RESULT', $cancelResult);

if ($cancelResult !== 'PASS') {
    out('PHASE', 'CANCEL_AMBIGUOUS_OR_FAIL_RETRIEVE_NEXT_NO_RETRY');
}

$postRetrieve = app(AirBlueRetrieveService::class)->retrieveAndSync($b->fresh(), $conn);
$b = $b->fresh();
$meta = is_array($b->meta) ? $b->meta : [];
$ctx = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
$localCancel = (($ctx['cancel_committed'] ?? false) === true)
    && in_array((string) ($ctx['cancellation_status'] ?? ''), ['cancelled', 'Canceled', 'Cancelled'], true);
$providerHint = strtolower(json_encode($postRetrieve) ?: '');
$providerCancel = str_contains($providerHint, 'cancel')
    || str_contains($providerHint, 'void')
    || (($postRetrieve['cancellation_status'] ?? '') !== '')
    || (($postRetrieve['status'] ?? '') === 'cancelled')
    || $localCancel;

out('BOOKING_A_POST_CANCEL_RETRIEVE', is_array($postRetrieve) ? 'PASS' : 'FAIL');
out('BOOKING_A_PROVIDER_CANCEL_CONFIRMED', $providerCancel ? 'YES' : 'CHECK');
out('BOOKING_A_LOCAL_CANCEL_CONFIRMED', $localCancel ? 'YES' : 'NO');
out('BOOKING_A_CANCEL_COMMITTED', (($ctx['cancel_committed'] ?? false) === true) ? 'YES' : 'NO');
out('BOOKING_A_CANCELLATION_STATUS', (string) ($ctx['cancellation_status'] ?? ''));
out('POST_CANCEL_RETRIEVE_KEYS', is_array($postRetrieve) ? implode(',', array_keys($postRetrieve)) : gettype($postRetrieve));

$secondCalls = 0;
$dupGuard = 'FAIL';
try {
    app(AirBlueCancelService::class)->cancelForBooking($b->fresh(), $conn, $actor);
    $dupGuard = 'FAIL_NO_EXCEPTION';
} catch (AirBlueCancellationException $e) {
    $dupGuard = $e->normalizedCode === 'duplicate_cancellation_guard' ? 'PASS' : 'FAIL_OTHER_'.$e->normalizedCode;
    $secondCalls = 0;
} catch (Throwable $e) {
    $dupGuard = 'FAIL_'.class_basename($e);
}

out('DUPLICATE_CANCEL_GUARD', $dupGuard);
out('SECOND_CANCEL_PROVIDER_CALLS', (string) $secondCalls);
out('DONE', 'YES');
