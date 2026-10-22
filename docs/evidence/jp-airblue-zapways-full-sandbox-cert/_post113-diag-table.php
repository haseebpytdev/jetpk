<?php
require '/home/pkjetp/jetpk_app/vendor/autoload.php';
$app = require '/home/pkjetp/jetpk_app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$t = 'supplier_diagnostic_logs';
echo 'COLUMNS='.implode(',', Schema::getColumnListing($t)).PHP_EOL;
$rows = DB::table($t)->orderByDesc('id')->limit(8)->get();
foreach ($rows as $r) {
    $a = (array) $r;
    echo '---'.PHP_EOL;
    foreach ($a as $k => $v) {
        if (is_string($v) && strlen($v) > 300) {
            $v = substr(preg_replace('/[A-Za-z0-9+\/=]{40,}/', '[REDACTED]', $v) ?? $v, 0, 300);
        }
        if (is_array($v) || (is_string($v) && str_starts_with(ltrim($v), '{'))) {
            $decoded = is_array($v) ? $v : json_decode($v, true);
            if (is_array($decoded)) {
                echo $k.'_KEYS='.implode(',', array_keys($decoded)).PHP_EOL;
                foreach (['error_code', 'http_status', 'fault_string', 'fault_code', 'correlation_id', 'safe_message', 'provider'] as $mk) {
                    if (isset($decoded[$mk]) && is_scalar($decoded[$mk])) {
                        echo $k.'_'.$mk.'='.substr((string) $decoded[$mk], 0, 160).PHP_EOL;
                    }
                }
                continue;
            }
        }
        if (in_array($k, ['id', 'provider', 'action', 'status', 'safe_message', 'message', 'error_code', 'created_at', 'booking_id', 'supplier_connection_id'], true)
            || (is_scalar($v) && strlen((string) $v) < 200)) {
            echo $k.'='.(is_scalar($v) || $v === null ? (string) $v : gettype($v)).PHP_EOL;
        }
    }
}
