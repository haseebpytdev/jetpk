<?php
declare(strict_types=1);
$appRoot = '/home/pkjetp/jetpk_app';
chdir($appRoot);
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
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

$booking = Booking::query()
    ->where('source_channel', 'qa_ticketing_cert_booking_c')
    ->where('booking_reference', 'like', 'AB-TIX-C-%')
    ->orderByDesc('id')
    ->first();

if (! $booking) {
    out('FATAL', 'booking_c_not_found');
    exit(2);
}

out('BOOKING_REF_HASH', hash12((string) $booking->booking_reference));
out('BOOKING_ID_HASH', hash12((string) $booking->id));
out('PNR_PRESENT', trim((string) $booking->pnr) !== '' ? 'YES' : 'NO');
out('PNR_HASH', trim((string) $booking->pnr) !== '' ? hash12((string) $booking->pnr) : 'ABSENT');
out('PAYMENT_STATUS', (string) $booking->payment_status);
out('STATUS', (string) ($booking->status?->value ?? $booking->status));

$meta = is_array($booking->meta) ? $booking->meta : [];
$ctx = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
out('CTX_SUPPLIER_PAYABLE', (string) ($ctx['supplier_payable_amount'] ?? 'ABSENT'));
out('CTX_PAYMENT_CURRENCY', (string) ($ctx['payment_currency'] ?? 'ABSENT'));
out('CTX_PAYMENT_TYPE', (string) ($ctx['payment_type'] ?? 'ABSENT'));
out('CTX_TICKETING_STATUS', (string) ($ctx['ticketing_status'] ?? 'ABSENT'));
$tickets = is_array($ctx['ticket_numbers'] ?? null) ? $ctx['ticket_numbers'] : [];
out('CTX_TICKET_COUNT', (string) count($tickets));

$attempts = SupplierBookingAttempt::query()
    ->where('booking_id', $booking->id)
    ->orderByDesc('id')
    ->limit(10)
    ->get();
out('ATTEMPT_COUNT', (string) $attempts->count());
foreach ($attempts as $i => $a) {
    $safe = $a->safe_summary;
    if (is_string($safe)) {
        $decoded = json_decode($safe, true);
        $safe = is_array($decoded) ? $decoded : ['raw' => substr($safe, 0, 300)];
    }
    if (! is_array($safe)) {
        $safe = [];
    }
    // redact any accidental secrets
    unset($safe['request_xml'], $safe['response_xml'], $safe['credentials']);
    $errors = $safe['provider_errors'] ?? $safe['errors'] ?? null;
    $faultClass = $safe['fault_class'] ?? null;
    $faultSnippet = $safe['fault_snippet'] ?? null;
    out('ATTEMPT_'.$i.'_OP', (string) ($safe['operation'] ?? $safe['request_context'] ?? $a->operation ?? 'unknown'));
    out('ATTEMPT_'.$i.'_STATUS', (string) ($a->status ?? $safe['status'] ?? ''));
    out('ATTEMPT_'.$i.'_ERROR_CODE', (string) ($safe['error_code'] ?? $a->error_code ?? ''));
    out('ATTEMPT_'.$i.'_FAULT_CLASS', is_string($faultClass) ? $faultClass : 'ABSENT');
    if (is_string($faultSnippet)) {
        out('ATTEMPT_'.$i.'_FAULT_SNIPPET', substr(preg_replace('/\s+/', ' ', $faultSnippet), 0, 180));
    }
    if (is_array($errors)) {
        $san = [];
        foreach ($errors as $e) {
            if (! is_array($e)) {
                $san[] = substr(preg_replace('/\s+/', ' ', (string) $e), 0, 120);
                continue;
            }
            $san[] = substr(preg_replace('/\s+/', ' ', trim(($e['code'] ?? '').'|'.($e['message'] ?? '').'|'.($e['type'] ?? ''))), 0, 160);
        }
        out('ATTEMPT_'.$i.'_PROVIDER_ERRORS', implode(' || ', $san));
    }
    $keys = implode(',', array_slice(array_keys($safe), 0, 25));
    out('ATTEMPT_'.$i.'_SAFE_KEYS', $keys);
}

if (Schema::hasTable('supplier_call_logs')) {
    $logs = DB::table('supplier_call_logs')
        ->where('booking_id', $booking->id)
        ->orderByDesc('id')
        ->limit(10)
        ->get(['id', 'operation', 'status', 'error_code', 'http_status', 'created_at']);
    out('CALL_LOG_COUNT', (string) $logs->count());
    foreach ($logs as $i => $log) {
        out('CALL_'.$i.'_OP', (string) ($log->operation ?? ''));
        out('CALL_'.$i.'_STATUS', (string) ($log->status ?? ''));
        out('CALL_'.$i.'_ERROR', (string) ($log->error_code ?? ''));
        out('CALL_'.$i.'_HTTP', (string) ($log->http_status ?? ''));
    }
}

out('DONE', 'YES');
