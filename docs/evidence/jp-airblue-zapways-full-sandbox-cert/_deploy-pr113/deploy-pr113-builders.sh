#!/usr/bin/env bash
# JP-AIRBLUE-ZAPWAYS-FULL-SANDBOX-CERT — scoped PR #113 builder deploy ONLY
# Authorized main SHA: c8dbaedbb0d2ba3f8f66337a427d6c706f376d8e
set -euo pipefail

APP=/home/pkjetp/jetpk_app
PHP=/usr/local/lsws/lsphp83/bin/php
AUTHORIZED_SHA=c8dbaedbb0d2ba3f8f66337a427d6c706f376d8e
STAGE=/tmp/airblue-pr113-stage
TS=$(date -u +%Y%m%dT%H%M%SZ)
BK=/home/pkjetp/backups/airblue-pr113-pre-deploy-$TS

RUNTIME_FILES=(
  app/Services/Suppliers/AirBlue/AirBlueOtaXmlBuilder.php
  app/Services/Suppliers/AirBlue/AirBluePassengerPayloadBuilder.php
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

KILL_IS_TRUE=$($PHP -r '
require "'"$APP"'/vendor/autoload.php";
$app=require "'"$APP"'/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$v=env("AIRBLUE_EXTERNAL_CALLS_ENABLED");
echo (filter_var($v, FILTER_VALIDATE_BOOLEAN)===true)?"YES":"NO";
')
echo "AIRBLUE_EXTERNAL_CALLS_ENV_IS_TRUE=$KILL_IS_TRUE"
if [[ "$KILL_IS_TRUE" == "YES" ]]; then
  echo "STOP_KILL_SWITCH_TRUE"
  exit 2
fi

mkdir -p "$BK/sha"

echo "=== PREDEPLOY SHA256 ==="
for rel in "${RUNTIME_FILES[@]}"; do
  src="$APP/$rel"
  mkdir -p "$BK/$(dirname "$rel")"
  cp -a "$src" "$BK/$rel"
  pre=$(sha256sum "$src" | awk '{print $1}')
  echo "PREDEPLOY_$(echo "$rel" | tr '/.' '__')=$pre"
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

echo "=== POSTDEPLOY PARITY vs STAGE (main blobs) ==="
FAIL=0
for rel in "${RUNTIME_FILES[@]}"; do
  a=$(sha256sum "$STAGE/$rel" | awk '{print $1}')
  b=$(sha256sum "$APP/$rel" | awk '{print $1}')
  echo "MAIN_$rel=$a"
  echo "POSTDEPLOY_$rel=$b"
  if [[ "$a" != "$b" ]]; then
    echo "SHA_MISMATCH=$rel"
    FAIL=$((FAIL+1))
  else
    echo "PARITY_OK=$rel"
  fi
done
if [[ "$FAIL" -ne 0 ]]; then
  echo "XML_BUILDER_PARITY=FAIL"
  echo "PASSENGER_BUILDER_PARITY=FAIL"
  echo "RESTORING_FROM_BACKUP"
  for rel in "${RUNTIME_FILES[@]}"; do
    install -o pkjetp -g pkjetp -m "$(stat -c '%a' "$APP/$rel")" "$BK/$rel" "$APP/$rel"
  done
  exit 4
fi

XML_A=$(sha256sum "$STAGE/app/Services/Suppliers/AirBlue/AirBlueOtaXmlBuilder.php" | awk '{print $1}')
XML_B=$(sha256sum "$APP/app/Services/Suppliers/AirBlue/AirBlueOtaXmlBuilder.php" | awk '{print $1}')
PAX_A=$(sha256sum "$STAGE/app/Services/Suppliers/AirBlue/AirBluePassengerPayloadBuilder.php" | awk '{print $1}')
PAX_B=$(sha256sum "$APP/app/Services/Suppliers/AirBlue/AirBluePassengerPayloadBuilder.php" | awk '{print $1}')
echo "MAIN_XML_BUILDER_SHA256=$XML_A"
echo "POSTDEPLOY_XML_BUILDER_SHA256=$XML_B"
echo "XML_BUILDER_PARITY=$([ "$XML_A" = "$XML_B" ] && echo PASS || echo FAIL)"
echo "MAIN_PASSENGER_BUILDER_SHA256=$PAX_A"
echo "POSTDEPLOY_PASSENGER_BUILDER_SHA256=$PAX_B"
echo "PASSENGER_BUILDER_PARITY=$([ "$PAX_A" = "$PAX_B" ] && echo PASS || echo FAIL)"

echo "=== PHP -l ==="
SYNTAX_FAIL=0
for rel in "${RUNTIME_FILES[@]}"; do
  if ! $PHP -l "$APP/$rel"; then
    SYNTAX_FAIL=$((SYNTAX_FAIL+1))
  fi
done
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
'

echo "=== OPTIMIZE CLEAR ==="
cd "$APP"
$PHP artisan optimize:clear >/tmp/airblue-pr113-optimize-clear.txt 2>&1 || true
tail -n 20 /tmp/airblue-pr113-optimize-clear.txt

echo "PR113_DEPLOYED=YES"
echo "BACKUP_PATH=$BK"
echo "DONE"
