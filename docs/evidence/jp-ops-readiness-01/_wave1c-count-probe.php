<?php

$appRoot = '/home/pkjetp/jetpk_app';
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo 'PENDING_DEFAULT='.DB::table('jobs')->where('queue', 'default')->count().PHP_EOL;
echo 'FAILED_JOBS='.DB::table('failed_jobs')->count().PHP_EOL;
echo 'PENDING_NOTIFICATIONS='.DB::table('jobs')->where('queue', 'like', 'notifications-%')->count().PHP_EOL;
echo 'RUNTIME_SHA=';
$marker = $appRoot.'/storage/app/deploy-sha.txt';
if (is_file($marker)) {
    echo trim((string) file_get_contents($marker)).PHP_EOL;
} else {
    echo 'MISSING'.PHP_EOL;
}
