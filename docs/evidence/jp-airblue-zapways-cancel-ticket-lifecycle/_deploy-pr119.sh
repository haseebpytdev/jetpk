#!/usr/bin/env bash
set -euo pipefail
APP=/home/pkjetp/jetpk_app
TS=$(date -u +%Y%m%dT%H%M%SZ)
BK=/home/pkjetp/backups/airblue-pr119-pre-$TS
EXPECT_PARSER=dbfd3acf8571d002d2413216948c1786b46d0263155e292df0c8c3f5719c803c
EXPECT_BUILDER=12fefec13f3d6ba4458c1fc8123d532a116e47dc6d66e22b5beab91b03cdfd98
STAGE=/tmp/airblue-pr119-stage

echo "MAIN_SHA=5808326bd4ca3faa4de7b9ad902afbae37a8b129"
echo "DEPLOY_TS=$TS"

STAGE_PARSER=$(sha256sum "$STAGE/AirBlueOtaXmlParser.php" | awk '{print $1}')
STAGE_BUILDER=$(sha256sum "$STAGE/AirBlueOtaXmlBuilder.php" | awk '{print $1}')
echo "STAGE_PARSER_SHA256=$STAGE_PARSER"
echo "STAGE_BUILDER_SHA256=$STAGE_BUILDER"
if [ "$STAGE_PARSER" != "$EXPECT_PARSER" ] || [ "$STAGE_BUILDER" != "$EXPECT_BUILDER" ]; then
  echo "FATAL=STAGE_HASH_MISMATCH"
  exit 2
fi
echo "STAGE_HASH_MATCH=YES"

mkdir -p "$BK/app/Services/Suppliers/AirBlue"
cp -a "$APP/app/Services/Suppliers/AirBlue/AirBlueOtaXmlParser.php" "$BK/app/Services/Suppliers/AirBlue/"
cp -a "$APP/app/Services/Suppliers/AirBlue/AirBlueOtaXmlBuilder.php" "$BK/app/Services/Suppliers/AirBlue/"
echo "BACKUP=$BK"
sha256sum "$BK/app/Services/Suppliers/AirBlue/AirBlueOtaXmlParser.php" "$BK/app/Services/Suppliers/AirBlue/AirBlueOtaXmlBuilder.php" | sed 's|^|PRE_|'

install -o pkjetp -g pkjetp -m 644 "$STAGE/AirBlueOtaXmlParser.php" "$APP/app/Services/Suppliers/AirBlue/AirBlueOtaXmlParser.php"
install -o pkjetp -g pkjetp -m 644 "$STAGE/AirBlueOtaXmlBuilder.php" "$APP/app/Services/Suppliers/AirBlue/AirBlueOtaXmlBuilder.php"

PROD_PARSER=$(sha256sum "$APP/app/Services/Suppliers/AirBlue/AirBlueOtaXmlParser.php" | awk '{print $1}')
PROD_BUILDER=$(sha256sum "$APP/app/Services/Suppliers/AirBlue/AirBlueOtaXmlBuilder.php" | awk '{print $1}')
echo "PROD_PARSER_SHA256=$PROD_PARSER"
echo "PROD_BUILDER_SHA256=$PROD_BUILDER"

if [ "$PROD_PARSER" = "$EXPECT_PARSER" ]; then echo "PARSER_PARITY=YES"; else echo "PARSER_PARITY=NO"; exit 3; fi
if [ "$PROD_BUILDER" = "$EXPECT_BUILDER" ]; then echo "BUILDER_PARITY=YES"; else echo "BUILDER_PARITY=NO"; exit 3; fi

/usr/local/lsws/lsphp83/bin/php -l "$APP/app/Services/Suppliers/AirBlue/AirBlueOtaXmlParser.php"
/usr/local/lsws/lsphp83/bin/php -l "$APP/app/Services/Suppliers/AirBlue/AirBlueOtaXmlBuilder.php"
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

echo "PR119_DEPLOYED=YES"
echo "OPTIMIZE_CLEAR=SKIPPED"
echo "DONE=YES"
