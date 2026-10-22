<?php
/**
 * One-shot sanitized fault extract for latest QA sandbox AirBook failure.
 * No supplier retry. No credentials / raw SOAP dump.
 */
declare(strict_types=1);

$appRoot = '/home/pkjetp/jetpk_app';
chdir($appRoot);
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

function out(string $k, string|int|null $v): void
{
    echo $k.'='.(string) $v.PHP_EOL;
}

$booking = Booking::query()
    ->where('source_channel', 'sandbox_cert')
    ->where('meta->cert_loop', 'JP-AIRBLUE-ZAPWAYS-FULL-SANDBOX-CERT-POST113')
    ->orderByDesc('id')
    ->first();

if (! $booking) {
    out('FATAL', 'no_post113_booking');
    exit(2);
}

out('BOOKING_ID_HASH', substr(hash('sha256', (string) $booking->id), 0, 12));
out('BOOKING_STATUS', (string) $booking->status?->value ?? (string) $booking->status);
out('PNR_PRESENT', trim((string) ($booking->pnr ?? '')) !== '' ? 'YES' : 'NO');

$attempt = SupplierBookingAttempt::query()
    ->where('booking_id', $booking->id)
    ->where('provider', SupplierProvider::Airblue->value)
    ->orderByDesc('id')
    ->first();

if ($attempt) {
    out('ATTEMPT_STATUS', (string) ($attempt->status ?? ''));
    out('ATTEMPT_ERROR_CODE', (string) ($attempt->error_code ?? ''));
    $msg = (string) ($attempt->error_message ?? $attempt->message ?? '');
    // sanitize
    $msg = preg_replace('/[A-Za-z0-9+\/=]{40,}/', '[REDACTED]', $msg) ?? $msg;
    $msg = preg_replace('/password["\']?\s*[:=]\s*["\']?[^"\'\s]+/i', 'password=[REDACTED]', $msg) ?? $msg;
    out('ATTEMPT_ERROR_SNIP', substr($msg, 0, 240));
    $meta = is_array($attempt->meta ?? null) ? $attempt->meta : (is_array($attempt->response_meta ?? null) ? $attempt->response_meta : []);
    if ($meta !== []) {
        out('ATTEMPT_META_KEYS', implode(',', array_keys($meta)));
        foreach (['http_status', 'fault_code', 'fault_string', 'provider_error', 'soap_fault'] as $k) {
            if (isset($meta[$k]) && is_scalar($meta[$k])) {
                $v = (string) $meta[$k];
                $v = preg_replace('/[A-Za-z0-9+\/=]{40,}/', '[REDACTED]', $v) ?? $v;
                out('ATTEMPT_META_'.$k, substr($v, 0, 200));
            }
        }
    }
} else {
    out('ATTEMPT_PRESENT', 'NO');
}

// Scan recent laravel log for DocumentTypeGender / faultstring near AirBlue (sanitized)
$log = $appRoot.'/storage/logs/laravel.log';
if (is_readable($log)) {
    $tail = shell_exec('tail -n 400 '.escapeshellarg($log).' 2>/dev/null') ?: '';
    $patterns = [
        'DocumentTypeGender',
        'faultstring',
        'Object reference',
        'NamePrefix',
        'OperatingAirline',
        'fare_basis',
        'FareBasis',
        'supplier_http_error',
        'AirBook',
        'validation error',
    ];
    foreach ($patterns as $p) {
        if (stripos($tail, $p) !== false) {
            out('LOG_HIT', $p);
        }
    }
    if (preg_match('/faultstring[^<]{0,40}([^<\n]{1,160})/i', $tail, $m)) {
        out('LOG_FAULTSTRING_SNIP', substr(trim($m[1]), 0, 160));
    }
    if (preg_match('/Instance validation error:[^\n]{1,160}/i', $tail, $m)) {
        out('LOG_VALIDATION_SNIP', substr(trim($m[0]), 0, 160));
    }
    if (preg_match('/Object reference not set[^\n]{0,80}/i', $tail, $m)) {
        out('LOG_NRE_SNIP', substr(trim($m[0]), 0, 120));
    }
}

// Prove deployed wire gender mapping still present
$src = (string) file_get_contents($appRoot.'/app/Services/Suppliers/AirBlue/AirBlueOtaXmlBuilder.php');
out('DEPLOYED_MAPS_M_TO_MALE', str_contains($src, "'M', 'MALE' => 'Male'") || str_contains($src, "=> 'Male'") ? 'YES' : 'NO');
out('DEPLOYED_FARE_BASIS_FALLBACK', str_contains($src, 'fare_basis') && str_contains($src, 'rbd') ? 'YES' : 'NO');

out('NO_AIRBOOK_RETRY', 'YES');
out('EXTRACT_COMPLETE', 'YES');
