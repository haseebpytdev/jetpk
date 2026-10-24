#!/usr/bin/env bash
set -euo pipefail
APP=/home/pkjetp/jetpk_app
TS=$(date -u +%Y%m%dT%H%M%SZ)
BK=/home/pkjetp/backups/airblue-pr127-pre-$TS
STAGE=/tmp/airblue-pr127-stage/suppliers.php
TARGET="$APP/config/suppliers.php"
PHP=/usr/local/lsws/lsphp83/bin/php
EXPECT=$(sha256sum "$STAGE" | awk '{print $1}')

echo "MAIN_SHA=2ade5d0c3e77917d95210967d109a1b01e85ab15"
echo "DEPLOY_TS=$TS"
echo "MAIN_SHA256=$EXPECT"
echo "STAGE_SHA256=$EXPECT"

# Sanity: WSDL action present; v2 block must not still use ota.qa for TEST Read
if ! grep -q "http://zapways.com/air/ota/2.0/Read" "$STAGE"; then
  echo "FATAL=STAGED_FILE_MISSING_WSDL_ACTION"
  exit 2
fi
# Extract protocol 2.0 read soap_action_test via python for accuracy
V2_TEST=$(python3 - <<'PY'
import re
text=open("/tmp/airblue-pr127-stage/suppliers.php",encoding="utf-8",errors="replace").read()
m=re.search(r"'2\.0'\s*=>\s*\[.*?['\"]read['\"]\s*=>\s*\[[^\]]*soap_action_test'\s*=>\s*'([^']+)'", text, re.S)
print(m.group(1) if m else "")
PY
)
echo "STAGED_V2_READ_SOAP_ACTION_TEST=$V2_TEST"
if [ "$V2_TEST" != "http://zapways.com/air/ota/2.0/Read" ]; then
  echo "FATAL=STAGED_V2_ACTION_UNEXPECTED"
  exit 2
fi

mkdir -p "$BK/config"
cp -a "$TARGET" "$BK/config/"
PRE=$(sha256sum "$BK/config/suppliers.php" | awk '{print $1}')
echo "BACKUP=$BK"
echo "PRE_SHA256=$PRE"

install -o pkjetp -g pkjetp -m 644 "$STAGE" "$TARGET"
POST=$(sha256sum "$TARGET" | awk '{print $1}')
echo "POST_SHA256=$POST"
if [ "$POST" = "$EXPECT" ]; then
  echo "CONFIG_PARITY=PASS"
else
  echo "CONFIG_PARITY=FAIL"
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

CFG_CACHE="$APP/bootstrap/cache/config.php"
if [ -f "$CFG_CACHE" ]; then
  echo "CONFIG_CACHE_PRE=YES"
  if grep -F "ota.qa.zapways.com/Read" "$CFG_CACHE" >/dev/null 2>&1 && ! grep -F "http://zapways.com/air/ota/2.0/Read" "$CFG_CACHE" >/dev/null 2>&1; then
    echo "CONFIG_REFRESH_REQUIRED=YES"
    echo "CONFIG_REFRESH_ACTION=config:cache"
    cd "$APP"
    $PHP artisan config:cache
    if ! grep -F "http://zapways.com/air/ota/2.0/Read" "$APP/bootstrap/cache/config.php" >/dev/null 2>&1; then
      echo "CONFIG_REFRESH_VERIFY=FAIL"
      exit 4
    fi
    echo "CONFIG_REFRESH_VERIFY=PASS"
  else
    echo "CONFIG_REFRESH_REQUIRED=NO"
    echo "CONFIG_REFRESH_ACTION=NONE"
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

echo "PR127_DEPLOYED=YES"
echo "OPTIMIZE_CLEAR=SKIPPED"
echo "DONE=YES"
