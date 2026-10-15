<?php
declare(strict_types=1);
/** READ-ONLY post-auth Wave A health probe. No mutations. */

require '/home/pkjetp/jetpk_app/vendor/autoload.php';
$app = require '/home/pkjetp/jetpk_app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$shaCandidates = [
    '/home/pkjetp/jetpk_app/CURRENT_SHA',
    '/home/pkjetp/jetpk_app/DEPLOYED_SHA',
    '/home/pkjetp/jetpk_app/.deploy-sha',
    '/home/pkjetp/jetpk_app/storage/app/deployed_sha.txt',
];
$sha = null;
foreach ($shaCandidates as $p) {
    if (is_file($p)) {
        $sha = trim((string) file_get_contents($p));
        if ($sha !== '') {
            break;
        }
    }
}

$buildCandidates = [
    '/home/pkjetp/jetpk_app/dashboard/.next/BUILD_ID',
    '/home/pkjetp/jetpk_app/frontend/.next/BUILD_ID',
];
$build = null;
foreach ($buildCandidates as $p) {
    if (is_file($p)) {
        $build = trim((string) file_get_contents($p));
        if ($build !== '') {
            break;
        }
    }
}

$disk = [];
$df = @shell_exec('df -h /home/pkjetp 2>/dev/null | tail -1');
if (is_string($df)) {
    $parts = preg_split('/\s+/', trim($df));
    $disk = [
        'filesystem' => $parts[0] ?? null,
        'size' => $parts[1] ?? null,
        'used' => $parts[2] ?? null,
        'avail' => $parts[3] ?? null,
        'use_pct' => $parts[4] ?? null,
        'mounted' => $parts[5] ?? null,
    ];
}

$workers = trim((string) @shell_exec("pgrep -af 'queue:work|queue:listen' 2>/dev/null | head -10"));

$http = [];
foreach (['/up', '/', '/login', '/admin', '/agent'] as $path) {
    $url = 'https://jetpakistan.pk'.$path;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_NOBODY => true,
        CURLOPT_HEADER => true,
    ]);
    curl_exec($ch);
    $http[$path] = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
}

$queuedSample = DB::table('communication_logs')
    ->where('status', 'queued')
    ->orderByDesc('id')
    ->limit(8)
    ->get(['id', 'event_type', 'channel', 'created_at', 'updated_at']);

$age = DB::table('communication_logs')
    ->where('status', 'queued')
    ->selectRaw('MIN(created_at) as oldest, MAX(created_at) as newest, COUNT(*) as cnt')
    ->first();

$byEvent = DB::table('communication_logs')
    ->where('status', 'queued')
    ->selectRaw('event_type, COUNT(*) as cnt')
    ->groupBy('event_type')
    ->orderByDesc('cnt')
    ->limit(15)
    ->get();

echo json_encode([
    'PROBED_AT_UTC' => gmdate('c'),
    'RUNTIME_SHA_FILE' => $sha,
    'BUILD_ID' => $build,
    'FAILED_JOBS' => (int) DB::table('failed_jobs')->count(),
    'PENDING_DEFAULT' => (int) DB::table('jobs')->where('queue', 'default')->count(),
    'PENDING_ALL_JOBS' => (int) DB::table('jobs')->count(),
    'PENDING_NOTIFICATION_QUEUES' => (int) DB::table('jobs')->where('queue', 'like', 'notifications%')->count(),
    'DEFAULT_WORKER_RAW' => $workers === '' ? 'NONE' : $workers,
    'DEFAULT_WORKER_RUNNING' => $workers !== '' && str_contains($workers, '--queue=default') ? 'YES' : 'NO',
    'ANY_QUEUE_WORKER' => $workers === '' ? 'NO' : 'YES',
    'DISK' => $disk,
    'HTTP' => $http,
    'COMM_LOGS_QUEUED' => [
        'total' => (int) ($age->cnt ?? 0),
        'oldest' => $age->oldest ?? null,
        'newest' => $age->newest ?? null,
        'by_event' => $byEvent,
        'sample_ids' => $queuedSample->pluck('id')->all(),
        'sample' => $queuedSample,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
