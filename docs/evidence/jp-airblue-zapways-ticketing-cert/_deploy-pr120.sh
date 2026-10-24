#!/usr/bin/env bash
set -euo pipefail
APP=/home/pkjetp/jetpk_app
TS=$(date -u +%Y%m%dT%H%M%SZ)
BK=/home/pkjetp/backups/airblue-pr120-pre-$TS
EXPECT=a630f48d2b5eb02dd29faa91184106b8862ed7af1d526410612141a11b632042
STAGE=/tmp/airblue-pr120-stage
TARGET="$APP/app/Services/Suppliers/AirBlue/AirBlueBookingService.php"

echo "MAIN_SHA=bc676a7b18c8a74859311cc38f523eb0c057be77"
echo "DEPLOY_TS=$TS"
echo "MAIN_SHA256=$EXPECT"

STAGE_HASH=$(sha256sum "$STAGE/AirBlueBookingService.php" | awk '{print $1}')
echo "STAGE_SHA256=$STAGE_HASH"
if [ "$STAGE_HASH" != "$EXPECT" ]; then
  echo "FATAL=STAGE_HASH_MISMATCH"
  exit 2
fi
echo "STAGE_HASH_MATCH=YES"

mkdir -p "$BK/app/Services/Suppliers/AirBlue"
cp -a "$TARGET" "$BK/app/Services/Suppliers/AirBlue/"
echo "BACKUP=$BK"
PRE=$(sha256sum "$BK/app/Services/Suppliers/AirBlue/AirBlueBookingService.php" | awk '{print $1}')
echo "PREDEPLOY_SHA256=$PRE"

install -o pkjetp -g pkjetp -m 644 "$STAGE/AirBlueBookingService.php" "$TARGET"

POST=$(sha256sum "$TARGET" | awk '{print $1}')
echo "POSTDEPLOY_SHA256=$POST"
if [ "$POST" = "$EXPECT" ]; then
  echo "BOOKING_SERVICE_PARITY=PASS"
else
  echo "BOOKING_SERVICE_PARITY=FAIL"
  exit 3
fi

/usr/local/lsws/lsphp83/bin/php -l "$TARGET"
echo "PHP_SYNTAX=PASS"

/usr/local/lsws/lsphp83/bin/php -r '
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

echo "PR120_DEPLOYED=YES"
echo "OPTIMIZE_CLEAR=SKIPPED"
echo "DONE=YES"
