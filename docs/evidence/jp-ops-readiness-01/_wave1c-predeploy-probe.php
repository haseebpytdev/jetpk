<?php

$appRoot = '/home/pkjetp/jetpk_app';
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$queues = [
    'default',
    'notifications-critical',
    'notifications-transactional',
    'notifications-ops',
    'notifications-bulk',
];

echo 'PROBED_AT_UTC='.gmdate('c').PHP_EOL;
echo 'QUEUE_CONNECTION='.config('queue.default').PHP_EOL;
echo 'MAIL_NOTIFICATION_QUEUE='.(string) config('mail.notification_queue', 'MISSING').PHP_EOL;
echo 'FAILED_JOBS='.DB::table('failed_jobs')->count().PHP_EOL;

foreach ($queues as $q) {
    $key = 'PENDING_'.strtoupper(str_replace('-', '_', $q));
    echo $key.'='.DB::table('jobs')->where('queue', $q)->count().PHP_EOL;
}

$shaFiles = [
    $appRoot.'/.jetpk-runtime-sha',
    $appRoot.'/storage/app/deploy-sha.txt',
];
foreach ($shaFiles as $f) {
    echo 'SHA_FILE='.$f.'='.(is_file($f) ? trim((string) file_get_contents($f)) : 'MISSING').PHP_EOL;
}

$class = $appRoot.'/app/Jobs/Notifications/DeliverQueuedOperationalMail.php';
echo 'DELIVER_JOB_PRESENT='.(is_file($class) ? 'YES' : 'NO').PHP_EOL;
echo 'DISK_LINE=';
passthru('df -h / | tail -1');
