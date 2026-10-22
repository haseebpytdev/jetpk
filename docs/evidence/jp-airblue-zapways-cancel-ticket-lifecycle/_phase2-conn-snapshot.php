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

$path = getenv('CONN_SNAPSHOT_PATH') ?: '/tmp/airblue-cancel-ticket-conn-start.json';
$rows = [];
foreach (SupplierConnection::query()->orderBy('id')->get() as $c) {
    $cred = is_array($c->credentials) ? $c->credentials : [];
    $fp = [
        'id' => (int) $c->id,
        'provider' => (string) ($c->provider?->value ?? ''),
        'env' => (string) ($c->environment?->value ?? ''),
        'active' => (bool) $c->is_active,
        'host' => (string) (parse_url((string) $c->base_url, PHP_URL_HOST) ?: ''),
        'keys' => array_keys($cred),
        'search_certified' => $cred['search_certified'] ?? null,
        'ticketing_enabled' => $cred['ticketing_enabled'] ?? null,
        'cancel_enabled' => $cred['cancel_enabled'] ?? null,
        'protocol_version' => $cred['protocol_version'] ?? null,
        'api_channel' => $cred['api_channel'] ?? null,
    ];
    $rows[(int) $c->id] = [
        'fp' => hash('sha256', json_encode($fp)),
        'provider' => $fp['provider'],
        'env' => $fp['env'],
        'host' => $fp['host'],
        'ticketing_enabled' => $fp['ticketing_enabled'],
        'protocol_version' => $fp['protocol_version'],
        'api_channel' => $fp['api_channel'],
    ];
}
file_put_contents($path, json_encode($rows, JSON_PRETTY_PRINT));
out('SNAPSHOT_PATH', $path);
out('SNAPSHOT_COUNT', (string) count($rows));
$conn22 = $rows[22] ?? null;
out('CONN22_PRESENT', $conn22 !== null);
if ($conn22) {
    out('CONN22_PROVIDER', (string) $conn22['provider']);
    out('CONN22_ENV', (string) $conn22['env']);
    out('CONN22_HOST', (string) $conn22['host']);
    out('CONN22_PROTOCOL', (string) ($conn22['protocol_version'] ?? ''));
    out('CONN22_CHANNEL', (string) ($conn22['api_channel'] ?? ''));
    out('CONN22_TICKETING_ENABLED', var_export($conn22['ticketing_enabled'], true));
    $ok = $conn22['provider'] === 'airblue'
        && $conn22['env'] === 'sandbox'
        && $conn22['host'] === 'otatest4.zapways.com';
    out('CONN22_ISOLATION', $ok ? 'PASS' : 'FAIL');
}
out('DONE', 'YES');
