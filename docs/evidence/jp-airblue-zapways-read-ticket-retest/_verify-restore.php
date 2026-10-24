<?php
declare(strict_types=1);
$appRoot = '/home/pkjetp/jetpk_app';
chdir($appRoot);
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SupplierConnection;

$c = SupplierConnection::find(22);
$cred = is_array($c?->credentials) ? $c->credentials : [];
$t = filter_var($cred['ticketing_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
echo 'TICKETING_ENABLED_NOW='.($t ? 'true' : 'false').PHP_EOL;
echo 'CONN22_HOST='.(parse_url((string) $c?->base_url, PHP_URL_HOST) ?: '').PHP_EOL;
echo 'CONN22_PROTOCOL='.(string) ($cred['protocol_version'] ?? '').PHP_EOL;
echo 'CONN22_CHANNEL='.(string) ($cred['api_channel'] ?? '').PHP_EOL;
echo 'BACKUP_PRESENT='.(is_file('/tmp/airblue-read-ticket-retest-conn22-backup.json') ? 'YES' : 'NO').PHP_EOL;

$log = storage_path('logs/airblue-'.gmdate('Y-m-d').'.log');
if (! is_file($log)) {
    $log = storage_path('logs/airblue-2026-10-24.log');
}
echo 'LOG_PATH='.$log.PHP_EOL;
if (is_file($log)) {
    $lines = file($log, FILE_IGNORE_NEW_LINES);
    $tail = array_slice($lines ?: [], -15);
    foreach ($tail as $line) {
        $red = preg_replace('/[A-Za-z0-9+\/=]{40,}/', '[REDACTED]', $line) ?? $line;
        $red = preg_replace('/"(request_xml|response_xml)":"[^"]*"/', '"$1":"[REDACTED]"', $red) ?? $red;
        echo 'LOG='.substr($red, 0, 400).PHP_EOL;
    }
}
echo 'DONE=YES'.PHP_EOL;
