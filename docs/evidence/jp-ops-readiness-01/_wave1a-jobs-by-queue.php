<?php

$appRoot = '/home/pkjetp/jetpk_app';
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$rows = Illuminate\Support\Facades\DB::table('jobs')
    ->selectRaw('queue, count(*) as c')
    ->groupBy('queue')
    ->orderByDesc('c')
    ->get();

foreach ($rows as $r) {
    echo $r->queue.'='.$r->c.PHP_EOL;
}

echo 'oldest='.Illuminate\Support\Facades\DB::table('jobs')->min('created_at').PHP_EOL;
echo 'newest='.Illuminate\Support\Facades\DB::table('jobs')->max('created_at').PHP_EOL;
