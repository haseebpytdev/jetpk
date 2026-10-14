<?php

$appRoot = '/home/pkjetp/jetpk_app';
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$out = [
    'PROBED_AT_UTC' => gmdate('c'),
    'RUNTIME_SHA' => is_file($appRoot.'/.jetpk-runtime-sha') ? trim((string) file_get_contents($appRoot.'/.jetpk-runtime-sha')) : 'MISSING',
    'MAIL_NOTIFICATION_QUEUE' => (string) config('mail.notification_queue', 'MISSING'),
    'QUEUE_CONNECTION' => (string) config('queue.default'),
    'FAILED_JOBS' => (int) DB::table('failed_jobs')->count(),
    'PENDING_DEFAULT' => (int) DB::table('jobs')->where('queue', 'default')->count(),
    'PENDING_TRANSACTIONAL' => (int) DB::table('jobs')->where('queue', 'notifications-transactional')->count(),
    'PENDING_CRITICAL' => (int) DB::table('jobs')->where('queue', 'notifications-critical')->count(),
    'PENDING_OPS' => (int) DB::table('jobs')->where('queue', 'notifications-ops')->count(),
    'PENDING_BULK' => (int) DB::table('jobs')->where('queue', 'notifications-bulk')->count(),
];

// A8 — recent failed job classes (sanitized)
$recentFails = DB::table('failed_jobs')->orderByDesc('id')->limit(5)->get(['id', 'queue', 'failed_at', 'payload']);
$failSummary = [];
foreach ($recentFails as $row) {
    $payload = json_decode((string) $row->payload, true);
    $failSummary[] = [
        'id' => (int) $row->id,
        'queue' => (string) $row->queue,
        'failed_at' => (string) $row->failed_at,
        'displayName' => (string) ($payload['displayName'] ?? 'UNKNOWN'),
    ];
}
$out['RECENT_FAILED_JOBS'] = $failSummary;

// A2 — role inventory counts only (no PII)
$roleCounts = [];
if (Schema::hasTable('users') && Schema::hasColumn('users', 'account_type')) {
    $rows = DB::table('users')->select('account_type', DB::raw('count(*) as c'))->groupBy('account_type')->get();
    foreach ($rows as $r) {
        $roleCounts[(string) $r->account_type] = (int) $r->c;
    }
}
$out['USER_ACCOUNT_TYPE_COUNTS'] = $roleCounts;

// Communication observability
if (Schema::hasTable('communication_logs')) {
    $out['COMM_LOG_STATUS_COUNTS_24H'] = DB::table('communication_logs')
        ->where('created_at', '>=', now()->subDay())
        ->select('status', DB::raw('count(*) as c'))
        ->groupBy('status')
        ->pluck('c', 'status')
        ->all();
    $canary = DB::table('communication_logs')->where('id', 747)->first(['id', 'status', 'event', 'sent_at']);
    $out['WAVE1C_CANARY_LOG'] = $canary ? [
        'id' => (int) $canary->id,
        'status' => (string) $canary->status,
        'event' => (string) $canary->event,
        'sent_at' => (string) ($canary->sent_at ?? ''),
    ] : null;
}

// Schedule table presence
$out['SCHEDULE_RUN_ARTIFACT'] = is_file($appRoot.'/storage/logs/notification-worker-cron.log') ? 'YES' : 'NO';
$logTail = '';
$logPath = $appRoot.'/storage/logs/notification-worker-cron.log';
if (is_file($logPath)) {
    $lines = @file($logPath, FILE_IGNORE_NEW_LINES);
    if (is_array($lines)) {
        $logTail = implode("\n", array_slice($lines, -8));
    }
}
$out['WORKER_LOG_TAIL_SANITIZED'] = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email]', $logTail);

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
