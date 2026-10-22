#!/usr/bin/env bash
set -uo pipefail
APP=/home/pkjetp/jetpk_app
PHP=/usr/local/lsws/lsphp83/bin/php

echo "=== AIRBLUE_LOG_LOCATE ==="
ls -la "$APP/storage/logs/airblue.log" 2>/dev/null || echo "airblue.log=MISSING"
find "$APP/storage/logs" -maxdepth 1 -type f -printf '%T@ %p\n' 2>/dev/null | sort -nr | head -15 | while read -r ts p; do
  echo "RECENT_LOG=$(basename "$p") SIZE=$(stat -c%s "$p")"
done

echo "=== GREP_PROVIDER_CALL ==="
for f in "$APP/storage/logs/airblue.log" "$APP/storage/logs/laravel.log"; do
  [[ -f "$f" ]] || continue
  echo "--- $f ---"
  grep -E 'airblue\.provider_call|supplier_http_error|http_status|faultstring|Object reference|DocumentTypeGender|air_book' "$f" 2>/dev/null \
    | tail -n 30 \
    | sed -E 's/[A-Za-z0-9+\/=]{40,}/[REDACTED]/g' \
    | sed -E 's/(MessagePassword|client_key|agent_password|password)[^,]{0,40}/[REDACTED]/Ig'
done

echo "=== SUPPLIER_DIAGNOSTICS ==="
$PHP <<'PHP'
<?php
require "/home/pkjetp/jetpk_app/vendor/autoload.php";
$app=require "/home/pkjetp/jetpk_app/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
foreach (["supplier_diagnostics","supplier_diagnostic_logs","api_logs","supplier_logs"] as $t) {
  if (Schema::hasTable($t)) echo "TABLE=$t\n";
}
try {
  $rows = DB::table("supplier_diagnostics")->orderByDesc("id")->limit(5)->get();
  foreach ($rows as $r) {
    $arr = (array)$r;
    echo "DIAG_ID=".$arr["id"]."\n";
    foreach (["provider","action","status","safe_message","error_code","http_status","created_at"] as $k) {
      if (array_key_exists($k,$arr)) echo "DIAG_$k=".substr((string)$arr[$k],0,120)."\n";
    }
    if (isset($arr["meta"])) {
      $meta = is_string($arr["meta"]) ? json_decode($arr["meta"], true) : (array)$arr["meta"];
      if (is_array($meta)) echo "DIAG_META_KEYS=".implode(",", array_keys($meta))."\n";
    }
  }
} catch (Throwable $e) {
  echo "DIAG_QUERY=".$e::class."\n";
}
PHP

echo "=== XML_STRUCTURE_DRYRUN ==="
$PHP <<'PHP'
<?php
require "/home/pkjetp/jetpk_app/vendor/autoload.php";
$app=require "/home/pkjetp/jetpk_app/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\AirBlueConfigResolver;
use App\Services\Suppliers\AirBlue\AirBlueOtaXmlBuilder;
use App\Services\Suppliers\AirBlue\AirBluePassengerPayloadBuilder;
$booking=Booking::query()->where("source_channel","sandbox_cert")->where("meta->cert_loop","JP-AIRBLUE-ZAPWAYS-FULL-SANDBOX-CERT-POST113")->orderByDesc("id")->with(["passengers","contact"])->first();
$conn=SupplierConnection::find(22);
$paxBuilder=app(AirBluePassengerPayloadBuilder::class);
$passengers=$paxBuilder->buildPassengersFromBooking($booking);
$contact=$paxBuilder->buildContactFromBooking($booking);
$meta=is_array($booking->meta)?$booking->meta:[];
$fromMeta=is_array($meta["airblue_context"]??null)?$meta["airblue_context"]:[];
$snapshot=is_array($booking->offer_snapshot)?$booking->offer_snapshot:(is_array($meta["offer_snapshot"]??null)?$meta["offer_snapshot"]:[]);
$raw=is_array($snapshot["raw_payload"]??null)?$snapshot["raw_payload"]:[];
$fromSnapshot=is_array($raw["provider_context"]??null)?$raw["provider_context"]:[];
$providerContext=array_merge($fromSnapshot,$fromMeta);
$config=app(AirBlueConfigResolver::class)->resolveOta($conn);
$xml=app(AirBlueOtaXmlBuilder::class)->buildAirBookRequest($config,$providerContext,$passengers,$contact);
foreach ([
  "NamePrefix","GivenName","Surname","Document","Telephone","Email","TravelerRefNumber",
  "OperatingAirline","MarketingAirline","DepartureAirport","ArrivalAirport",
  "FareBasisCodes","FareBasisCode","PTC_FareBreakdown","AirItinerary","PriceInfo","TravelerInfo",
  "ResBookDesigCode","FlightNumber",
] as $tag) {
  echo "TAG_$tag=".(str_contains($xml,"$tag")?"YES":"NO")."\n";
}
if (preg_match_all('/Gender="([^"]+)"/',$xml,$m)) echo "GENDER=".implode(",",array_unique($m[1]))."\n";
if (preg_match_all('/<ota:NamePrefix>([^<]*)<\/ota:NamePrefix>/',$xml,$m)) echo "NAMEPREFIX=".implode(",",array_unique($m[1]))."\n";
if (preg_match_all('/<ota:FareBasisCode>([^<]*)<\/ota:FareBasisCode>/',$xml,$m)) echo "FAREBASIS=".implode(",",array_unique($m[1]))."\n";
if (preg_match('/ResBookDesigCode="([^"]*)"/',$xml,$m)) echo "RBD=".$m[1]."\n";
if (preg_match('/OperatingAirline[^>]*Code="([^"]*)"/',$xml,$m)) echo "OP_CARRIER=".$m[1]."\n";
if (preg_match('/MarketingAirline[^>]*Code="([^"]*)"/',$xml,$m)) echo "MKT_CARRIER=".$m[1]."\n";
// ADT birthdate present?
echo "HAS_BIRTHDATE=".(str_contains($xml,'BirthDate=')?"YES":"NO")."\n";
echo "XML_LEN=".strlen($xml)."\n";
echo "NO_SUPPLIER_CALL=YES\n";
PHP

echo "FIND_FAULT_DONE"
