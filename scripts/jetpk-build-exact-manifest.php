<?php

$root = dirname(__DIR__);
$outDir = $root.'/storage/app/audits/jetpk-9h-d';
$base = json_decode((string) file_get_contents($outDir.'/SFTP-MANIFEST.json'), true);
$files = array_filter($base['files'] ?? [], static fn (string $f): bool => ! str_starts_with($f, 'tests/'));
$extra = file($outDir.'/EXACT-PRODUCTION-MANIFEST.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/resources/views/dashboard/admin'));
foreach ($rii as $file) {
    if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
        continue;
    }
    $p = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    $content = (string) file_get_contents($file->getPathname());
    if (str_contains($content, 'jp-card')) {
        $files[] = $p;
    }
}
$files = array_values(array_unique(array_merge($files, $extra)));
sort($files);
file_put_contents($outDir.'/EXACT-PRODUCTION-MANIFEST.txt', implode(PHP_EOL, $files).PHP_EOL);
$web = array_values(array_filter($files, static fn (string $f): bool => str_starts_with($f, 'public/')));
file_put_contents($outDir.'/EXACT-LIVE-WEBROOT-MIRRORS.txt', implode(PHP_EOL, $web).PHP_EOL);
$sftp = array_map(static fn (string $f): string => 'put '.$f.' '.$f, $files);
file_put_contents($outDir.'/LIVE-SFTP-COMMANDS.txt', implode(PHP_EOL, $sftp).PHP_EOL);
echo count($files).' production files'.PHP_EOL;
