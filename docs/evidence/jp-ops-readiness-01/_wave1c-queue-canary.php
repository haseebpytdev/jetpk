<?php

/**
 * Wave 1C QA queue canary — dispatches DeliverQueuedOperationalMail only.
 * No booking/payment/supplier mutation. Recipient must be QA-safe.
 *
 * Usage:
 *   WAVE1C_CANARY_TO=qa@example.com php _wave1c-queue-canary.php dispatch
 *   php _wave1c-queue-canary.php observe
 */

$appRoot = '/home/pkjetp/jetpk_app';
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Jobs\Notifications\DeliverQueuedOperationalMail;
use App\Models\Agency;
use App\Models\CommunicationLog;
use Illuminate\Support\Facades\DB;

$mode = $argv[1] ?? 'observe';
$marker = 'WAVE1C-QUEUE-CANARY-'.gmdate('Ymd\THis\Z');

function counts(): array
{
    return [
        'PENDING_DEFAULT' => (int) DB::table('jobs')->where('queue', 'default')->count(),
        'PENDING_TRANSACTIONAL' => (int) DB::table('jobs')->where('queue', 'notifications-transactional')->count(),
        'FAILED_JOBS' => (int) DB::table('failed_jobs')->count(),
    ];
}

if ($mode === 'dispatch') {
    $to = getenv('WAVE1C_CANARY_TO') ?: '';
    if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
        fwrite(STDERR, "WAVE1C_CANARY_TO missing/invalid\n");
        exit(2);
    }

    $agencyId = Agency::query()->orderBy('id')->value('id');
    $log = CommunicationLog::query()->create([
        'agency_id' => $agencyId,
        'channel' => 'email',
        'event' => 'ops.wave1c.queue_canary',
        'status' => 'queued',
        'recipient_email' => $to,
        'subject' => $marker,
        'message' => 'QA-only Wave 1C queue routing canary. Safe to ignore.',
        'meta' => ['wave' => '1C', 'marker' => $marker, 'qa_only' => true],
    ]);

    DeliverQueuedOperationalMail::dispatch(
        [$to],
        [],
        [],
        $marker,
        '<p>QA-only Wave 1C queue routing canary. Safe to ignore.</p>',
        'QA-only Wave 1C queue routing canary. Safe to ignore.',
        [],
        $log->id,
        null,
    );

    $job = DB::table('jobs')->where('queue', 'notifications-transactional')->orderByDesc('id')->first();
    $defaultJob = DB::table('jobs')->where('queue', 'default')->orderByDesc('id')->first();

    $out = array_merge([
        'MODE' => 'dispatch',
        'MARKER' => $marker,
        'COMMUNICATION_LOG_ID' => $log->id,
        'CANARY_JOB_CLASS' => 'App\\Jobs\\Notifications\\DeliverQueuedOperationalMail',
        'CANARY_QUEUE' => $job ? (string) $job->queue : 'NOT_FOUND',
        'CANARY_JOB_ID' => $job ? (int) $job->id : null,
        'CANARY_ON_DEFAULT' => $defaultJob ? 'YES' : 'NO',
        'DEFAULT_WORKER_RUNNING' => 'NO',
    ], counts());

    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    exit(0);
}

// observe
$logId = isset($argv[2]) ? (int) $argv[2] : 0;
$jobId = isset($argv[3]) ? (int) $argv[3] : 0;
$markerArg = $argv[4] ?? '';

$log = $logId > 0 ? CommunicationLog::query()->find($logId) : null;
$jobStill = $jobId > 0 ? DB::table('jobs')->where('id', $jobId)->exists() : null;

$recentFail = DB::table('failed_jobs')
    ->where('failed_at', '>=', now()->subMinutes(10))
    ->orderByDesc('id')
    ->first();

$out = array_merge([
    'MODE' => 'observe',
    'MARKER' => $markerArg,
    'COMMUNICATION_LOG_ID' => $logId,
    'COMMUNICATION_LOG_STATUS' => $log?->status,
    'COMMUNICATION_LOG_SENT_AT' => optional($log?->sent_at)?->toIso8601String(),
    'CANARY_JOB_STILL_PENDING' => $jobStill === null ? 'UNKNOWN' : ($jobStill ? 'YES' : 'NO'),
    'CANARY_CONSUMED' => ($jobStill === false) ? 'YES' : 'NO',
    'RECENT_FAILED_JOB_ID' => $recentFail->id ?? null,
    'MAIL_NOTIFICATION_QUEUE' => (string) config('mail.notification_queue'),
    'RUNTIME_SHA' => is_file($appRoot.'/.jetpk-runtime-sha') ? trim((string) file_get_contents($appRoot.'/.jetpk-runtime-sha')) : 'MISSING',
], counts());

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
