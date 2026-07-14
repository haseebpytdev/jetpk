<?php

$root = dirname(__DIR__);
$outDir = $root.'/storage/app/audits/jetpk-9h-d';
if (! is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}

$excludePatterns = [
    '#^tests/#',
    '#^playwright/#',
    '#^storage/app/audits/#',
    '#^docs/#',
    '#^scripts/#',
    '#^node_modules/#',
    '#^vendor/#',
    '#^\.env#',
    '#^storage/logs/#',
    '#^storage/framework/#',
    '#^\.git/#',
];

$changed = [];
exec('git -C '.escapeshellarg($root).' diff --name-only HEAD', $changed);
$untracked = [];
exec('git -C '.escapeshellarg($root).' ls-files --others --exclude-standard', $untracked);
$candidates = array_values(array_unique(array_merge($changed, $untracked)));
sort($candidates);

$production = [];
$testOnly = [];
$webrootMirrors = [];

foreach ($candidates as $path) {
    $normalized = str_replace('\\', '/', $path);
    $isExcluded = false;
    foreach ($excludePatterns as $pattern) {
        if (preg_match($pattern, $normalized)) {
            $isExcluded = true;
            break;
        }
    }
    if ($isExcluded) {
        $testOnly[] = $normalized;

        continue;
    }
    if (! is_file($root.'/'.$normalized)) {
        continue;
    }
    $production[] = $normalized;
    if (str_starts_with($normalized, 'public/')) {
        $webrootMirrors[] = $normalized;
    }
}

file_put_contents($outDir.'/EXACT-PRODUCTION-MANIFEST.txt', implode(PHP_EOL, $production).PHP_EOL);
file_put_contents($outDir.'/EXACT-LIVE-WEBROOT-MIRRORS.txt', implode(PHP_EOL, $webrootMirrors).PHP_EOL);
file_put_contents($outDir.'/LOCAL-TEST-ONLY-FILES.txt', implode(PHP_EOL, $testOnly).PHP_EOL);

$sftp = [];
foreach ($production as $file) {
    $sftp[] = 'put '.$file.' '.$file;
}
file_put_contents($outDir.'/LIVE-SFTP-COMMANDS.txt', implode(PHP_EOL, $sftp).PHP_EOL);

echo 'production='.count($production).' webroot='.count($webrootMirrors).' test_only='.count($testOnly).PHP_EOL;
