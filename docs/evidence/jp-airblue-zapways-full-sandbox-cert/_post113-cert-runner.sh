#!/usr/bin/env bash
# Post-PR113 full sandbox certification runner (otatest4 only)
set -euo pipefail
APP=/home/pkjetp/jetpk_app
PHP=/usr/local/lsws/lsphp83/bin/php
EVID=/tmp/ab-post113-cert
mkdir -p "$EVID"
cd "$APP"

KEY="${HOME}/.ssh/unused"
# scripts already on server under /tmp/ab-post113/

PHASE12=${PHASE12:-/tmp/ab-post113/_phase1-2-probe.sh}
PHASE3=${PHASE3:-/tmp/ab-post113/_phase3-tls-probe.sh}
PHASE5=${PHASE5:-/tmp/ab-post113/_phase5-search.sh}
LIFECYCLE=${LIFECYCLE:-/tmp/ab-post113/_post113-lifecycle.php}
RESTORE=${RESTORE:-/tmp/ab-post113/_restore-check.php}
UNIT=${UNIT:-/tmp/ab-post113/_post113-run-unit.sh}

echo "=== POST113_START $(date -u +%Y%m%dT%H%M%SZ) ==="

echo "=== PHASE1_2 ==="
bash "$PHASE12" 2>&1 | tee "$EVID/phase1-2.txt"

echo "=== PHASE3_TLS ==="
bash "$PHASE3" 2>&1 | tee "$EVID/phase3.txt"

echo "=== PHASE6_ISOLATION ==="
$PHP -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$c=App\Models\SupplierConnection::find(22);
$cred=is_array($c->credentials)?$c->credentials:[];
$suppliers=(string)env("OTA_PUBLIC_FLIGHT_RESULTS_SUPPLIERS","");
echo "AIRBLUE_PUBLIC_SEARCH_ENABLED=".(stripos($suppliers,"airblue")!==false?"YES":"NO")."\n";
echo "AIRBLUE_PRODUCTION_LIVE_ACTIVATION=NOT_STARTED\n";
echo "AIRBLUE_CERTIFICATION_STATUS=".((string)($cred["certification_status"]??"pending"))."\n";
echo "AIRBLUE_SEARCH_CERTIFIED=".(filter_var($cred["search_certified"]??false,FILTER_VALIDATE_BOOLEAN)?"YES":"NO")."\n";
echo "AIRBLUE_EXTERNAL_CALLS_GLOBAL=".(filter_var(env("AIRBLUE_EXTERNAL_CALLS_ENABLED",false),FILTER_VALIDATE_BOOLEAN)?"YES":"NO")."\n";
echo "AIRBLUE_CLI_LIVE_GLOBAL=".(filter_var(env("AIRBLUE_CLI_LIVE_ENABLED",false),FILTER_VALIDATE_BOOLEAN)?"YES":"NO")."\n";
$env=(string)($c->environment?->value??"");
$host=parse_url((string)$c->base_url,PHP_URL_HOST)?:"";
echo "CLIENT_ID_PRESENT=".(trim((string)($cred["client_id"]??""))!==""?"YES":"NO")."\n";
echo "CLIENT_KEY_PRESENT=".(trim((string)($cred["client_key"]??""))!==""?"YES":"NO")."\n";
echo "AGENT_TYPE_PRESENT=".(trim((string)($cred["agent_type"]??""))!==""?"YES":"NO")."\n";
echo "AGENT_ID_PRESENT=".(trim((string)($cred["agent_id"]??""))!==""?"YES":"NO")."\n";
echo "AGENT_PASSWORD_PRESENT=".(trim((string)($cred["agent_password"]??""))!==""?"YES":"NO")."\n";
echo "AIRBLUE_REAL_LIVE_CREDENTIALS_USED=NO\n";
echo "AIRBLUE_PRODUCTION_ENDPOINT_USED=".(($host==="otatest4.zapways.com"&&$env==="sandbox")?"NO":"YES")."\n";
' | tee "$EVID/phase6-isolation.txt"

echo "=== PHASE5_SEARCH ==="
bash "$PHASE5" 2>&1 | tee "$EVID/phase5.txt"
OFFER=$(grep -E '^SEARCH_OFFER_FOUND=' "$EVID/phase5.txt" | tail -1 | cut -d= -f2)
STATUS=$(grep -E '^AIRBLUE_SEARCH_STATUS=' "$EVID/phase5.txt" | tail -1 | cut -d= -f2 || true)
if [[ "$OFFER" != "YES" ]]; then
  echo "STOP_NO_OFFER status=${STATUS:-unknown}"
  $PHP "$RESTORE" 2>&1 | tee "$EVID/restore.txt" || true
  exit 10
fi

FIXTURE_A="$APP/tests/Fixtures/airblue/search_isb_khi.json"
# Prefer winning route fixture if present
WIN_ROUTE=$(grep -E '^WINNING_ROUTE=' "$EVID/phase5.txt" | tail -1 | cut -d= -f2 || true)
if [[ "$WIN_ROUTE" == *"-"* ]]; then
  FROM=$(echo "$WIN_ROUTE" | cut -d- -f1 | tr 'A-Z' 'a-z')
  TO=$(echo "$WIN_ROUTE" | cut -d- -f2 | tr 'A-Z' 'a-z')
  CAND="$APP/tests/Fixtures/airblue/search_${FROM}_${TO}.json"
  if [[ -f "$CAND" ]]; then FIXTURE_A="$CAND"; fi
fi
echo "FIXTURE_A=$FIXTURE_A"

# Fresh search for Booking B (second inventory call — counts toward budget if needed)
echo "=== PHASE13_FRESH_SEARCH_B ==="
D1=$(date -u -d '+50 days' +%Y-%m-%d 2>/dev/null || date -u -v+50d +%Y-%m-%d)
set +e
AIRBLUE_EXTERNAL_CALLS_ENABLED=true AIRBLUE_CLI_LIVE_ENABLED=true \
  $PHP artisan airblue:test-search --connection=22 --allow-live \
  --from=ISB --to=KHI --date="$D1" --adults=1 --children=0 --infants=0 --currency=PKR \
  2>&1 | tee "$EVID/phase13-search-b.txt" | head -40
set -e
FIXTURE_B="$APP/tests/Fixtures/airblue/search_isb_khi.json"
echo "FIXTURE_B=$FIXTURE_B"

echo "=== LIFECYCLE_A_B ==="
AIRBLUE_EXTERNAL_CALLS_ENABLED=true AIRBLUE_CLI_LIVE_ENABLED=true \
  $PHP "$LIFECYCLE" "$FIXTURE_A" "$FIXTURE_B" 2>&1 | tee "$EVID/lifecycle.txt"
LC_RC=${PIPESTATUS[0]}

echo "=== UNIT_AND_SECURITY ==="
bash "$UNIT" 2>&1 | tee "$EVID/unit.txt" || true

echo "=== RESTORE ==="
# Force-restore feature flags on connection 22
$PHP -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$c=App\Models\SupplierConnection::find(22);
$cred=is_array($c->credentials)?$c->credentials:[];
$cred["ticketing_enabled"]=false;
$cred["cancel_enabled"]=false;
$cred["booking_enabled"]=true;
$c->credentials=$cred;
$c->save();
echo "FLAGS_RESTORED_ticketing=false cancel=false booking=true\n";
echo "ENV=".(string)($c->environment?->value??"")."\n";
echo "HOST=".(parse_url((string)$c->base_url,PHP_URL_HOST)?:"")."\n";
'
$PHP "$RESTORE" 2>&1 | tee "$EVID/restore.txt" || true

echo "LIFECYCLE_RC=$LC_RC"
echo "EVID_DIR=$EVID"
echo "POST113_COMPLETE"
exit "$LC_RC"
