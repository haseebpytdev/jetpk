<?php

/**
 * Safe AirBlue QA isolation probe — search only. No AirBook/ticket/payment.
 * Run: lsphp docs/evidence/.../_remote-qa-search-probe.php  (from app root after upload to /tmp)
 */

use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\FlightSearch\FlightSearchService;
use App\Support\FlightSearch\AirBlueQaSearchScope;
use App\Support\FlightSearch\QaStaffAirBlueContext;

$base = getenv('APP_BASE') ?: '/home/pkjetp/jetpk_app';
require $base.'/vendor/autoload.php';

$app = require $base.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$out = static function (array $row): void {
    echo json_encode($row, JSON_UNESCAPED_SLASHES).PHP_EOL;
};

$connectionId = (int) config('ota.qa_staff_airblue.connection_id', 23);
$connection = SupplierConnection::query()->find($connectionId);
$scope = app(AirBlueQaSearchScope::class)->evaluate($connection);

$out([
    'stage' => 'scope',
    'allowed' => (bool) ($scope['allowed'] ?? false),
    'connection_id' => $scope['connection_id'] ?? null,
    'provider' => $scope['provider'] ?? null,
    'environment' => $scope['environment'] ?? null,
    'protocol' => $scope['protocol'] ?? null,
    'test_host' => $scope['test_host'] ?? null,
    'service_target' => $scope['service_target'] ?? null,
    'reason' => $scope['reason'] ?? null,
]);

if (! ($scope['allowed'] ?? false)) {
    exit(2);
}

$agency = Agency::query()->where('slug', config('ota.default_agency_slug'))->first();
$depart = now()->addWeeks(3)->format('Y-m-d');
$criteria = [
    'trip_type' => 'one_way',
    'origin' => 'LHE',
    'destination' => 'KHI',
    'depart_date' => $depart,
    'adults' => 1,
    'children' => 0,
    'infants' => 0,
    'cabin' => 'economy',
];

$result = app(FlightSearchService::class)->searchWithMeta(
    $criteria,
    $agency,
    QaStaffAirBlueContext::SOURCE_CHANNEL,
    null,
);

$offers = is_array($result['offers'] ?? null) ? $result['offers'] : [];
$connIds = [];
$providers = [];
$families = [];
$display = [];

foreach ($offers as $offer) {
    if (! is_array($offer)) {
        continue;
    }
    $cid = (int) ($offer['supplier_connection_id'] ?? 0);
    if ($cid > 0) {
        $connIds[$cid] = ($connIds[$cid] ?? 0) + 1;
    }
    $p = strtolower((string) ($offer['supplier_provider'] ?? ''));
    if ($p !== '') {
        $providers[$p] = ($providers[$p] ?? 0) + 1;
    }
    $opts = is_array($offer['fare_family_options'] ?? null) ? $offer['fare_family_options'] : [];
    foreach ($opts as $opt) {
        if (! is_array($opt)) {
            continue;
        }
        $code = strtoupper(trim((string) ($opt['code'] ?? $opt['fare_family'] ?? $opt['brand_code'] ?? '')));
        if ($code !== '') {
            $families[$code] = true;
        }
    }
    $display[] = [
        'offer_id_hash' => substr(hash('sha256', (string) ($offer['id'] ?? $offer['offer_id'] ?? '')), 0, 12),
        'connection_id' => $cid,
        'provider' => $p,
        'customer_total' => $offer['final_customer_price'] ?? $offer['total'] ?? null,
        'currency' => $offer['currency'] ?? $offer['pricing_currency'] ?? null,
        'fare_option_count' => count($opts),
        'has_supplier_total' => array_key_exists('supplier_total', $offer),
    ];
}

$out([
    'stage' => 'qa_search',
    'source_channel' => QaStaffAirBlueContext::SOURCE_CHANNEL,
    'depart' => $depart,
    'raw_offers' => count($offers),
    'connection_ids' => $connIds,
    'providers' => $providers,
    'fare_family_codes' => array_keys($families),
    'display_sample' => array_slice($display, 0, 6),
    'warnings_count' => count(is_array($result['warnings'] ?? null) ? $result['warnings'] : []),
    'book_create_calls' => 0,
]);
