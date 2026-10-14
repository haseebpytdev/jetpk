<?php
$appRoot = '/home/pkjetp/jetpk_app';
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo 'jobs='.Illuminate\Support\Facades\DB::table('jobs')->count().PHP_EOL;
echo 'failed='.Illuminate\Support\Facades\DB::table('failed_jobs')->count().PHP_EOL;
