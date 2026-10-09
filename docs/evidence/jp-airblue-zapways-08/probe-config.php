<?php

declare(strict_types=1);

$base = '/home/pkjetp/jetpk_app';
chdir($base);
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Data\FlightSearchRequestData;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\AirBlueClient;
use App\Services\Suppliers\AirBlue\AirBlueConfigResolver;
use App\Services\Suppliers\AirBlue\AirBlueFlightSearchService;
use App\Services\Suppliers\AirBlue\AirBlueOtaXmlBuilder;
use App\Services\Suppliers\SupplierConnectionService;
use App\Support\Security\SensitiveDataRedactor;

$connections = SupplierConnection::query()
    ->where('provider', 'airblue')
    ->orderByDesc('is_active')
    ->orderBy('id')
    ->get();

if ($connections->isEmpty()) {
    echo "CONNECTION_FOUND=NO\n";
    exit(1);
}

echo 'AIRBLUE_CONNECTION_COUNT='.$connections->count()."\n";
foreach ($connections as $row) {
    $cr = is_array($row->credentials) ? $row->credentials : [];
    $aid = trim((string) ($cr['agent_id'] ?? ''));
    echo 'CONN id='.$row->id.' active='.($row->is_active ? '1' : '0').' env='.($row->environment?->value ?? '').' name='.$row->name.' agent_id_set='.($aid !== '' ? 'YES' : 'NO').' agent_id_match='.($aid === 'JetPakistanOTA' ? 'YES' : 'NO')."\n";
}

$preferredId = isset($argv[2]) ? (int) $argv[2] : 0;
if ($preferredId > 0) {
    $connection = $connections->firstWhere('id', $preferredId) ?? $connections->first();
} else {
    $connection = $connections->first(fn ($row): bool => $row->name === 'AirBlue Zapways TEST v2')
        ?? $connections->first(fn ($row): bool => trim((string) ((is_array($row->credentials) ? $row->credentials : [])['agent_id'] ?? '')) === 'JetPakistanOTA')
        ?? $connections->first();
}

echo 'SELECTED_CONNECTION_ID='.$connection->id."\n";

$svc = app(SupplierConnectionService::class);
$creds = is_array($connection->credentials) ? $connection->credentials : [];
$agentId = trim((string) ($creds['agent_id'] ?? ''));

echo 'CONNECTION_ID='.$connection->id."\n";
echo 'ENVIRONMENT='.($connection->environment?->value ?? 'unknown')."\n";
echo 'CONNECTION_NAME='.$connection->name."\n";
echo 'CLIENT_ID_PRESENT='.(trim((string) ($creds['client_id'] ?? '')) !== '' ? 'YES' : 'NO')."\n";
echo 'CLIENT_KEY_PRESENT='.(trim((string) ($creds['client_key'] ?? '')) !== '' ? 'YES' : 'NO')."\n";
echo 'AGENT_ID_PRESENT='.($agentId !== '' ? 'YES' : 'NO')."\n";
echo 'AGENT_ID_MATCH='.($agentId === 'JetPakistanOTA' ? 'YES' : 'NO')."\n";
echo 'AGENT_PASSWORD_PRESENT='.(trim((string) ($creds['agent_password'] ?? '')) !== '' ? 'YES' : 'NO')."\n";
echo 'CONFIG_KEYS_VALID='.($svc->credentialKeysPresent($connection) ? 'YES' : 'NO')."\n";

$resolver = app(AirBlueConfigResolver::class);
$config = $resolver->resolveOta($connection);
echo 'ENDPOINT='.$config['endpoint_url']."\n";
echo 'PROTOCOL='.$config['protocol_version']."\n";
echo 'TARGET='.$config['service_target']."\n";
echo 'VERSION='.$config['service_version']."\n";

$cert = (string) ($config['tls_cert_path'] ?? '');
$key = (string) ($config['tls_key_path'] ?? '');
echo 'TLS_CERT_EXISTS='.(is_file($cert) ? 'YES' : 'NO')."\n";
echo 'TLS_KEY_EXISTS='.(is_file($key) ? 'YES' : 'NO')."\n";
echo 'TLS_CERT_READABLE='.(is_readable($cert) ? 'YES' : 'NO')."\n";
echo 'TLS_KEY_READABLE='.(is_readable($key) ? 'YES' : 'NO')."\n";

$mode = $argv[1] ?? 'config';
if ($mode === 'config') {
    exit(0);
}

if ($mode === 'auth_read') {
    $builder = app(AirBlueOtaXmlBuilder::class);
    $client = app(AirBlueClient::class);
    $xml = $builder->buildReadRequest($config, 'CERTPROBE', '1');
    try {
        $response = $client->callOta($connection, 'read', $xml, ['request_context' => 'cert_auth_probe']);
        $diag = is_array($response['_ota_diagnostic'] ?? null) ? $response['_ota_diagnostic'] : [];
        echo 'AUTH_HTTP_STATUS='.($diag['http_status'] ?? 'unknown')."\n";
        echo 'AUTH_RESULT=PASS'."\n";
        $fault = $response['soap_fault'] ?? null;
        if (is_array($fault)) {
            echo 'AUTH_SOAP_FAULT_CODE='.substr((string) ($fault['code'] ?? ''), 0, 120)."\n";
            echo 'AUTH_SOAP_FAULT_SUMMARY='.substr((string) ($fault['message'] ?? ''), 0, 200)."\n";
        }
    } catch (Throwable $e) {
        echo 'AUTH_RESULT=FAIL'."\n";
        echo 'AUTH_ERROR_CLASS='.$e::class."\n";
        if (method_exists($e, 'getCode')) {
            echo 'AUTH_HTTP_STATUS='.(string) $e->getCode()."\n";
        }
        if (property_exists($e, 'normalizedCode')) {
            echo 'AUTH_ERROR_CODE='.(string) $e->normalizedCode."\n";
        }
        if (method_exists($e, 'getMessage')) {
            echo 'AUTH_SAFE_MESSAGE='.substr((string) $e->getMessage(), 0, 200)."\n";
        }
        exit(2);
    }
    exit(0);
}

if ($mode === 'search_once') {
    $depart = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+14 days')->format('Y-m-d');
    $criteria = [
        'origin' => 'ISB',
        'destination' => 'LHE',
        'depart_date' => $depart,
        'adults' => 1,
        'children' => 0,
        'infants' => 0,
        'currency' => 'PKR',
        'trip_type' => 'one_way',
    ];
    echo 'SEARCH_ORIGIN=ISB'."\n";
    echo 'SEARCH_DESTINATION=LHE'."\n";
    echo 'SEARCH_DATE='.$depart."\n";

    $search = app(AirBlueFlightSearchService::class);
    $request = FlightSearchRequestData::fromArray($criteria);
    $result = $search->search($request, $connection);
    echo 'OFFER_COUNT='.count($result->offers)."\n";
    if (isset($result->meta['error_code'])) {
        echo 'SEARCH_ERROR_CODE='.$result->meta['error_code']."\n";
        echo 'SEARCH_RESULT=FAIL'."\n";
        exit(3);
    }
    echo 'SEARCH_RESULT=PASS'."\n";
    if ($result->offers !== []) {
        $first = $result->offers[0]->toArray();
        $safe = SensitiveDataRedactor::redact($first);
        echo 'FIRST_OFFER_ID='.(string) ($safe['offer_id'] ?? '')."\n";
        echo 'FIRST_ROUTE='.(string) ($safe['origin'] ?? '').'-'.(string) ($safe['destination'] ?? '')."\n";
    }
    exit(0);
}

echo "UNKNOWN_MODE\n";
exit(1);
