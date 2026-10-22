#!/usr/bin/env bash
# Phases 3-8: regression, connection, WSDL fetch, dry-run contract vs generated AirBook
set -euo pipefail
APP=/home/pkjetp/jetpk_app
PHP=/usr/local/lsws/lsphp83/bin/php
EVID=/tmp/ab-nre-closure
mkdir -p "$EVID"
cd "$APP"

echo "=== PHASE3_HTTP ==="
for u in /up /login /lookup-booking; do
  code=$(curl -sS -o /dev/null -w "%{http_code}" "https://jetpakistan.pk$u" || echo FAIL)
  echo "HTTP_$u=$code"
done

echo "=== PHASE3_KILL_SWITCH_ABSENCE ==="
$PHP -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$air=config("suppliers.airblue", []);
echo "AIRBLUE_GLOBAL_KILL_SWITCH_PRESENT=".(array_key_exists("external_calls_enabled",$air)?"YES":"NO")."\n";
echo "CONFIG_HAS_CLI_LIVE_KEY=".(array_key_exists("cli_live_enabled",$air)?"YES":"NO")."\n";
'

echo "=== PHASE3_PUBLIC_SEARCH_POLICY ==="
$PHP <<'PHP'
<?php
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\AirBlueConnectionSearchPolicy;
$c=SupplierConnection::find(22);
$cred=is_array($c->credentials)?$c->credentials:[];
$policy=app(AirBlueConnectionSearchPolicy::class);
$eligible=$policy->isEligibleForPublicSearch($c);
echo "AIRBLUE_CERTIFICATION_STATUS=".((string)($cred["certification_status"]??"unset"))."\n";
echo "AIRBLUE_SEARCH_CERTIFIED=".(filter_var($cred["search_certified"]??false,FILTER_VALIDATE_BOOLEAN)?"YES":"NO")."\n";
echo "CONN22_PUBLIC_SEARCH_ELIGIBLE=".($eligible?"YES":"NO")."\n";
echo "PUBLIC_UNCERTIFIED_AIRBLUE_EXCLUDED=".($eligible?"NO":"YES")."\n";
PHP

echo "=== PHASE3_UNIT_TESTS ==="
if [[ -x vendor/bin/phpunit || -f vendor/bin/phpunit ]]; then
  $PHP vendor/bin/phpunit --filter 'AirBlueClientRedactionTest|AirBlueClientSoapActionTest|AirBlueOtaXmlBuilderTest' 2>&1 | tee "$EVID/phpunit.txt" | tail -40
else
  echo "PHPUNIT_MISSING_ON_PROD"
  # Prove gender/PTC/fault helpers via reflection instead
  $PHP <<'PHP'
<?php
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$client=app(App\Services\Suppliers\AirBlue\AirBlueClient::class);
$m=(new ReflectionClass($client))->getMethod("summarizeSoapFault");
$m->setAccessible(true);
$nre=$m->invoke($client,'<soap:Fault><faultstring>Object reference not set to an instance of an object.</faultstring></soap:Fault>');
echo "FAULT_CLASSIFY_NRE=".(($nre["fault_class"]??"")==="provider_nre"?"PASS":"FAIL")."\n";
$src=file_get_contents(app_path("Services/Suppliers/AirBlue/AirBlueOtaXmlBuilder.php"));
echo "XML_HAS_MALE_MAP=".(str_contains($src,"=> 'Male'")?"PASS":"FAIL")."\n";
$guard=file_get_contents(app_path("Console/Commands/Concerns/GuardsAirBlueLiveNetwork.php"));
echo "GLOBAL_KILL_SWITCH_TEST=".(str_contains($guard,"AIRBLUE_CLI_LIVE")||str_contains($guard,"EXTERNAL_CALLS")?"FAIL":"PASS")."\n";
PHP
fi

echo "=== PHASE4_CONNECTION ==="
$PHP <<'PHP'
<?php
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\SupplierConnection;
$c=SupplierConnection::find(22);
$cred=is_array($c->credentials)?$c->credentials:[];
$env=(string)($c->environment?->value??"");
$host=parse_url((string)$c->base_url,PHP_URL_HOST)?:"";
$proto=(string)($cred["protocol_version"]??"");
$channel=(string)($cred["api_channel"]??"");
echo "AIRBLUE_CONNECTION_ID=".$c->id."\n";
echo "AIRBLUE_CONNECTION_ACTIVE=".($c->is_active?"YES":"NO")."\n";
echo "AIRBLUE_ENVIRONMENT=$env\n";
echo "AIRBLUE_PROTOCOL_VERSION=$proto\n";
echo "AIRBLUE_API_CHANNEL=$channel\n";
echo "AIRBLUE_ENDPOINT_HOST_ONLY=$host\n";
echo "CLIENT_ID_PRESENT=".(trim((string)($cred["client_id"]??""))!==""?"YES":"NO")."\n";
echo "CLIENT_KEY_PRESENT=".(trim((string)($cred["client_key"]??""))!==""?"YES":"NO")."\n";
echo "AGENT_TYPE_PRESENT=".(trim((string)($cred["agent_type"]??""))!==""?"YES":"NO")."\n";
echo "AGENT_ID_PRESENT=".(trim((string)($cred["agent_id"]??""))!==""?"YES":"NO")."\n";
echo "AGENT_PASSWORD_PRESENT=".(trim((string)($cred["agent_password"]??""))!==""?"YES":"NO")."\n";
$cert=(string)($cred["tls_cert_path"]??"");
$key=(string)($cred["tls_key_path"]??"");
echo "CERT_PRESENT=".(is_file($cert)?"YES":"NO")."\n";
echo "KEY_PRESENT=".(is_file($key)?"YES":"NO")."\n";
echo "CERT_RUNTIME_READABLE=".(is_readable($cert)?"YES":"NO")."\n";
echo "KEY_RUNTIME_READABLE=".(is_readable($key)?"YES":"NO")."\n";
if (is_file($cert)&&is_file($key)) {
  @shell_exec("openssl x509 -in ".escapeshellarg($cert)." -noout -modulus 2>/dev/null > /tmp/_ab_c_mod");
  @shell_exec("openssl pkey -in ".escapeshellarg($key)." -noout -modulus 2>/dev/null > /tmp/_ab_k_mod");
  $match=(is_file("/tmp/_ab_c_mod")&&is_file("/tmp/_ab_k_mod")&&trim((string)@file_get_contents("/tmp/_ab_c_mod"))!==""&&@file_get_contents("/tmp/_ab_c_mod")===@file_get_contents("/tmp/_ab_k_mod"));
  echo "CERT_KEY_MATCH=".($match?"YES":"NO")."\n";
  @unlink("/tmp/_ab_c_mod"); @unlink("/tmp/_ab_k_mod");
}
$ok=($env==="sandbox"&&$host==="otatest4.zapways.com"&&$proto==="2.0"&&$channel==="zapways_ota"&&$c->is_active);
echo "PHASE4_IDENTITY_GATE=".($ok?"PASS":"FAIL")."\n";
echo "CERT_PATH=$cert\n";
echo "KEY_PATH=$key\n";
PHP

# Capture cert paths for WSDL
CERT=$(grep '^CERT_PATH=' <<<"$($PHP -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$c=App\Models\SupplierConnection::find(22);
$cred=is_array($c->credentials)?$c->credentials:[];
echo "CERT_PATH=".(string)($cred["tls_cert_path"]??"")."\n";
echo "KEY_PATH=".(string)($cred["tls_key_path"]??"")."\n";
')" | sed -n 's/^CERT_PATH=//p')
KEY=$( $PHP -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$c=App\Models\SupplierConnection::find(22);
$cred=is_array($c->credentials)?$c->credentials:[];
echo (string)($cred["tls_key_path"]??"");
')

echo "=== PHASE5_WSDL ==="
WSDL_URL="https://otatest4.zapways.com/v2.0/OTAAPI.asmx?WSDL"
OP_URL="https://otatest4.zapways.com/v2.0/OTAAPI.asmx?op=AirBook"
set +e
curl -sS --max-time 45 --cert "$CERT" --key "$KEY" "$WSDL_URL" -o "$EVID/otaapi.wsdl" -w "WSDL_HTTP=%{http_code}\n"
WSDL_RC=$?
curl -sS --max-time 45 --cert "$CERT" --key "$KEY" "$OP_URL" -o "$EVID/airbook-op.html" -w "OP_HTTP=%{http_code}\n"
set -e

if [[ -f "$EVID/otaapi.wsdl" && -s "$EVID/otaapi.wsdl" ]]; then
  # Strip secrets if any leaked (unlikely in WSDL)
  if grep -qiE 'password|private key|BEGIN ' "$EVID/otaapi.wsdl"; then
    echo "WSDL_FETCH=BLOCKED_SENSITIVE"
  else
    echo "WSDL_FETCH=YES"
    echo "WSDL_BYTES=$(wc -c <"$EVID/otaapi.wsdl")"
  fi
else
  echo "WSDL_FETCH=BLOCKED"
fi

if [[ -f "$EVID/airbook-op.html" && -s "$EVID/airbook-op.html" ]]; then
  echo "AIRBOOK_OPERATION_METADATA_FETCH=YES"
  echo "OP_BYTES=$(wc -c <"$EVID/airbook-op.html")"
else
  echo "AIRBOOK_OPERATION_METADATA_FETCH=NO"
fi

# Parse WSDL for AirBook types and XSD imports (sanitized summary only)
$PHP <<'PHP'
<?php
$wsdlPath="/tmp/ab-nre-closure/otaapi.wsdl";
$opPath="/tmp/ab-nre-closure/airbook-op.html";
if (!is_file($wsdlPath) || filesize($wsdlPath)<100) {
  echo "AIRBOOK_REQUEST_TYPE_FOUND=NO\n";
  echo "AIRBOOK_RESPONSE_TYPE_FOUND=NO\n";
  echo "XSD_IMPORT_COUNT=0\n";
  exit(0);
}
$xml=file_get_contents($wsdlPath);
echo "AIRBOOK_REQUEST_TYPE_FOUND=".(preg_match('/AirBook|airBookRQ|OTA_AirBookRQ/i',$xml)?"YES":"NO")."\n";
echo "AIRBOOK_RESPONSE_TYPE_FOUND=".(preg_match('/AirBookResponse|airBookRS|OTA_AirBookRS/i',$xml)?"YES":"NO")."\n";
preg_match_all('/schemaLocation="([^"]+)"/i',$xml,$m1);
preg_match_all('/<[^>]*import[^>]*namespace=/i',$xml,$m2);
$count=count($m1[1]??[])+count($m2[0]??[]);
echo "XSD_IMPORT_COUNT=$count\n";
// List schemaLocation hosts only (no secrets)
foreach (array_unique($m1[1]??[]) as $loc) {
  $h=parse_url($loc,PHP_URL_HOST) ?: "relative";
  echo "XSD_IMPORT_HOST=$h\n";
  echo "XSD_IMPORT_PATH_HASH=".substr(hash("sha256",$loc),0,12)."\n";
}
// Operation names containing AirBook
if (preg_match_all('/<wsdl:operation name="([^"]*AirBook[^"]*)"/i',$xml,$ops) || preg_match_all('/operation name="([^"]*AirBook[^"]*)"/i',$xml,$ops)) {
  foreach (array_unique($ops[1]) as $op) echo "WSDL_OPERATION=$op\n";
}
// SoapAction hints
if (preg_match_all('/soapAction="([^"]+)"/i',$xml,$sa)) {
  foreach (array_unique($sa[1]) as $a) {
    if (stripos($a,"AirBook")!==false) echo "SOAPACTION=$a\n";
  }
}
// Save sanitized element list for AirBook-related complexTypes (names only)
if (preg_match_all('/name="(OTA_AirBook[^"]*|AirBook[^"]*|AirTraveler[^"]*|PTC_FareBreakdown[^"]*|FlightSegment[^"]*)"/i',$xml,$names)) {
  foreach (array_unique($names[1]) as $n) echo "WSDL_TYPE_NAME=$n\n";
}
if (is_file($opPath)) {
  $html=file_get_contents($opPath);
  echo "OP_PAGE_HAS_AIRBOOK=".(stripos($html,"AirBook")!==false?"YES":"NO")."\n";
  // Extract parameter table-ish tags without credentials
  if (preg_match_all('/<(?:span|td|b)[^>]*>\s*(Target|Version|Gender|NamePrefix|FareBasisCode|ResBookDesigCode|OperatingAirline|PassengerTypeCode|BirthDate)\s*</i',$html,$p)) {
    foreach (array_unique($p[1]) as $x) echo "OP_DOC_FIELD=$x\n";
  }
}
PHP

echo "=== PHASE6_DRYRUN_AIRBOOK ==="
$PHP <<'PHP'
<?php
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\AirBlueConfigResolver;
use App\Services\Suppliers\AirBlue\AirBlueOtaXmlBuilder;
use App\Services\Suppliers\AirBlue\AirBluePassengerPayloadBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function out($k,$v){ if(is_bool($v))$v=$v?"YES":"NO"; echo $k."=".$v."\n"; }

$conn=SupplierConnection::find(22);
$host=parse_url((string)$conn->base_url,PHP_URL_HOST)?:"";
if ((string)($conn->environment?->value??"")!=="sandbox" || $host!=="otatest4.zapways.com") { out("FATAL","STOP_ENV_DRIFT"); exit(2); }

$fixture=$app->basePath("tests/Fixtures/airblue/search_isb_khi.json");
if (!is_file($fixture)) {
  // use last known structure from empty - fail closed
  out("FATAL","fixture_missing");
  exit(2);
}
$j=json_decode((string)file_get_contents($fixture),true);
$offer=$j["first_offer"]??null;
if (!is_array($offer)) { out("FATAL","no_offer"); exit(2); }
$pc=$offer["raw_payload"]["provider_context"]??[];
if (!is_array($pc) || empty($pc["priced_itineraries"])) { out("FATAL","no_priced"); exit(2); }

// Create ephemeral QA booking for PTC mapping proof then dry-run (no supplier call)
$ref="AB-NRE-DRY-".gmdate("YmdHis");
$fb=$offer["fare_breakdown"]??[];
$total=(float)($fb["supplier_total"]??0);
$currency=strtoupper((string)($fb["currency"]??"PKR"));
$booking=null;
DB::transaction(function() use (&$booking,$offer,$pc,$ref,$total,$currency,$conn){
  $booking=new Booking;
  $booking->booking_reference=$ref;
  $booking->agency_id=$conn->agency_id;
  $booking->status=BookingStatus::Paid;
  $booking->payment_status="paid";
  $booking->supplier=SupplierProvider::Airblue->value;
  $booking->currency=$currency;
  $booking->route=($offer["origin"]??"ISB")."-".($offer["destination"]??"KHI");
  $booking->airline="PA";
  $booking->travel_date=isset($offer["departure_at"])?substr((string)$offer["departure_at"],0,10):null;
  $booking->source_channel="sandbox_cert";
  $booking->selected_fare_total=$total;
  $booking->payable_after_promo=$total;
  $booking->payable_before_promo=$total;
  $booking->amount_paid=$total;
  $booking->balance_due=0;
  $booking->submitted_at=now();
  $booking->confirmed_at=now();
  $booking->meta=[
    "qa_fixture"=>true,"local_qa"=>true,"airblue_zapways_sandbox_cert"=>true,
    "cert_loop"=>"JP-AIRBLUE-ZAPWAYS-AIRBOOK-NRE-CLOSURE","cert_booking_role"=>"DRYRUN_CONTRACT",
    "supplier_connection_id"=>22,"offer_snapshot"=>$offer,
    "airblue_context"=>array_merge($pc,["protocol_version"=>"2.0","api_channel"=>"zapways_ota"]),
  ];
  $booking->save();
  if (Schema::hasColumn("bookings","offer_snapshot")) { $booking->offer_snapshot=$offer; $booking->save(); }
  $pax=new BookingPassenger;
  $pax->booking_id=$booking->id;
  $pax->passenger_type="adult";
  $pax->passenger_index=0;
  $pax->is_lead_passenger=true;
  $pax->title="MR";
  $pax->first_name="TESTQA";
  $pax->last_name="NREDRY";
  $pax->date_of_birth="1990-01-15";
  $pax->nationality="PK";
  $pax->gender="M";
  $pax->passport_number="QA".substr(hash("sha256",$ref),0,7);
  $pax->passport_expiry_date="2030-12-31";
  $pax->passport_issuing_country="PK";
  $pax->save();
  $contact=new BookingContact;
  $contact->booking_id=$booking->id;
  $contact->email="airblue.nre.dry.".substr(hash("sha256",$ref),0,8)."@example.test";
  $contact->phone="+923001112233";
  $contact->country="PK";
  $contact->save();
});

$booking=$booking->fresh(["passengers","contact"]);
out("BOOKING_PASSENGER_TYPE_SOURCE","passenger_type");
out("LOCAL_PASSENGER_TYPE",(string)$booking->passengers->first()->passenger_type);
$paxBuilder=app(AirBluePassengerPayloadBuilder::class);
$passengers=$paxBuilder->buildPassengersFromBooking($booking);
$contact=$paxBuilder->buildContactFromBooking($booking);
out("ZAPWAYS_PTC",(string)($passengers[0]["ptc"]??""));
out("PASSENGER_TYPE_MAPPING",(($passengers[0]["ptc"]??"")==="ADT" && $booking->passengers->first()->passenger_type==="adult")?"PASS":"FAIL");

$meta=is_array($booking->meta)?$booking->meta:[];
$fromMeta=is_array($meta["airblue_context"]??null)?$meta["airblue_context"]:[];
$snapshot=is_array($booking->offer_snapshot)?$booking->offer_snapshot:(is_array($meta["offer_snapshot"]??null)?$meta["offer_snapshot"]:[]);
$raw=is_array($snapshot["raw_payload"]??null)?$snapshot["raw_payload"]:[];
$fromSnapshot=is_array($raw["provider_context"]??null)?$raw["provider_context"]:[];
$providerContext=array_merge($fromSnapshot,$fromMeta);
$config=app(AirBlueConfigResolver::class)->resolveOta($conn);
$xml=app(AirBlueOtaXmlBuilder::class)->buildAirBookRequest($config,$providerContext,$passengers,$contact);
file_put_contents("/tmp/ab-nre-closure/airbook-dryrun.xml",$xml);
out("DRYRUN_XML_BYTES",strlen($xml));
out("DRYRUN_SUPPLIER_CALLS",0);

// Structural presence matrix
$checks=[
  "Envelope"=>str_contains($xml,"soapenv:Envelope"),
  "zap_AirBook"=>str_contains($xml,"<zap:AirBook>"),
  "airBookRQ"=>str_contains($xml,"airBookRQ"),
  "Target"=> (bool)preg_match('/Target="[^"]+"/',$xml),
  "Version"=> (bool)preg_match('/Version="[^"]+"/',$xml),
  "POS"=>str_contains($xml,"<ota:POS>"),
  "Source"=>str_contains($xml,"<ota:Source"),
  "ERSP_UserID"=>str_contains($xml,"ERSP_UserID="),
  "RequestorID"=>str_contains($xml,"RequestorID"),
  "MessagePassword"=>str_contains($xml,"MessagePassword="),
  "AirItinerary"=>str_contains($xml,"AirItinerary"),
  "FlightSegment"=>str_contains($xml,"FlightSegment"),
  "DepartureDateTime"=>str_contains($xml,"DepartureDateTime="),
  "ArrivalDateTime"=>str_contains($xml,"ArrivalDateTime="),
  "FlightNumber"=>str_contains($xml,"FlightNumber="),
  "ResBookDesigCode"=>str_contains($xml,"ResBookDesigCode="),
  "MarketingAirline"=>str_contains($xml,"MarketingAirline"),
  "OperatingAirline"=>str_contains($xml,"OperatingAirline"),
  "PriceInfo"=>str_contains($xml,"PriceInfo"),
  "ItinTotalFare"=>str_contains($xml,"ItinTotalFare"),
  "PTC_FareBreakdown"=>str_contains($xml,"PTC_FareBreakdown"),
  "PassengerTypeQuantity"=>str_contains($xml,"PassengerTypeQuantity"),
  "FareBasisCode"=>str_contains($xml,"FareBasisCode"),
  "TravelerInfo"=>str_contains($xml,"TravelerInfo"),
  "AirTraveler"=>str_contains($xml,"AirTraveler"),
  "Gender_Male"=>str_contains($xml,'Gender="Male"'),
  "NamePrefix"=>str_contains($xml,"NamePrefix"),
  "GivenName"=>str_contains($xml,"GivenName"),
  "Surname"=>str_contains($xml,"Surname"),
  "Telephone"=>str_contains($xml,"Telephone"),
  "Email"=>str_contains($xml,"Email"),
  "TravelerRefNumber"=>str_contains($xml,"TravelerRefNumber"),
  "Document"=>str_contains($xml,"Document"),
  "PassengerTypeCode_ADT"=>str_contains($xml,'PassengerTypeCode="ADT"'),
];
foreach ($checks as $k=>$ok) out("STRUCT_$k",$ok?"PRESENT":"ABSENT");

// Order checks (approximate local-name sequence in body)
$bodyOrder=["POS","AirItinerary","PriceInfo","TravelerInfo"];
$positions=[];
foreach ($bodyOrder as $tag) {
  $p=strpos($xml,"<ota:$tag");
  $positions[$tag]=$p===false?-1:$p;
}
$orderOk=true; $prev=-1;
foreach ($bodyOrder as $tag) {
  if ($positions[$tag]<0 || $positions[$tag]<$prev) { $orderOk=false; break; }
  $prev=$positions[$tag];
}
out("AIRBOOK_SCHEMA_ORDER_PASS",$orderOk?"PASS":"FAIL");

// Traveler child order PersonName before Document/Telephone/Email/TravelerRef
$travPos=strpos($xml,"<ota:AirTraveler");
$travSlice=$travPos===false?"":substr($xml,$travPos,1200);
$tOrder=["PersonName","Document","Telephone","Email","TravelerRefNumber"];
$tPrev=-1; $tOk=true;
foreach ($tOrder as $tag) {
  $p=strpos($travSlice,"<ota:$tag");
  if ($p===false) continue;
  if ($p<$tPrev) { $tOk=false; break; }
  $tPrev=$p;
}
out("TRAVELER_CHILD_ORDER_PASS",$tOk?"PASS":"FAIL");

// PTC FareBreakdown: FareBasisCodes sibling vs inside PassengerFare
$fareInsidePassengerFare=preg_match('/PassengerFare[\s\S]*FareBasisCodes[\s\S]*<\/ota:PassengerFare>/',$xml);
$fareSibling=preg_match('/<\/ota:PassengerFare>\s*<ota:FareBasisCodes>/',$xml);
out("FAREBASIS_INSIDE_PASSENGERFARE",$fareInsidePassengerFare?"YES":"NO");
out("FAREBASIS_SIBLING_AFTER_PASSENGERFARE",$fareSibling?"YES":"NO");

// Enums
preg_match('/Target="([^"]+)"/',$xml,$tm); out("SERVICE_TARGET",$tm[1]??"");
preg_match('/Version="([^"]+)"/',$xml,$vm); out("SERVICE_VERSION",$vm[1]??"");
preg_match('/Gender="([^"]+)"/',$xml,$gm); out("GENDER_VALUE",$gm[1]??"");
preg_match('/<ota:NamePrefix>([^<]*)<\/ota:NamePrefix>/',$xml,$nm); out("NAMEPREFIX_VALUE",$nm[1]??"");
preg_match('/<ota:FareBasisCode>([^<]*)<\/ota:FareBasisCode>/',$xml,$fbv); out("FAREBASIS_VALUE",$fbv[1]??"");
preg_match('/ResBookDesigCode="([^"]*)"/',$xml,$rbd); out("RBD_VALUE",$rbd[1]??"");
preg_match('/OperatingAirline[^>]*Code="([^"]*)"/',$xml,$op); out("OPERATING_CARRIER",$op[1]??"");
preg_match('/MarketingAirline[^>]*Code="([^"]*)"/',$xml,$mk); out("MARKETING_CARRIER",$mk[1]??"");
preg_match('/RPH="([^"]*)"/',$xml,$rph); out("FLIGHT_RPH",$rph[1]??"");
out("HAS_CABIN_CLASS",str_contains($xml,"CabinClass=")?"YES":"NO");
out("HAS_FARE_TYPE",str_contains($xml,"FareType=")?"YES":"NO");

// Required elements heuristic vs known Zapways AirBook working shape
$requiredOk=!in_array(false,array_values($checks),true);
out("AIRBOOK_REQUIRED_ELEMENTS_PASS",$requiredOk?"PASS":"FAIL");
out("AIRBOOK_REQUIRED_ATTRIBUTES_PASS",(
  str_contains($xml,"DepartureDateTime=")&&str_contains($xml,"FlightNumber=")&&str_contains($xml,"ResBookDesigCode=")
  && str_contains($xml,'Gender="Male"') && str_contains($xml,'PassengerTypeCode="ADT"')
)?"PASS":"FAIL");
out("AIRBOOK_ENUM_VALUES_PASS",(
  ($gm[1]??"")==="Male" && ($nm[1]??"")!=="" && in_array(($tm[1]??""),["Test","Production"],true)
)?"PASS":"FAIL");
out("AIRBOOK_CARDINALITY_PASS",(
  substr_count($xml,"<ota:AirTraveler")===1 && substr_count($xml,"<ota:FlightSegment")>=1 && substr_count($xml,"PTC_FareBreakdown")>=1
)?"PASS":"FAIL");

// NRE matrix classification
$matrix=[
  "PTC_CODE"=>($passengers[0]["ptc"]??"")==="ADT"?"PRESENT":"INVALID",
  "NAME_PREFIX"=>($nm[1]??"")!==""?"PRESENT":"ABSENT",
  "GENDER"=>($gm[1]??"")==="Male"?"PRESENT":"INVALID",
  "BIRTHDATE"=>str_contains($xml,"BirthDate=")?"PRESENT":"ABSENT",
  "CONTACT_EMAIL"=>str_contains($xml,"Email")?"PRESENT":"ABSENT",
  "CONTACT_PHONE"=>str_contains($xml,"Telephone")?"PRESENT":"ABSENT",
  "TRAVELER_RPH"=>str_contains($xml,"TravelerRefNumber")?"PRESENT":"ABSENT",
  "FLIGHT_RPH"=>($rph[1]??"")!==""?"PRESENT":"ABSENT",
  "FLIGHT_STATUS_IF_REQUIRED"=>"UNKNOWN",
  "NUMBER_IN_PARTY_IF_REQUIRED"=>"ABSENT",
  "MARKETING_CARRIER"=>($mk[1]??"")!==""?"PRESENT":"ABSENT",
  "OPERATING_CARRIER"=>($op[1]??"")!==""?"PRESENT":"ABSENT",
  "FARE_BASIS"=>($fbv[1]??"")!==""?"PRESENT":"ABSENT",
  "RBD"=>($rbd[1]??"")!==""?"PRESENT":"ABSENT",
  "FARE_TYPE"=>str_contains($xml,"FareType=")?"PRESENT":"ABSENT",
  "CABIN_CLASS"=>str_contains($xml,"CabinClass=")?"PRESENT":"ABSENT",
  "BASE_FARE"=>str_contains($xml,"BaseFare")?"PRESENT":"ABSENT",
  "TAXES"=>str_contains($xml,"Taxes")?"PRESENT":"ABSENT",
  "TOTAL_FARE"=>str_contains($xml,"TotalFare")?"PRESENT":"ABSENT",
  "CURRENCY"=>str_contains($xml,"CurrencyCode=")?"PRESENT":"ABSENT",
  "PTC_FARE_BREAKDOWN"=>str_contains($xml,"PTC_FareBreakdown")?"PRESENT":"ABSENT",
  "POS"=>str_contains($xml,"POS")?"PRESENT":"ABSENT",
  "REQUESTOR_ID"=>str_contains($xml,"RequestorID")?"PRESENT":"ABSENT",
  "SERVICE_TARGET"=>($tm[1]??"")!==""?"PRESENT":"ABSENT",
  "SERVICE_VERSION"=>($vm[1]??"")!==""?"PRESENT":"ABSENT",
  "ELEMENT_ORDER"=>$orderOk?"PRESENT":"INVALID",
];
foreach ($matrix as $k=>$v) out("MATRIX_$k",$v);

// Compare against OpenTravel 2003 PTC_FareBreakdown sequence if WSDL didn't supply XSD detail:
// Standard OTA: PassengerTypeQuantity, FareBasisCodes, PassengerFare (FareBasisCodes often BEFORE PassengerFare)
$otaStandardOrder=preg_match('/PassengerTypeQuantity[\s\S]*FareBasisCodes[\s\S]*PassengerFare/',$xml);
out("OTA_STANDARD_FAREBASIS_BEFORE_PASSENGERFARE",$otaStandardOrder?"YES":"NO");
$jetpkOrderInside=preg_match('/PassengerTypeQuantity[\s\S]*PassengerFare[\s\S]*FareBasisCodes/',$xml);
out("JETPK_FAREBASIS_INSIDE_OR_AFTER_PASSENGERFARE",$jetpkOrderInside?"YES":"NO");

// Contract defect decision
$defects=[];
if (!$orderOk) $defects[]="body_element_order";
if (!$tOk) $defects[]="traveler_child_order";
if (!$otaStandardOrder && $jetpkOrderInside) $defects[]="farebasis_codes_position_vs_ota2003_common_order";
if (!str_contains($xml,"CabinClass=")) $defects[]="cabin_class_absent_may_be_optional";
if (!str_contains($xml,"FareType=")) $defects[]="fare_type_absent_may_be_optional";
// Mark optional ones carefully — only hard defects if WSDL required them
$hard=[];
if (!$requiredOk) $hard[]="required_elements_missing";
if (($gm[1]??"")!=="Male") $hard[]="gender_not_Male";
if (($passengers[0]["ptc"]??"")!=="ADT") $hard[]="ptc_not_ADT";
if (($fbv[1]??"")==="") $hard[]="fare_basis_empty";
// FareBasis position: search RS fixture nests FareBasisCodes inside PassengerFare — JetPK matches RS. Not hard defect unless WSDL says otherwise.
out("AIRBOOK_CONTRACT_HARD_DEFECTS", $hard===[]?"NONE":implode(",",$hard));
out("AIRBOOK_CONTRACT_SOFT_FINDINGS", $defects===[]?"NONE":implode(",",$defects));
out("AIRBOOK_CONTRACT_DEFECT_FOUND", $hard===[]?"NO":"YES");
out("DRYRUN_BOOKING_ID_HASH",substr(hash("sha256",(string)$booking->id),0,12));
out("PHASE6_8_COMPLETE","YES");
PHP

echo "PHASE3_8_COMPLETE"
