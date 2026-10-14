<?php

/**
 * Wave 1C — archive sanitized metadata for all default-queue jobs, then delete.
 * NEVER executes / releases jobs. Never calls queue:work.
 *
 * Usage (production app root):
 *   php docs/evidence/jp-ops-readiness-01/_wave1c-archive-and-delete-default.php --dry-run
 *   php docs/evidence/jp-ops-readiness-01/_wave1c-archive-and-delete-default.php --execute
 */

$dryRun = in_array('--dry-run', $argv, true);
$execute = in_array('--execute', $argv, true);
if (! $dryRun && ! $execute) {
    fwrite(STDERR, "Specify --dry-run or --execute\n");
    exit(1);
}

$appRoot = '/home/pkjetp/jetpk_app';
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$evidenceDir = $appRoot.'/docs/evidence/jp-ops-readiness-01';
if (! is_dir($evidenceDir)) {
    mkdir($evidenceDir, 0755, true);
}

$rows = DB::table('jobs')->where('queue', 'default')->orderBy('id')->get();
$archive = [];
$ids = [];

foreach ($rows as $row) {
    $payload = json_decode((string) $row->payload, true);
    $displayName = (string) ($payload['displayName'] ?? 'UNKNOWN');
    $command = (string) ($payload['data']['command'] ?? '');
    // Sanitize: strip emails and long bodies
    $commandSafe = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[redacted-email]', $command) ?? '';
    $commandSafe = preg_replace('/s:\d+:"<[^"]{200,}"/', 's:0:"[redacted-html]"', $commandSafe) ?? $commandSafe;
    $commandSafe = mb_substr($commandSafe, 0, 800);

    $archive[] = [
        'id' => (int) $row->id,
        'queue' => (string) $row->queue,
        'attempts' => (int) $row->attempts,
        'created_at' => $row->created_at,
        'available_at' => $row->available_at,
        'display_name' => $displayName,
        'command_sanitized_prefix' => $commandSafe,
        'executed' => false,
    ];
    $ids[] = (int) $row->id;
}

$stamp = gmdate('Ymd\THis\Z');
$archivePath = $evidenceDir.'/DEFAULT-QUEUE-ARCHIVE-'.$stamp.'.json';
$summary = [
    'mode' => $execute ? 'execute' : 'dry-run',
    'count' => count($ids),
    'ids' => $ids,
    'OLD_SERIALIZED_CLOSURES_EXECUTED' => 0,
    'archived_at_utc' => gmdate('c'),
];

file_put_contents($archivePath, json_encode([
    'summary' => $summary,
    'jobs' => $archive,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

echo 'ARCHIVE='.$archivePath.PHP_EOL;
echo 'COUNT='.count($ids).PHP_EOL;

if ($execute) {
    if (count($ids) === 0) {
        echo "DELETE_COUNT=0\n";
        exit(0);
    }
    $deleted = DB::table('jobs')->where('queue', 'default')->whereIn('id', $ids)->delete();
    echo 'DELETE_COUNT='.$deleted.PHP_EOL;
    $remaining = DB::table('jobs')->where('queue', 'default')->count();
    echo 'PENDING_DEFAULT_AFTER='.$remaining.PHP_EOL;
} else {
    echo "DRY_RUN_NO_DELETE=1\n";
}

echo "OLD_SERIALIZED_CLOSURES_EXECUTED=0\n";
