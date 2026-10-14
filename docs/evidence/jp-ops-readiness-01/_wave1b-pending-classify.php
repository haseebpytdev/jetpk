<?php

/**
 * Classify all pending default-queue jobs. READ-ONLY. No dispatch/delete.
 * Sanitized: no payload body dump.
 */

$appRoot = '/home/pkjetp/jetpk_app';
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::table('jobs')->where('queue', 'default')->orderBy('id')->get();
$groups = [];

foreach ($rows as $row) {
    $payload = json_decode((string) $row->payload, true);
    $displayName = 'UNKNOWN';
    if (is_array($payload)) {
        if (! empty($payload['displayName']) && is_string($payload['displayName'])) {
            $displayName = $payload['displayName'];
        } elseif (! empty($payload['data']['commandName']) && is_string($payload['data']['commandName'])) {
            $displayName = $payload['data']['commandName'];
        } elseif (! empty($payload['job']) && is_string($payload['job'])) {
            $displayName = $payload['job'];
        }
        // Try unserialize command class only (no dump)
        if ($displayName === 'UNKNOWN' && ! empty($payload['data']['command']) && is_string($payload['data']['command'])) {
            if (preg_match('/O:\d+:"([^"]+)"/', $payload['data']['command'], $m)) {
                $displayName = $m[1];
            }
        }
    }

    $created = is_numeric($row->created_at) ? (int) $row->created_at : strtotime((string) $row->created_at);
    $available = is_numeric($row->available_at) ? (int) $row->available_at : strtotime((string) $row->available_at);
    $attempts = (int) $row->attempts;

    $key = $displayName;
    if (! isset($groups[$key])) {
        $groups[$key] = [
            'job_class' => $displayName,
            'count' => 0,
            'oldest_at' => $created,
            'newest_at' => $created,
            'max_attempts' => $attempts,
            'sample_ids' => [],
        ];
    }
    $g = &$groups[$key];
    $g['count']++;
    $g['oldest_at'] = min($g['oldest_at'], $created);
    $g['newest_at'] = max($g['newest_at'], $created);
    $g['max_attempts'] = max($g['max_attempts'], $attempts);
    if (count($g['sample_ids']) < 8) {
        $g['sample_ids'][] = (int) $row->id;
    }
    unset($g);
}

foreach ($groups as &$g) {
    $g['oldest_at_iso'] = gmdate('c', $g['oldest_at']);
    $g['newest_at_iso'] = gmdate('c', $g['newest_at']);
}
unset($g);

uasort($groups, static fn ($a, $b) => $b['count'] <=> $a['count']);

echo json_encode([
    'total' => count($rows),
    'group_count' => count($groups),
    'groups' => array_values($groups),
    'probed_at_utc' => gmdate('c'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
