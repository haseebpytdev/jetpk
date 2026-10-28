<?php
declare(strict_types=1);
$appRoot = "/home/pkjetp/jetpk_app";
chdir($appRoot);
require $appRoot."/vendor/autoload.php";
$app = require $appRoot."/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\AirBlueConnectionSearchPolicy;

$list = config("ota.public_flight_results_suppliers");
if (! is_array($list)) {
    $list = [];
}
$lower = array_map(static fn ($v) => strtolower((string) $v), $list);
echo "PUBLIC_SUPPLIERS=".json_encode(array_values($lower)).PHP_EOL;
echo "AIRBLUE_IN_PUBLIC_ALLOWLIST=".(in_array("airblue", $lower, true) ? "YES" : "NO").PHP_EOL;

$V3_HASH = "535fa30d7e25";
$v3 = null;
foreach (SupplierConnection::query()->where("provider", "airblue")->orderBy("id")->get() as $c) {
    $cred = is_array($c->credentials) ? $c->credentials : [];
    if ((string) ($cred["protocol_version"] ?? "") === "3.0" && substr(hash("sha256", (string) $c->id), 0, 12) === $V3_HASH) {
        $v3 = $c;
        break;
    }
}
if (! $v3) {
    echo "V3_CONNECTION=MISSING".PHP_EOL;
    exit(0);
}
$cred = is_array($v3->credentials) ? $v3->credentials : [];
$eligible = app(AirBlueConnectionSearchPolicy::class)->isEligibleForPublicSearch($v3);
echo "V3_PROTOCOL=".(string) ($cred["protocol_version"] ?? "").PHP_EOL;
echo "V3_ENV=".(string) ($v3->environment?->value ?? "").PHP_EOL;
echo "V3_PUBLIC_SEARCH_ELIGIBLE=".($eligible ? "YES" : "NO").PHP_EOL;
echo "V3_SEARCH_CERTIFIED=".(string) ($cred["search_certified"] ?? "ABSENT").PHP_EOL;
echo "V3_CERT_STATUS=".(string) ($cred["certification_status"] ?? "ABSENT").PHP_EOL;

$c22 = SupplierConnection::find(22);
$c22c = is_array($c22?->credentials) ? $c22->credentials : [];
echo "CONN22_PROTOCOL=".(string) ($c22c["protocol_version"] ?? "2.0").PHP_EOL;
echo "DONE=YES".PHP_EOL;
