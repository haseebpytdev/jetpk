<?php
declare(strict_types=1);
$appRoot = '/home/pkjetp/jetpk_app';
chdir($appRoot);
require $appRoot.'/vendor/autoload.php';
$app = require $appRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SupplierConnection;

function out(string $k, string|int|float|bool|null $v): void
{
    if (is_bool($v)) {
        $v = $v ? 'YES' : 'NO';
    }
    echo $k.'='.(string) $v.PHP_EOL;
}

$start = json_decode((string) file_get_contents('/tmp/airblue-ticketing-cert-conn-start.json'), true);
if (! is_array($start)) {
    out('FATAL', 'missing_start_snapshot');
    exit(2);
}

$changed = 0;
foreach (SupplierConnection::query()->orderBy('id')->get() as $c) {
    $cred = is_array($c->credentials) ? $c->credentials : [];
    $fpPayload = [
        'id' => (int) $c->id,
        'provider' => (string) ($c->provider?->value ?? ''),
        'env' => (string) ($c->environment?->value ?? ''),
        'active' => (bool) $c->is_active,
        'host' => (string) (parse_url((string) $c->base_url, PHP_URL_HOST) ?: ''),
        'keys' => array_keys($cred),
        'search_certified' => $cred['search_certified'] ?? null,
        'ticketing_enabled' => $cred['ticketing_enabled'] ?? null,
        'cancel_enabled' => $cred['cancel_enabled'] ?? null,
        'booking_enabled' => $cred['booking_enabled'] ?? null,
        'protocol_version' => $cred['protocol_version'] ?? null,
        'api_channel' => $cred['api_channel'] ?? null,
    ];
    $h = hash('sha256', json_encode($fpPayload));
    $id = (int) $c->id;
    $base = (string) ($start[$id]['fp'] ?? '');
    $match = $base !== '' && $base === $h;
    out('CONN_'.$id.'_PROVIDER', $fpPayload['provider']);
    out('CONN_'.$id.'_MATCH', $match ? 'YES' : 'NO');
    out('CONN_'.$id.'_TICKETING', var_export($fpPayload['ticketing_enabled'], true));
    if ($id !== 22 && ! $match) {
        $changed++;
        $baseRow = $start[$id]['fp_payload'] ?? null;
        if (is_array($baseRow)) {
            foreach ($fpPayload as $k => $v) {
                $bv = $baseRow[$k] ?? null;
                if ($bv !== $v) {
                    out('CONN_'.$id.'_DIFF_'.$k, json_encode(['was' => $bv, 'now' => $v]));
                }
            }
        }
    }
    if ($id === 22) {
        out('CONN22_HOST', $fpPayload['host']);
        out('CONN22_ENV', $fpPayload['env']);
        out('CONN22_PROTOCOL', (string) $fpPayload['protocol_version']);
        out('CONN22_CHANNEL', (string) $fpPayload['api_channel']);
    }
}

$c22 = SupplierConnection::find(22);
$cred22 = is_array($c22?->credentials) ? $c22->credentials : [];
$tickNow = filter_var($cred22['ticketing_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
$tickPre = filter_var($start[22]['ticketing_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
out('TICKETING_ENABLED_PRE', $tickPre ? 'true' : 'false');
out('TICKETING_ENABLED_NOW', $tickNow ? 'true' : 'false');
out('TICKETING_ENABLED_RESTORED', $tickNow === $tickPre ? 'YES' : 'NO');
out('NON_CONN22_CONNECTION_ROWS_CHANGED', (string) $changed);
out('SABRE_CONNECTION_CHANGED', (($start[1]['fp'] ?? '') === hash('sha256', json_encode([
    'id' => 1,
    'provider' => (string) (SupplierConnection::find(1)?->provider?->value ?? ''),
    'env' => (string) (SupplierConnection::find(1)?->environment?->value ?? ''),
    'active' => (bool) SupplierConnection::find(1)?->is_active,
    'host' => (string) (parse_url((string) SupplierConnection::find(1)?->base_url, PHP_URL_HOST) ?: ''),
    'keys' => array_keys(is_array(SupplierConnection::find(1)?->credentials) ? SupplierConnection::find(1)->credentials : []),
    'search_certified' => (is_array(SupplierConnection::find(1)?->credentials) ? SupplierConnection::find(1)->credentials : [])['search_certified'] ?? null,
    'ticketing_enabled' => (is_array(SupplierConnection::find(1)?->credentials) ? SupplierConnection::find(1)->credentials : [])['ticketing_enabled'] ?? null,
    'cancel_enabled' => (is_array(SupplierConnection::find(1)?->credentials) ? SupplierConnection::find(1)->credentials : [])['cancel_enabled'] ?? null,
    'booking_enabled' => (is_array(SupplierConnection::find(1)?->credentials) ? SupplierConnection::find(1)->credentials : [])['booking_enabled'] ?? null,
    'protocol_version' => (is_array(SupplierConnection::find(1)?->credentials) ? SupplierConnection::find(1)->credentials : [])['protocol_version'] ?? null,
    'api_channel' => (is_array(SupplierConnection::find(1)?->credentials) ? SupplierConnection::find(1)->credentials : [])['api_channel'] ?? null,
])) ? 'NO' : 'YES'));
out('DONE', 'YES');
