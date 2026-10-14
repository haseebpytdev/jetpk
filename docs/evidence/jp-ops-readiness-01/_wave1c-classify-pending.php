<?php

/**
 * Wave 1C — classify all default-queue jobs. READ-ONLY DB writes: none.
 * Sanitized: no recipient emails, no HTML bodies in output.
 */

$appRoot = '/home/pkjetp/jetpk_app';
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$rows = DB::table('jobs')->where('queue', 'default')->orderBy('id')->get();
$closureOut = [];
$bookingOut = [];
$now = time();

foreach ($rows as $row) {
    $payload = json_decode((string) $row->payload, true);
    $displayName = (string) ($payload['displayName'] ?? 'UNKNOWN');
    $created = is_numeric($row->created_at) ? (int) $row->created_at : (int) strtotime((string) $row->created_at);
    $available = is_numeric($row->available_at) ? (int) $row->available_at : (int) strtotime((string) $row->available_at);
    $ageHours = (int) floor(($now - $created) / 3600);

    if (str_contains($displayName, 'BookingUniversalNotification') || str_contains($displayName, 'SendQueuedMailable')) {
        $eventType = 'booking_universal_mail';
        $command = (string) ($payload['data']['command'] ?? '');
        if (preg_match('/s:\d+:"type";s:\d+:"([^"]+)"/', $command, $m)) {
            $eventType = $m[1];
        } elseif (preg_match('/"type";s:\d+:"([^"]+)"/', $command, $m)) {
            $eventType = $m[1];
        }
        $bookingOut[] = [
            'id' => (int) $row->id,
            'created_at' => gmdate('c', $created),
            'available_at' => gmdate('c', $available),
            'attempts' => (int) $row->attempts,
            'job_class' => $displayName,
            'originating_event_type' => $eventType,
            'related_domain' => 'BOOKING_PAYMENT_COMMS',
            'age_hours' => $ageHours,
            'stale' => $ageHours >= 24,
            'safe_to_send_now' => false,
            'disposition' => $ageHours >= 24 ? 'OBSOLETE_DELETE' : 'MANUAL_REVIEW',
        ];
        continue;
    }

    // Closure path — extract communicationLogId if present
    $command = (string) ($payload['data']['command'] ?? '');
    $logId = null;
    if (preg_match('/communicationLogId[^;]*;i:(\d+);/', $command, $m)) {
        $logId = (int) $m[1];
    } elseif (preg_match('/"communicationLogId";i:(\d+);/', $command, $m)) {
        $logId = (int) $m[1];
    } elseif (preg_match('/i:(\d+);/', $command, $m) && preg_match('/communicationLogId/', $command)) {
        // fallback weaker
        if (preg_match_all('/i:(\d+);/', $command, $all) && count($all[1]) > 0) {
            // Prefer last int near log id naming — skip
        }
    }

    // SerializableClosure often embeds: communicationLogId";i:123;
    if ($logId === null && preg_match('/communicationLogId";(?:N|i:(\d+));/', $command, $m)) {
        $logId = isset($m[1]) && $m[1] !== '' ? (int) $m[1] : null;
    }

    $logStatus = null;
    $eventKey = null;
    $alreadySent = false;
    if ($logId !== null && Schema::hasTable('communication_logs')) {
        $log = DB::table('communication_logs')->where('id', $logId)->first();
        if ($log) {
            $logStatus = (string) ($log->status ?? '');
            $eventKey = isset($log->event_key) ? (string) $log->event_key : (isset($log->type) ? (string) $log->type : null);
            $alreadySent = in_array($logStatus, ['sent', 'delivered'], true);
        }
    }

    $domain = 'OPERATIONAL';
    $ek = strtolower((string) $eventKey);
    if (str_contains($ek, 'booking') || str_contains($ek, 'ticket') || str_contains($ek, 'pnr')) {
        $domain = 'BOOKING';
    } elseif (str_contains($ek, 'payment') || str_contains($ek, 'refund') || str_contains($ek, 'deposit')) {
        $domain = 'PAYMENT';
    } elseif (str_contains($ek, 'support')) {
        $domain = 'SUPPORT';
    } elseif (str_contains($ek, 'auth') || str_contains($ek, 'account') || str_contains($ek, 'otp') || str_contains($ek, 'welcome')) {
        $domain = 'ACCOUNT';
    }

    $disposition = 'MANUAL_REVIEW';
    if ($alreadySent) {
        $disposition = 'DUPLICATE_ALREADY_DELIVERED';
    } elseif ($ageHours >= 48) {
        $disposition = 'OBSOLETE_DELETE';
    } elseif ($ageHours >= 24) {
        $disposition = 'MANUAL_REVIEW';
    } else {
        $disposition = 'CURRENT_REPLAY_REQUIRED';
    }

    $closureOut[] = [
        'id' => (int) $row->id,
        'created_at' => gmdate('c', $created),
        'available_at' => gmdate('c', $available),
        'attempts' => (int) $row->attempts,
        'originating_event_type' => $eventKey ?? 'unknown_closure_mail',
        'related_domain' => $domain,
        'related_record_reference_safe' => $logId !== null ? 'communication_log_id:'.$logId : 'none',
        'recipient_class' => 'masked',
        'age_hours' => $ageHours,
        'delivery_already_recorded' => $alreadySent,
        'communication_log_status' => $logStatus,
        'business_event_still_current' => $ageHours < 24 && ! $alreadySent,
        'duplicate_send_risk' => $alreadySent || $ageHours >= 24,
        'stale' => $ageHours >= 24,
        'safe_to_replay' => false, // never execute serialized closure
        'disposition' => $disposition,
    ];
}

$summary = [
    'total' => count($rows),
    'closure_count' => count($closureOut),
    'booking_universal_count' => count($bookingOut),
    'disposition_counts_closure' => array_count_values(array_column($closureOut, 'disposition')),
    'disposition_counts_booking' => array_count_values(array_column($bookingOut, 'disposition')),
    'domain_counts_closure' => array_count_values(array_column($closureOut, 'related_domain')),
    'probed_at_utc' => gmdate('c'),
];

echo json_encode([
    'summary' => $summary,
    'closures' => $closureOut,
    'booking_universal' => $bookingOut,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
