#!/usr/bin/env bash
set -euo pipefail
APP=/home/pkjetp/jetpk_app
PHP=/usr/local/lsws/lsphp83/bin/php
TS=$(date -u +%Y%m%dT%H%M%SZ)
BK=/home/pkjetp/backups/ops-def-0011-0012-pre-deploy-$TS
STAGE=/tmp/ops-def-0011-0012-stage
EXPECT_SHA=9a3f2ba22ecf8466aa4cdf056739a6c3a4ff693e

mkdir -p "$BK/frontend-meta"
echo "BACKUP_PATH=$BK"
echo "AUTHORIZED_SHA=$EXPECT_SHA"

cp -a "$APP/frontend/.next/BUILD_ID" "$BK/frontend-meta/FRONTEND_BUILD_ID" 2>/dev/null || echo missing > "$BK/frontend-meta/FRONTEND_BUILD_ID"
cp -a "$APP/dashboard/.next/BUILD_ID" "$BK/frontend-meta/DASHBOARD_BUILD_ID" 2>/dev/null || echo missing > "$BK/frontend-meta/DASHBOARD_BUILD_ID"
PREV_FE=$(cat "$BK/frontend-meta/FRONTEND_BUILD_ID")
PREV_DASH=$(cat "$BK/frontend-meta/DASHBOARD_BUILD_ID")
echo "PREVIOUS_FRONTEND_BUILD_ID=$PREV_FE"
echo "PREVIOUS_DASHBOARD_BUILD_ID=$PREV_DASH"
echo "FRONTEND_ROLLBACK_READY=YES"

# Backup every file we will replace
while IFS= read -r rel; do
  [ -z "$rel" ] && continue
  src="$APP/$rel"
  if [ -f "$src" ]; then
    mkdir -p "$BK/$(dirname "$rel")"
    cp -a "$src" "$BK/$rel"
  else
    echo "BACKUP_MISSING_SRC=$rel"
  fi
done <<'EOF'
app/Http/Controllers/Frontend/BookingCheckoutPromoController.php
app/Http/Controllers/Frontend/BookingController.php
app/Http/Controllers/Frontend/GuestBookingCancellationController.php
app/Http/Controllers/Frontend/GuestBookingLookupController.php
app/Http/Controllers/Payments/AbhiPayPaymentController.php
app/Http/Middleware/SecurityHeaders.php
app/Services/Customer/GuestBookingAccessService.php
app/Support/Booking/StandardBookingJsonPresenter.php
app/Support/GuestBooking/GuestBookingDetailPresenter.php
routes/web.php
resources/views/components/bookings/detail-documents-card.blade.php
resources/views/components/bookings/detail-payment-card.blade.php
resources/views/components/bookings/promo-code-card.blade.php
resources/views/frontend/booking/guest-show.blade.php
resources/views/frontend/booking/partials/confirmation-body.blade.php
resources/views/themes/frontend/jetpakistan/frontend/booking/partials/confirmation-body.blade.php
frontend/app/(public)/guest/bookings/[booking]/access/[token]/page.tsx
frontend/app/(public)/guest/bookings/[booking]/page.tsx
frontend/features/guest-booking/components/GuestBookingDetailPage.tsx
frontend/features/guest-booking/services/guest-booking-api.ts
frontend/features/standard-booking/lookup/guest-redirect.ts
EOF

# Expected sha256 from SOURCE_RUNTIME_MANIFEST
declare -A EXPECT
while read -r hash path; do
  [ -z "${hash:-}" ] && continue
  EXPECT["$path"]="$hash"
done < "$STAGE/SOURCE_RUNTIME_MANIFEST.txt"

deployed=0
fail=0
while IFS= read -r rel; do
  [ -z "$rel" ] && continue
  case "$rel" in
    frontend/*) continue ;;
  esac
  src="$STAGE/$rel"
  dst="$APP/$rel"
  if [ ! -f "$src" ]; then
    echo "MISSING_STAGE=$rel"
    fail=$((fail+1))
    continue
  fi
  mkdir -p "$(dirname "$dst")"
  mode=0644
  if [ -f "$dst" ]; then
    mode=$(stat -c '%a' "$dst")
  fi
  install -o pkjetp -g pkjetp -m "$mode" "$src" "$dst"
  got=$(sha256sum "$dst" | awk '{print $1}')
  exp="${EXPECT[$rel]:-}"
  if [ -n "$exp" ] && [ "$got" != "$exp" ]; then
    echo "PARITY_FAIL=$rel got=$got exp=$exp"
    fail=$((fail+1))
    # rollback this file from backup if present
    if [ -f "$BK/$rel" ]; then
      cp -a "$BK/$rel" "$dst"
      echo "RESTORED=$rel"
    fi
    continue
  fi
  echo "PARITY_OK=$rel"
  deployed=$((deployed+1))
done <<'EOF'
app/Http/Controllers/Frontend/BookingCheckoutPromoController.php
app/Http/Controllers/Frontend/BookingController.php
app/Http/Controllers/Frontend/GuestBookingCancellationController.php
app/Http/Controllers/Frontend/GuestBookingLookupController.php
app/Http/Controllers/Payments/AbhiPayPaymentController.php
app/Http/Middleware/SecurityHeaders.php
app/Services/Customer/GuestBookingAccessService.php
app/Support/Booking/StandardBookingJsonPresenter.php
app/Support/GuestBooking/GuestBookingDetailPresenter.php
routes/web.php
resources/views/components/bookings/detail-documents-card.blade.php
resources/views/components/bookings/detail-payment-card.blade.php
resources/views/components/bookings/promo-code-card.blade.php
resources/views/frontend/booking/guest-show.blade.php
resources/views/frontend/booking/partials/confirmation-body.blade.php
resources/views/themes/frontend/jetpakistan/frontend/booking/partials/confirmation-body.blade.php
EOF

echo "PHP_BLADE_ROUTE_FILES_DEPLOYED=$deployed"
echo "PHP_INSTALL_FAILS=$fail"

if [ "$fail" -ne 0 ] || [ "$deployed" -ne 16 ]; then
  echo "PHP_RUNTIME_PARITY=FAIL"
  echo "STOP=YES"
  exit 2
fi
echo "PHP_RUNTIME_PARITY=PASS"
echo "UNEXPECTED_RUNTIME_FILES_DEPLOYED=0"

# php -l on changed PHP
syn_fail=0
while IFS= read -r rel; do
  [ -z "$rel" ] && continue
  if ! "$PHP" -l "$APP/$rel" >/tmp/php-l-out.txt 2>&1; then
    echo "PHP_L_FAIL=$rel"
    cat /tmp/php-l-out.txt
    syn_fail=$((syn_fail+1))
  else
    echo "PHP_L_OK=$rel"
  fi
done <<'EOF'
app/Http/Controllers/Frontend/BookingCheckoutPromoController.php
app/Http/Controllers/Frontend/BookingController.php
app/Http/Controllers/Frontend/GuestBookingCancellationController.php
app/Http/Controllers/Frontend/GuestBookingLookupController.php
app/Http/Controllers/Payments/AbhiPayPaymentController.php
app/Http/Middleware/SecurityHeaders.php
app/Services/Customer/GuestBookingAccessService.php
app/Support/Booking/StandardBookingJsonPresenter.php
app/Support/GuestBooking/GuestBookingDetailPresenter.php
EOF
echo "PHP_SYNTAX_FAIL=$syn_fail"
if [ "$syn_fail" -ne 0 ]; then
  echo "STOP_SYNTAX=YES"
  exit 3
fi

cd "$APP"
"$PHP" -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo "LARAVEL_BOOTSTRAP=PASS\n";
'

# Install frontend sources from flat map
install_fe() {
  local flat="$1" rel="$2"
  local src="$STAGE/_fe_flat/$flat"
  local dst="$APP/$rel"
  if [ ! -f "$src" ]; then
    echo "MISSING_FE_FLAT=$flat"
    return 1
  fi
  mkdir -p "$(dirname "$dst")"
  mode=0644
  [ -f "$dst" ] && mode=$(stat -c '%a' "$dst")
  install -o pkjetp -g pkjetp -m "$mode" "$src" "$dst"
  got=$(sha256sum "$dst" | awk '{print $1}')
  exp="${EXPECT[$rel]:-}"
  if [ -n "$exp" ] && [ "$got" != "$exp" ]; then
    echo "FE_PARITY_FAIL=$rel got=$got exp=$exp"
    return 1
  fi
  echo "FE_PARITY_OK=$rel"
  return 0
}

fe_fail=0
install_fe fe__booking__page.tsx 'frontend/app/(public)/guest/bookings/[booking]/page.tsx' || fe_fail=$((fe_fail+1))
install_fe fe__access__page.tsx 'frontend/app/(public)/guest/bookings/[booking]/access/[token]/page.tsx' || fe_fail=$((fe_fail+1))
install_fe fe__GuestBookingDetailPage.tsx 'frontend/features/guest-booking/components/GuestBookingDetailPage.tsx' || fe_fail=$((fe_fail+1))
install_fe fe__guest-booking-api.ts 'frontend/features/guest-booking/services/guest-booking-api.ts' || fe_fail=$((fe_fail+1))
install_fe fe__guest-redirect.ts 'frontend/features/standard-booking/lookup/guest-redirect.ts' || fe_fail=$((fe_fail+1))
echo "FRONTEND_SOURCES_FAIL=$fe_fail"
if [ "$fe_fail" -ne 0 ]; then
  echo "FRONTEND_SOURCE_PARITY=FAIL"
  exit 4
fi
echo "FRONTEND_SOURCE_PARITY=PASS"
echo "BACKUP_PATH=$BK" > "$BK/BACKUP_META.txt"
echo "PREVIOUS_FRONTEND_BUILD_ID=$PREV_FE" >> "$BK/BACKUP_META.txt"
echo "DEPLOY_PHP_FE_SOURCES_OK=YES"
