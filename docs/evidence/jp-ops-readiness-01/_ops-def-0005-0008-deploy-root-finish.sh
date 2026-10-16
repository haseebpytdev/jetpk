#!/usr/bin/env bash
set -euo pipefail
APP=/home/pkjetp/jetpk_app
PHP=/usr/local/lsws/lsphp83/bin/php
BK=/home/pkjetp/backups/ops-def-0005-0008-pre-deploy-20261016T194205Z
EXPECT_GUEST=4370cdf602308473027643ce9c429fa311d9d5a2ebe3b827311f584619751e2e
EXPECT_DEP=37e7eaf57348fde24dc44d4af25fdcae1fd90120519a337b3b80407d89396217
GUEST="$APP/app/Services/Customer/GuestBookingAccessService.php"
DEP="$APP/app/Services/Dashboard/Api/DashboardDepositsReadService.php"

# Deposits dir is root-owned; finish deposits install as root.
install -o pkjetp -g pkjetp -m 0644 /tmp/DashboardDepositsReadService.php "$DEP"

POST_GUEST=$(sha256sum "$GUEST" | awk '{print $1}')
POST_DEP=$(sha256sum "$DEP" | awk '{print $1}')
echo "PROD_GUEST_LOOKUP_SHA256_POST=$POST_GUEST"
echo "PROD_DEPOSITS_SERVICE_SHA256_POST=$POST_DEP"

if [[ "$POST_GUEST" != "$EXPECT_GUEST" || "$POST_DEP" != "$EXPECT_DEP" ]]; then
  echo "PARITY_FAIL — restoring both from $BK"
  cp -a "$BK/GuestBookingAccessService.php" "$GUEST"
  cp -a "$BK/DashboardDepositsReadService.php" "$DEP"
  chown pkjetp:pkjetp "$GUEST" "$DEP"
  chmod 0664 "$GUEST"
  chmod 0644 "$DEP"
  echo "GUEST_LOOKUP_FILE_PARITY=FAIL"
  echo "DEPOSITS_SERVICE_FILE_PARITY=FAIL"
  exit 2
fi

echo "GUEST_LOOKUP_FILE_PARITY=PASS"
echo "DEPOSITS_SERVICE_FILE_PARITY=PASS"
echo "FILES_DEPLOYED=2"
echo "UNEXPECTED_FILES_DEPLOYED=0"
echo "BACKUP_PATH=$BK"

echo -n "PHP_SYNTAX_GUEST="
"$PHP" -l "$GUEST"
echo -n "PHP_SYNTAX_DEPOSITS="
"$PHP" -l "$DEP"

cd "$APP"
"$PHP" -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo "LARAVEL_BOOTSTRAP=PASS\n";'

printf '%s' '922f15774ece177d8b3cc23207e98379b8a2755b+ops-def-0005-0008@79da54779df46139c981de03e852009572c4ffd8' > "$APP/storage/app/ops-def-0005-0008-patch.txt"
chown pkjetp:pkjetp "$APP/storage/app/ops-def-0005-0008-patch.txt"

echo "BUILD_ID_POST=$(cat $APP/dashboard/.next/BUILD_ID)"
echo "RUNTIME_SHA_BASE=$(cat $APP/.jetpk-runtime-sha)"

for p in /up / /login /admin /agent; do
  code=$(curl -sS -o /dev/null -w '%{http_code}' -m 15 "https://jetpakistan.pk${p}" || echo ERR)
  echo "HTTP_POST${p}=${code}"
done

stat -c 'GUEST_POST=%U:%G %a' "$GUEST"
stat -c 'DEP_POST=%U:%G %a' "$DEP"

grep -n 'agent_id\|0 = 1\|agency_id' "$DEP" | head -20
grep -n 'emailMatch && \$phoneMatch\|normalizedEmail !== null && \$normalizedPhone !== null' "$GUEST" | head -10
echo "DEPLOY_COMPLETE=YES"
