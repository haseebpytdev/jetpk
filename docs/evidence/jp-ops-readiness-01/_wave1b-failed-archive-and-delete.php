<?php

/**
 * Wave 1B: archive sanitized metadata for historical SMTP failed jobs, then delete ONLY those rows.
 * No retry. No flush. No payload dump.
 */

$appRoot = '/home/pkjetp/jetpk_app';
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$pre = (int) DB::table('failed_jobs')->count();
if ($pre !== 147) {
    fwrite(STDERR, "STOP: FAILED_JOBS_PRE expected 147 got {$pre}\n");
    exit(2);
}

$rows = DB::table('failed_jobs')->orderBy('id')->get();
$ids = [];
$archive = [];

foreach ($rows as $row) {
    $payload = json_decode((string) $row->payload, true);
    $displayName = 'UNKNOWN';
    if (is_array($payload) && ! empty($payload['displayName']) && is_string($payload['displayName'])) {
        $displayName = $payload['displayName'];
    }

    $exLines = preg_split("/\r\n|\n|\r/", (string) $row->exception) ?: [];
    $first = isset($exLines[0]) ? trim((string) $exLines[0]) : 'UNKNOWN';
    $first = preg_replace('/(password|token|secret|authorization|api[_-]?key|bearer)\s*[:=]\s*\S+/i', '$1=***', $first) ?? $first;
    $first = preg_replace('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', '[email]', $first) ?? $first;
    $exClass = 'UNKNOWN';
    if (preg_match('/^([A-Za-z0-9_\\\\]+(?:Exception|Error))/', $first, $m)) {
        $exClass = $m[1];
    }
    $sig = preg_replace('/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i', '{uuid}', $first) ?? $first;
    $sig = preg_replace('/\b\d{4,}\b/', '{n}', $sig) ?? $sig;
    $sig = mb_substr($sig, 0, 180);

    // Safety: only delete DeliverQueuedOperationalMail UnexpectedResponseException SMTP rejects
    if ($displayName !== 'App\\Jobs\\Notifications\\DeliverQueuedOperationalMail') {
        fwrite(STDERR, "STOP: unexpected job class id={$row->id} class={$displayName}\n");
        exit(3);
    }
    if (! str_contains($first, 'UnexpectedResponseException') && $exClass !== 'Symfony\\Component\\Mailer\\Exception\\UnexpectedResponseException') {
        fwrite(STDERR, "STOP: unexpected exception id={$row->id}\n");
        exit(4);
    }

    $ids[] = (int) $row->id;
    $archive[] = [
        'id' => (int) $row->id,
        'uuid' => (string) $row->uuid,
        'job_class' => $displayName,
        'queue' => (string) $row->queue,
        'failed_at' => (string) $row->failed_at,
        'exception_class' => $exClass,
        'sanitized_error_signature' => $sig,
        'business_effect' => 'historical_missing_operational_email',
        'disposition' => 'ARCHIVE_THEN_DELETE_NO_RETRY',
    ];
}

if (count($ids) !== 147) {
    fwrite(STDERR, 'STOP: archive count '.count($ids)." != 147\n");
    exit(5);
}

$archivePath = '/tmp/FAILED-JOBS-ARCHIVE-20261014.json';
file_put_contents($archivePath, json_encode(['count' => count($archive), 'rows' => $archive], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

$deleted = DB::table('failed_jobs')->whereIn('id', $ids)->delete();
$post = (int) DB::table('failed_jobs')->count();

echo json_encode([
    'failed_jobs_pre' => $pre,
    'archived' => count($archive),
    'deleted' => $deleted,
    'failed_jobs_post' => $post,
    'archive_path' => $archivePath,
], JSON_PRETTY_PRINT)."\n";
