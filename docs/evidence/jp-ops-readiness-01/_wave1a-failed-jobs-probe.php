<?php

declare(strict_types=1);

/**
 * WAVE-1A failed_jobs triage — READ-ONLY. Never retry/flush/delete.
 * Sanitized: no raw payload dump; exception first line only.
 */

$appRoot = '/home/pkjetp/jetpk_app';
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$total = (int) DB::table('failed_jobs')->count();
$now = now();

$last24 = (int) DB::table('failed_jobs')->where('failed_at', '>=', $now->copy()->subDay())->count();
$last7d = (int) DB::table('failed_jobs')->where('failed_at', '>=', $now->copy()->subDays(7))->count();
$oldest = DB::table('failed_jobs')->min('failed_at');
$newest = DB::table('failed_jobs')->max('failed_at');

$rows = DB::table('failed_jobs')
    ->select(['id', 'uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at'])
    ->orderBy('failed_at')
    ->get();

$groups = [];

foreach ($rows as $row) {
    $displayName = 'UNKNOWN';
    $payload = json_decode((string) $row->payload, true);
    if (is_array($payload)) {
        if (! empty($payload['displayName']) && is_string($payload['displayName'])) {
            $displayName = $payload['displayName'];
        } elseif (! empty($payload['data']['commandName']) && is_string($payload['data']['commandName'])) {
            $displayName = $payload['data']['commandName'];
        } elseif (! empty($payload['job']) && is_string($payload['job'])) {
            $displayName = $payload['job'];
        }
    }

    $exLines = preg_split("/\r\n|\n|\r/", (string) $row->exception) ?: [];
    $exClass = 'UNKNOWN';
    $exSig = 'UNKNOWN';
    if (isset($exLines[0]) && is_string($exLines[0])) {
        $first = trim($exLines[0]);
        // Sanitize secrets-like fragments
        $first = preg_replace('/(password|token|secret|authorization|api[_-]?key|bearer)\s*[:=]\s*\S+/i', '$1=***', $first) ?? $first;
        $first = preg_replace('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', '[email]', $first) ?? $first;
        if (preg_match('/^([A-Za-z0-9_\\\\]+(?:Exception|Error))/', $first, $m)) {
            $exClass = $m[1];
        } elseif (preg_match('/^([A-Za-z0-9_\\\\]+):/', $first, $m)) {
            $exClass = $m[1];
        }
        // Normalize volatile ids/uuids/numbers for grouping
        $exSig = preg_replace('/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i', '{uuid}', $first) ?? $first;
        $exSig = preg_replace('/\b\d{4,}\b/', '{n}', $exSig) ?? $exSig;
        $exSig = mb_substr($exSig, 0, 180);
    }

    $key = $displayName.'|'.$row->queue.'|'.$exClass.'|'.$exSig;
    if (! isset($groups[$key])) {
        $groups[$key] = [
            'job_class' => $displayName,
            'queue' => (string) $row->queue,
            'connection' => (string) $row->connection,
            'exception_class' => $exClass,
            'sanitized_error_signature' => $exSig,
            'count' => 0,
            'oldest_at' => (string) $row->failed_at,
            'newest_at' => (string) $row->failed_at,
            'ids_sample' => [],
        ];
    }
    $groups[$key]['count']++;
    $groups[$key]['newest_at'] = (string) $row->failed_at;
    if (count($groups[$key]['ids_sample']) < 5) {
        $groups[$key]['ids_sample'][] = (int) $row->id;
    }
}

uasort($groups, static fn ($a, $b) => $b['count'] <=> $a['count']);

$summary = [
    'failed_jobs_total' => $total,
    'failed_jobs_last_24h' => $last24,
    'failed_jobs_last_7d' => $last7d,
    'oldest_at' => $oldest,
    'newest_at' => $newest,
    'group_count' => count($groups),
    'probed_at_utc' => gmdate('c'),
];

echo json_encode(['summary' => $summary, 'groups' => array_values($groups)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
