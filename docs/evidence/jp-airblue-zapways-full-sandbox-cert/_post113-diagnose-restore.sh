#!/usr/bin/env bash
set -uo pipefail
PHP=/usr/local/lsws/lsphp83/bin/php
APP=/home/pkjetp/jetpk_app

echo "=== FAULT_EXTRACT ==="
$PHP /tmp/ab-post113/_post113-fault-extract.php

echo "=== RECENT_ATTEMPT_DB ==="
$PHP <<'PHP'
<?php
require "/home/pkjetp/jetpk_app/vendor/autoload.php";
$app=require "/home/pkjetp/jetpk_app/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use Illuminate\Support\Facades\Schema;
$b=Booking::query()->where("source_channel","sandbox_cert")->orderByDesc("id")->first();
if(!$b){echo "NO_BOOKING\n"; exit(0);} 
echo "BOOKING_ID_HASH=".substr(hash("sha256",(string)$b->id),0,12)."\n";
$cols=Schema::getColumnListing((new SupplierBookingAttempt)->getTable());
echo "ATTEMPT_COLUMNS=".implode(",",$cols)."\n";
$a=SupplierBookingAttempt::query()->where("booking_id",$b->id)->orderByDesc("id")->first();
if(!$a){echo "NO_ATTEMPT\n";}
else {
  foreach(["id","status","error_code","http_status","provider_status","message","error_message","failure_reason"] as $c){
    if(in_array($c,$cols,true)){
      $v=$a->{$c};
      if(is_string($v)) $v=substr(preg_replace('/[A-Za-z0-9+\/=]{40,}/','[REDACTED]',$v)??$v,0,200);
      echo "ATTEMPT_$c=".(is_scalar($v)||$v===null?(string)$v:json_encode($v))."\n";
    }
  }
  foreach(["meta","response","response_meta","request_meta","safe_summary"] as $c){
    if(in_array($c,$cols,true) && is_array($a->{$c}??null)){
      echo "ATTEMPT_{$c}_KEYS=".implode(",",array_keys($a->{$c}))."\n";
    }
  }
}
// build dry-run XML gender sample via reflection of private method if possible
$builder=app(App\Services\Suppliers\AirBlue\AirBlueOtaXmlBuilder::class);
$ref=new ReflectionClass($builder);
if($ref->hasMethod("zapwaysDocumentGender")){
  $m=$ref->getMethod("zapwaysDocumentGender");
  $m->setAccessible(true);
  echo "MAP_M=".$m->invoke($builder,"M")."\n";
  echo "MAP_F=".$m->invoke($builder,"F")."\n";
}
PHP

echo "=== LOG_GREP ==="
LOG=$APP/storage/logs/laravel.log
if [[ -f "$LOG" ]]; then
  tail -n 800 "$LOG" | grep -E -i 'DocumentTypeGender|faultstring|Object reference|AirBook|supplier_http_error|validation error|NamePrefix|OperatingAirline' | tail -n 40 | sed -E 's/[A-Za-z0-9+\/=]{40,}/[REDACTED]/g'
fi

echo "=== FORCE_RESTORE ==="
$PHP <<'PHP'
<?php
require "/home/pkjetp/jetpk_app/vendor/autoload.php";
$app=require "/home/pkjetp/jetpk_app/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$c=App\Models\SupplierConnection::find(22);
$cred=is_array($c->credentials)?$c->credentials:[];
$cred["ticketing_enabled"]=false;
$cred["cancel_enabled"]=false;
$cred["booking_enabled"]=true;
$c->credentials=$cred;
$c->save();
echo "ticketing_enabled=".(filter_var($cred["ticketing_enabled"],FILTER_VALIDATE_BOOLEAN)?"YES":"NO")."\n";
echo "cancel_enabled=".(filter_var($cred["cancel_enabled"],FILTER_VALIDATE_BOOLEAN)?"YES":"NO")."\n";
echo "booking_enabled=".(filter_var($cred["booking_enabled"],FILTER_VALIDATE_BOOLEAN)?"YES":"NO")."\n";
echo "ENV=".(string)($c->environment?->value??"")."\n";
echo "HOST=".(parse_url((string)$c->base_url,PHP_URL_HOST)?:"")."\n";
echo "AIRBLUE_EXTERNAL_CALLS_EFFECTIVELY_ENABLED_AFTER=".(filter_var(env("AIRBLUE_EXTERNAL_CALLS_ENABLED",false),FILTER_VALIDATE_BOOLEAN)?"YES":"NO")."\n";
echo "AIRBLUE_CLI_LIVE_ENABLED_AFTER=".(filter_var(env("AIRBLUE_CLI_LIVE_ENABLED",false),FILTER_VALIDATE_BOOLEAN)?"YES":"NO")."\n";
$sup=(string)env("OTA_PUBLIC_FLIGHT_RESULTS_SUPPLIERS","");
echo "AIRBLUE_PUBLIC_SEARCH_ENABLED_AFTER=".(stripos($sup,"airblue")!==false?"YES":"NO")."\n";
PHP

echo "=== UNIT_IF_PRESENT ==="
cd "$APP"
if [[ -f tests/Unit/Services/Suppliers/AirBlue/AirBlueOtaXmlBuilderTest.php ]]; then
  $PHP vendor/bin/phpunit --filter AirBlueOtaXmlBuilderTest tests/Unit/Services/Suppliers/AirBlue/AirBlueOtaXmlBuilderTest.php 2>&1 | tail -30
else
  echo "AIRBLUE_XML_TESTS=SKIPPED_NOT_DEPLOYED"
fi
$PHP vendor/bin/phpunit --filter 'AirBlueBookingUncertainStateTest|AirBlueExternalCallGateTest|AirBlueLiveCliGateTest|AirBlueClientRedactionTest|AirBlueVoid' 2>&1 | tail -40

echo "DIAGNOSE_RESTORE_DONE"
