<?php

$appRoot = '/home/pkjetp/jetpk_app';
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo 'FAILED_JOBS='.DB::table('failed_jobs')->count().PHP_EOL;
echo 'PENDING_DEFAULT='.DB::table('jobs')->where('queue', 'default')->count().PHP_EOL;
echo 'PENDING_ALL='.DB::table('jobs')->count().PHP_EOL;
echo 'RUNTIME_SHA='.trim((string) @file_get_contents($appRoot.'/.jetpk-runtime-sha')).PHP_EOL;
echo 'DASH_BUILD='.trim((string) @file_get_contents($appRoot.'/dashboard/.next/BUILD_ID')).PHP_EOL;
