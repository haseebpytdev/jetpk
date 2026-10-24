#!/usr/bin/env bash
set -euo pipefail
APP=/home/pkjetp/jetpk_app
TS=$(date -u +%Y%m%dT%H%M%SZ)
BK=/home/pkjetp/backups/airblue-pr125-pre-$TS
STAGE=/tmp/airblue-pr125-stage/AirBlueRetrieveService.php
TARGET="$APP/app/Services/Suppliers/AirBlue/AirBlueRetrieveService.php"
EXPECT=49d6879e54368bb047d4183bb1f5bce38e4bcc9239f5975ef9e133533d829bda
PHP=/usr/local/lsws/lsphp83/bin/php

echo "MAIN_SHA=4f0f73ead66a2a639331b4c5ffb586bcf5b56027"
echo "DEPLOY_TS=$TS"
echo "MAIN_SHA256=$EXPECT"

STAGE_HASH=$(sha256sum "$STAGE" | awk '{print $1}')
echo "STAGE_SHA256=$STAGE_HASH"
if [ "$STAGE_HASH" != "$EXPECT" ]; then
  echo "FATAL=STAGE_HASH_MISMATCH"
  exit 2
fi

mkdir -p "$BK/app/Services/Suppliers/AirBlue"
cp -a "$TARGET" "$BK/app/Services/Suppliers/AirBlue/"
PRE=$(sha256sum "$BK/app/Services/Suppliers/AirBlue/AirBlueRetrieveService.php" | awk '{print $1}')
echo "BACKUP=$BK"
echo "PRE_SHA256=$PRE"

install -o pkjetp -g pkjetp -m 644 "$STAGE" "$TARGET"
POST=$(sha256sum "$TARGET" | awk '{print $1}')
echo "POST_SHA256=$POST"
if [ "$POST" = "$EXPECT" ]; then
  echo "RETRIEVE_SERVICE_PARITY=PASS"
else
  echo "RETRIEVE_SERVICE_PARITY=FAIL"
  exit 3
fi

$PHP -l "$TARGET"
echo "PHP_SYNTAX=PASS"

$PHP -r '
require "/home/pkjetp/jetpk_app/vendor/autoload.php";
$app = require "/home/pkjetp/jetpk_app/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo "LARAVEL_BOOTSTRAP=PASS\n";
'

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

echo "PR125_DEPLOYED=YES"
echo "OPTIMIZE_CLEAR=SKIPPED"
echo "DONE=YES"
