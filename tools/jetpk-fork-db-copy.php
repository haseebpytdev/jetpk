<?php

declare(strict_types=1);

$src = $argv[1] ?? '';
$dst = $argv[2] ?? '';

if ($src === '' || $dst === '') {
    fwrite(STDERR, "Usage: php jetpk-fork-db-copy.php <source.sqlite> <dest.sqlite>\n");
    exit(1);
}

if (! is_file($src)) {
    fwrite(STDERR, "Source not found: {$src}\n");
    exit(1);
}

$dstDir = dirname($dst);
if (! is_dir($dstDir) && ! mkdir($dstDir, 0775, true) && ! is_dir($dstDir)) {
    fwrite(STDERR, "Cannot create directory: {$dstDir}\n");
    exit(1);
}

$pdo = new PDO('sqlite:'.$src);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$escaped = str_replace("'", "''", $dst);
$pdo->exec("VACUUM INTO '{$escaped}'");

echo 'ok '.filesize($dst).PHP_EOL;
