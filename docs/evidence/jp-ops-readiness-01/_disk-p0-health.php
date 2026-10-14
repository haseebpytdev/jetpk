<?php

$appRoot = '/home/pkjetp/jetpk_app';
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$runtime = is_file($appRoot.'/.jetpk-runtime-sha')
    ? trim((string) file_get_contents($appRoot.'/.jetpk-runtime-sha'))
    : 'MISSING';
$deployTxt = is_file($appRoot.'/storage/app/deploy-sha.txt')
    ? trim((string) file_get_contents($appRoot.'/storage/app/deploy-sha.txt'))
    : 'MISSING';
$authSha = is_file($appRoot.'/.jetpk-authorized-sha')
    ? trim((string) file_get_contents($appRoot.'/.jetpk-authorized-sha'))
    : 'MISSING';
$build = is_file($appRoot.'/dashboard/.next/BUILD_ID')
    ? trim((string) file_get_contents($appRoot.'/dashboard/.next/BUILD_ID'))
    : 'MISSING';

echo json_encode([
    'PROBED_AT_UTC' => gmdate('c'),
    'RUNTIME_SHA' => $runtime,
    'AUTHORIZED_SHA' => $authSha,
    'DEPLOY_SHA_TXT' => $deployTxt,
    'DASHBOARD_BUILD_ID' => $build,
    'FAILED_JOBS' => (int) DB::table('failed_jobs')->count(),
    'PENDING_DEFAULT' => (int) DB::table('jobs')->where('queue', 'default')->count(),
    'PENDING_ALL' => (int) DB::table('jobs')->count(),
    'MAIL_NOTIFICATION_QUEUE' => (string) config('mail.notification_queue', 'MISSING'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
