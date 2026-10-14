<?php

/**
 * List jetpk_app tar backups with sizes for retention decisions.
 * Run on production via php /tmp/...
 */

$dir = '/home/pkjetp/backups';
$files = glob($dir.'/jetpk_app-*.tar.gz') ?: [];
$out = [];
foreach ($files as $f) {
    $out[] = [
        'path' => $f,
        'filename' => basename($f),
        'size' => filesize($f),
        'mtime' => gmdate('c', filemtime($f)),
        'mtime_unix' => filemtime($f),
    ];
}
usort($out, static fn ($a, $b) => $b['mtime_unix'] <=> $a['mtime_unix']);
echo json_encode(['count' => count($out), 'total_bytes' => array_sum(array_column($out, 'size')), 'files' => $out], JSON_PRETTY_PRINT)."\n";
