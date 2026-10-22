#!/usr/bin/env bash
# JP-AIRBLUE-ZAPWAYS-AIRBOOK-NRE-CLOSURE — scoped PR #114 deploy
# Authorized main SHA: 1f37e0197f296398e4b0c7d687e9d60b2dfea3c2
set -euo pipefail

APP=/home/pkjetp/jetpk_app
PHP=/usr/local/lsws/lsphp83/bin/php
AUTHORIZED_SHA=1f37e0197f296398e4b0c7d687e9d60b2dfea3c2
STAGE=/tmp/airblue-pr114-stage
TS=$(date -u +%Y%m%dT%H%M%SZ)
BK=/home/pkjetp/backups/airblue-pr114-pre-deploy-$TS

RUNTIME_FILES=(
  app/Console/Commands/Concerns/GuardsAirBlueLiveNetwork.php
  app/Services/Suppliers/AirBlue/AirBlueBookingService.php
  app/Services/Suppliers/AirBlue/AirBlueClient.php
  app/Services/Suppliers/AirBlue/AirBluePassengerPayloadBuilder.php
  config/suppliers.php
)

echo "AUTHORIZED_SHA=$AUTHORIZED_SHA"
echo "BACKUP_PATH=$BK"
echo "STAGE=$STAGE"

if [[ ! -d "$STAGE" ]]; then
  echo "STAGE_MISSING"
  exit 1
fi

for rel in "${RUNTIME_FILES[@]}"; do
  if [[ ! -f "$STAGE/$rel" ]]; then
    echo "MISSING_STAGE=$rel"
    exit 3
  fi
done

mkdir -p "$BK/sha"

echo "=== PREDEPLOY SHA256 ==="
for rel in "${RUNTIME_FILES[@]}"; do
  src="$APP/$rel"
  mkdir -p "$BK/$(dirname "$rel")"
  cp -a "$src" "$BK/$rel"
  pre=$(sha256sum "$src" | awk '{print $1}')
  echo "PREDEPLOY_$rel=$pre"
  echo "$pre" > "$BK/sha/$(echo "$rel" | tr '/' '_').pre.sha256"
done

echo "=== DEPLOY ==="
for rel in "${RUNTIME_FILES[@]}"; do
  src="$STAGE/$rel"
  dst="$APP/$rel"
  mode=$(stat -c '%a' "$dst")
  install -o pkjetp -g pkjetp -m "$mode" "$src" "$dst"
  echo "DEPLOYED=$rel mode=$mode"
done

echo "=== PARITY ==="
FAIL=0
declare -A LABELS=(
  [app/Console/Commands/Concerns/GuardsAirBlueLiveNetwork.php]=GUARD
  [app/Services/Suppliers/AirBlue/AirBlueBookingService.php]=BOOKING_SERVICE
  [app/Services/Suppliers/AirBlue/AirBlueClient.php]=CLIENT
  [app/Services/Suppliers/AirBlue/AirBluePassengerPayloadBuilder.php]=PASSENGER_BUILDER
  [config/suppliers.php]=SUPPLIERS_CONFIG
)
for rel in "${RUNTIME_FILES[@]}"; do
  a=$(sha256sum "$STAGE/$rel" | awk '{print $1}')
  b=$(sha256sum "$APP/$rel" | awk '{print $1}')
  label="${LABELS[$rel]}"
  echo "MAIN_${label}_SHA256=$a"
  echo "POSTDEPLOY_${label}_SHA256=$b"
  if [[ "$a" != "$b" ]]; then
    echo "${label}_PARITY=FAIL"
    FAIL=$((FAIL+1))
  else
    echo "${label}_PARITY=PASS"
  fi
done

if [[ "$FAIL" -ne 0 ]]; then
  echo "RESTORING_FROM_BACKUP"
  for rel in "${RUNTIME_FILES[@]}"; do
    install -o pkjetp -g pkjetp -m "$(stat -c '%a' "$APP/$rel")" "$BK/$rel" "$APP/$rel"
  done
  echo "PR114_DEPLOYED=NO"
  exit 4
fi

echo "=== PHP -l ==="
SYNTAX_FAIL=0
for rel in "${RUNTIME_FILES[@]}"; do
  case "$rel" in
    *.php)
      if ! $PHP -l "$APP/$rel"; then
        SYNTAX_FAIL=$((SYNTAX_FAIL+1))
      fi
      ;;
  esac
done
# config/suppliers.php is PHP
if ! $PHP -l "$APP/config/suppliers.php"; then
  SYNTAX_FAIL=$((SYNTAX_FAIL+1))
fi
if [[ "$SYNTAX_FAIL" -ne 0 ]]; then
  echo "PHP_SYNTAX=FAIL"
  exit 5
fi
echo "PHP_SYNTAX=PASS"

echo "=== BOOTSTRAP ==="
$PHP -r '
require "'"$APP"'/vendor/autoload.php";
$app=require "'"$APP"'/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo "LARAVEL_BOOTSTRAP=PASS\n";
$ext = config("suppliers.airblue.external_calls_enabled", "UNSET");
echo "CONFIG_EXTERNAL_CALLS_KEY=".(array_key_exists("external_calls_enabled", config("suppliers.airblue", [])) ? "PRESENT" : "ABSENT")."\n";
'

echo "=== OPTIMIZE CLEAR ==="
cd "$APP"
$PHP artisan optimize:clear >/tmp/airblue-pr114-optimize-clear.txt 2>&1 || true
tail -n 15 /tmp/airblue-pr114-optimize-clear.txt

echo "PR114_DEPLOYED=YES"
echo "BACKUP_PATH=$BK"
echo "DONE"
