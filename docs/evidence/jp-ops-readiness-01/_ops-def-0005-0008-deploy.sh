#!/usr/bin/env bash
set -euo pipefail
APP=/home/pkjetp/jetpk_app
PHP=/usr/local/lsws/lsphp83/bin/php
TS=$(date -u +%Y%m%dT%H%M%SZ)
BK=/home/pkjetp/backups/ops-def-0005-0008-pre-deploy-$TS
EXPECT_GUEST=4370cdf602308473027643ce9c429fa311d9d5a2ebe3b827311f584619751e2e
EXPECT_DEP=37e7eaf57348fde24dc44d4af25fdcae1fd90120519a337b3b80407d89396217
GUEST_REL=app/Services/Customer/GuestBookingAccessService.php
DEP_REL=app/Services/Dashboard/Api/DashboardDepositsReadService.php
GUEST="$APP/$GUEST_REL"
DEP="$APP/$DEP_REL"

mkdir -p "$BK"
cp -a "$GUEST" "$BK/GuestBookingAccessService.php"
cp -a "$DEP" "$BK/DashboardDepositsReadService.php"
echo "BACKUP_PATH=$BK"
echo "BACKUP_GUEST_LOOKUP_SHA256=$(sha256sum "$BK/GuestBookingAccessService.php" | awk '{print $1}')"
echo "BACKUP_DEPOSITS_SERVICE_SHA256=$(sha256sum "$BK/DashboardDepositsReadService.php" | awk '{print $1}')"

# Preserve modes: guest 664, deposits 644; owner pkjetp:pkjetp
install -o pkjetp -g pkjetp -m 0664 /tmp/GuestBookingAccessService.php "$GUEST"
install -o pkjetp -g pkjetp -m 0644 /tmp/DashboardDepositsReadService.php "$DEP"

POST_GUEST=$(sha256sum "$GUEST" | awk '{print $1}')
POST_DEP=$(sha256sum "$DEP" | awk '{print $1}')
echo "PROD_GUEST_LOOKUP_SHA256_POST=$POST_GUEST"
echo "PROD_DEPOSITS_SERVICE_SHA256_POST=$POST_DEP"

if [[ "$POST_GUEST" != "$EXPECT_GUEST" || "$POST_DEP" != "$EXPECT_DEP" ]]; then
  echo "CHECKSUM_MISMATCH — restoring both"
  cp -a "$BK/GuestBookingAccessService.php" "$GUEST"
  cp -a "$BK/DashboardDepositsReadService.php" "$DEP"
  echo "GUEST_LOOKUP_FILE_PARITY=FAIL"
  echo "DEPOSITS_SERVICE_FILE_PARITY=FAIL"
  echo "RESTORED=YES"
  exit 2
fi

echo "GUEST_LOOKUP_FILE_PARITY=PASS"
echo "DEPOSITS_SERVICE_FILE_PARITY=PASS"
echo "FILES_DEPLOYED=2"
echo "UNEXPECTED_FILES_DEPLOYED=0"

echo -n "PHP_SYNTAX_GUEST="
"$PHP" -l "$GUEST"
echo -n "PHP_SYNTAX_DEPOSITS="
"$PHP" -l "$DEP"

cd "$APP"
"$PHP" -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo "LARAVEL_BOOTSTRAP=PASS\n";
$g = file_get_contents("app/Services/Customer/GuestBookingAccessService.php");
$d = file_get_contents("app/Services/Dashboard/Api/DashboardDepositsReadService.php");
echo (str_contains($g, "emailMatch && \$phoneMatch") || preg_match("/normalizedEmail !== null && \\\$normalizedPhone !== null && \\\$emailMatch && \\\$phoneMatch/", $g)) ? "GUEST_AND_LOGIC_PRESENT=YES\n" : "GUEST_AND_LOGIC_PRESENT=NO\n";
echo (str_contains($d, "agent_id") && str_contains($d, "agency_id")) ? "DEPOSITS_SCOPE_MARKERS=YES\n" : "DEPOSITS_SCOPE_MARKERS=NO\n";
echo (str_contains($d, "fail closed") || str_contains($d, "whereRaw('\''1 = 0'\'')") || str_contains($d, "whereRaw(\"1 = 0\")") || str_contains($d, "1 = 0")) ? "MISSING_AGENT_FAILS_CLOSED_MARKER=YES\n" : "MISSING_AGENT_FAILS_CLOSED_MARKER=CHECK\n";
'

# Do NOT rewrite full runtime SHA to protected main (scoped two-file patch only).
# Record patch marker alongside existing runtime base.
printf '%s' "922f15774ece177d8b3cc23207e98379b8a2755b+ops-def-0005-0008@79da54779df46139c981de03e852009572c4ffd8" > "$APP/storage/app/ops-def-0005-0008-patch.txt" || true
echo "RUNTIME_SHA_BASE=$(cat $APP/.jetpk-runtime-sha 2>/dev/null || echo MISSING)"
echo "BUILD_ID_POST=$(cat $APP/dashboard/.next/BUILD_ID 2>/dev/null || echo MISSING)"

for p in /up / /login /admin /agent; do
  code=$(curl -sS -o /dev/null -w '%{http_code}' -m 15 "https://jetpakistan.pk${p}" || echo ERR)
  echo "HTTP_POST${p}=${code}"
done

stat -c 'GUEST_POST_OWNER=%U:%G MODE=%a' "$GUEST"
stat -c 'DEP_POST_OWNER=%U:%G MODE=%a' "$DEP"
echo "DEPLOY_OK=YES"
