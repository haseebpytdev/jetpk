$key = "$env:USERPROFILE\.ssh\jetpk_contabo_2026_v2"
$remote = "pkjetp@185.215.166.176"
$out = "docs/evidence/jp-airblue-v3-frontend-uat/_raw-isolation.txt"
ssh -i $key -o BatchMode=yes -o ConnectTimeout=30 $remote @'
APP=/home/pkjetp/jetpk_app
PHP=/usr/local/lsws/lsphp83/bin/php
cd "$APP"
$PHP -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$list=config("ota.public_flight_results_suppliers");
if (!is_array($list)) { $list=[]; }
$lower=array_map(fn($v)=>strtolower((string)$v), $list);
echo "PUBLIC_SUPPLIERS=".json_encode(array_values($lower)).PHP_EOL;
echo "AIRBLUE_IN_PUBLIC_ALLOWLIST=".(in_array("airblue",$lower,true)?"YES":"NO").PHP_EOL;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\AirBlueConnectionSearchPolicy;
$V3_HASH="535fa30d7e25";
$v3=null;
foreach (SupplierConnection::query()->where("provider","airblue")->orderBy("id")->get() as $c) {
  $cred=is_array($c->credentials)?$c->credentials:[];
  if ((string)($cred["protocol_version"]??"")==="3.0" && substr(hash("sha256",(string)$c->id),0,12)===$V3_HASH) { $v3=$c; break; }
}
if (!$v3) { echo "V3_CONNECTION=MISSING".PHP_EOL; exit(0); }
$cred=is_array($v3->credentials)?$v3->credentials:[];
$eligible=app(AirBlueConnectionSearchPolicy::class)->isEligibleForPublicSearch($v3);
echo "V3_PROTOCOL=".(string)($cred["protocol_version"]??"").PHP_EOL;
echo "V3_ENV=".(string)($v3->environment?->value ?? "").PHP_EOL;
echo "V3_PUBLIC_SEARCH_ELIGIBLE=".($eligible?"YES":"NO").PHP_EOL;
echo "V3_SEARCH_CERTIFIED=".((string)($cred["search_certified"]??"false")).PHP_EOL;
echo "V3_CERT_STATUS=".(string)($cred["certification_status"]??"").PHP_EOL;
echo "CONN22_PROTOCOL=";
$c22=SupplierConnection::find(22);
$c22c=is_array($c22?->credentials)?$c22->credentials:[];
echo (string)($c22c["protocol_version"]??"2.0").PHP_EOL;
'
'@ | Tee-Object -FilePath $out
