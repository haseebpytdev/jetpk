<?php

declare(strict_types=1);

$appRoot = '/home/pkjetp/jetpk_app';
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo 'APP_ENV='.config('app.env').PHP_EOL;
echo 'APP_DEBUG='.(config('app.debug') ? 'true' : 'false').PHP_EOL;
echo 'QUEUE='.config('queue.default').PHP_EOL;
echo 'MAIL='.config('mail.default').PHP_EOL;
try {
    echo 'FAILED_JOBS='.Illuminate\Support\Facades\DB::table('failed_jobs')->count().PHP_EOL;
} catch (Throwable $e) {
    echo 'FAILED_JOBS=ERROR'.PHP_EOL;
}
