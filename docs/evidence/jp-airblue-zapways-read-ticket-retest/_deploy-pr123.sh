#!/usr/bin/env bash
set -euo pipefail
APP=/home/pkjetp/jetpk_app
TS=$(date -u +%Y%m%dT%H%M%SZ)
BK=/home/pkjetp/backups/airblue-pr123-pre-$TS
STAGE=/tmp/airblue-pr123-stage
PHP=/usr/local/lsws/lsphp83/bin/php

declare -A EXPECT=(
  [suppliers.php]=56071fccdee79e210bd1df323e22e23b3c01bedbc9f5a084cdff959fa000fc36
  [AirBlueOtaXmlBuilder.php]=69f2c96c49d1ed3027b0b2d0779dd137e43c164dcc896cd2b8b7bc502cbc6759
  [AirBlueRetrieveService.php]=eb517d313eb60872e46e945c34bdc5a59ac252d56460013a2d1ade8376720844
  [AirBlueTicketingService.php]=06c2537ec067ef801aeebed266995aeb1efc9a62fd39abe61c710714a23f37e4
)

declare -A TARGET=(
  [suppliers.php]=config/suppliers.php
  [AirBlueOtaXmlBuilder.php]=app/Services/Suppliers/AirBlue/AirBlueOtaXmlBuilder.php
  [AirBlueRetrieveService.php]=app/Services/Suppliers/AirBlue/AirBlueRetrieveService.php
  [AirBlueTicketingService.php]=app/Services/Suppliers/AirBlue/AirBlueTicketingService.php
)

echo "MAIN_SHA=d4258db6baba3894feca412bbff5fe80a7c6d1b1"
echo "DEPLOY_TS=$TS"
echo "BACKUP=$BK"

mkdir -p "$BK/config" "$BK/app/Services/Suppliers/AirBlue"

for name in suppliers.php AirBlueOtaXmlBuilder.php AirBlueRetrieveService.php AirBlueTicketingService.php; do
  stage="$STAGE/$name"
  rel="${TARGET[$name]}"
  tgt="$APP/$rel"
  expect="${EXPECT[$name]}"
  stage_hash=$(sha256sum "$stage" | awk '{print $1}')
  echo "STAGE_${name}_SHA256=$stage_hash"
  if [ "$stage_hash" != "$expect" ]; then
    echo "FATAL=STAGE_HASH_MISMATCH name=$name"
    exit 2
  fi
  mkdir -p "$(dirname "$BK/$rel")"
  cp -a "$tgt" "$BK/$rel"
  pre=$(sha256sum "$BK/$rel" | awk '{print $1}')
  echo "PREDEPLOY_${name}_SHA256=$pre"
  install -o pkjetp -g pkjetp -m 644 "$stage" "$tgt"
  post=$(sha256sum "$tgt" | awk '{print $1}')
  echo "POSTDEPLOY_${name}_SHA256=$post"
  if [ "$post" != "$expect" ]; then
    echo "PARITY_${name}=FAIL"
    exit 3
  fi
  echo "PARITY_${name}=PASS"
  if [[ "$name" == *.php && "$name" != suppliers.php ]]; then
    $PHP -l "$tgt"
  elif [[ "$name" == suppliers.php ]]; then
    $PHP -l "$tgt"
  fi
done

echo "CONFIG_PARITY=PASS"
echo "BUILDER_PARITY=PASS"
echo "RETRIEVE_SERVICE_PARITY=PASS"
echo "TICKETING_SERVICE_PARITY=PASS"
echo "PHP_SYNTAX=PASS"

$PHP -r '
require "/home/pkjetp/jetpk_app/vendor/autoload.php";
$app = require "/home/pkjetp/jetpk_app/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo "LARAVEL_BOOTSTRAP=PASS\n";
'

# Config cache safety
CFG_CACHE="$APP/bootstrap/cache/config.php"
if [ -f "$CFG_CACHE" ]; then
  echo "CONFIG_CACHE_PRE=YES"
  if grep -E 'otatest4\.zapways\.com/Read|ota4\.zapways\.com/Read' "$CFG_CACHE" >/dev/null 2>&1; then
    echo "CONFIG_REFRESH_REQUIRED=YES"
    echo "CONFIG_REFRESH_ACTION=config:cache"
    cd "$APP"
    $PHP artisan config:cache
    if grep -E 'otatest4\.zapways\.com/Read|ota4\.zapways\.com/Read' "$APP/bootstrap/cache/config.php" >/dev/null 2>&1; then
      echo "CONFIG_REFRESH_VERIFY=STALE_STILL_PRESENT"
      exit 4
    fi
    if grep -F 'ota.qa.zapways.com/Read' "$APP/bootstrap/cache/config.php" >/dev/null 2>&1; then
      echo "CONFIG_REFRESH_VERIFY=PASS"
    else
      echo "CONFIG_REFRESH_VERIFY=MISSING_DOCUMENTED_ACTION"
      exit 5
    fi
  else
    echo "CONFIG_REFRESH_REQUIRED=NO"
    echo "CONFIG_REFRESH_ACTION=NONE"
    if grep -F 'ota.qa.zapways.com/Read' "$CFG_CACHE" >/dev/null 2>&1; then
      echo "CONFIG_CACHE_ALREADY_CURRENT=YES"
    else
      echo "CONFIG_CACHE_ALREADY_CURRENT=UNKNOWN"
    fi
  fi
else
  echo "CONFIG_CACHE_PRE=NO"
  echo "CONFIG_REFRESH_REQUIRED=NO"
  echo "CONFIG_REFRESH_ACTION=NONE"
fi

UP=$(curl -sS -o /dev/null -w "%{http_code}" https://jetpakistan.pk/up || echo 000)
LOGIN=$(curl -sS -o /dev/null -w "%{http_code}" https://jetpakistan.pk/login || echo 000)
LOOKUP=$(curl -sS -o /dev/null -w "%{http_code}" https://jetpakistan.pk/lookup-booking || echo 000)
echo "HTTP_UP=$UP"
echo "HTTP_LOGIN=$LOGIN"
echo "HTTP_LOOKUP=$LOOKUP"
if [ "$UP" = "200" ] && [ "$LOGIN" = "200" ] && { [ "$LOOKUP" = "200" ] || [ "$LOOKUP" = "302" ]; }; then
  echo "HTTP_HEALTH=PASS"
else
  echo "HTTP_HEALTH=CHECK"
fi

echo "PR123_DEPLOYED=YES"
echo "OPTIMIZE_CLEAR=SKIPPED"
echo "DONE=YES"
