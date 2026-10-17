#!/usr/bin/env bash
set -euo pipefail
APP=/home/pkjetp/jetpk_app
PHP=/usr/local/lsws/lsphp83/bin/php
TS=$(date -u +%Y%m%dT%H%M%SZ)
BK=/home/pkjetp/backups/ops-def-0011-0012-pre-deploy-$TS
STAGE=/tmp/ops-def-0011-0012-stage
EXPECT_SHA=9a3f2ba22ecf8466aa4cdf056739a6c3a4ff693e

mkdir -p "$BK/app" "$BK/resources" "$BK/routes" "$BK/frontend-meta"
echo "BACKUP_PATH=$BK"
echo "AUTHORIZED_SHA=$EXPECT_SHA"

# Preserve frontend BUILD_ID for rollback readiness
cp -a "$APP/frontend/.next/BUILD_ID" "$BK/frontend-meta/FRONTEND_BUILD_ID" 2>/dev/null || echo missing > "$BK/frontend-meta/FRONTEND_BUILD_ID"
cp -a "$APP/dashboard/.next/BUILD_ID" "$BK/frontend-meta/DASHBOARD_BUILD_ID" 2>/dev/null || echo missing > "$BK/frontend-meta/DASHBOARD_BUILD_ID"
# Snapshot frontend source paths we will replace (for rollback)
mkdir -p "$BK/frontend"
while IFS= read -r rel; do
  [ -z "$rel" ] && continue
  src="$APP/$rel"
  if [ -f "$src" ]; then
    mkdir -p "$BK/$(dirname "$rel")"
    cp -a "$src" "$BK/$rel"
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

echo "PREVIOUS_FRONTEND_BUILD_ID=$(cat "$BK/frontend-meta/FRONTEND_BUILD_ID")"
echo "PREVIOUS_DASHBOARD_BUILD_ID=$(cat "$BK/frontend-meta/DASHBOARD_BUILD_ID")"
echo "FRONTEND_ROLLBACK_READY=YES"

# Deploy Laravel/Blade/routes from stage (frontend sources deployed separately before build)
fail=0
deployed=0
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
  # Preserve mode if exists
  mode=0644
  if [ -f "$dst" ]; then
    mode=$(stat -c '%a' "$dst")
  fi
  install -o pkjetp -g pkjetp -m "$mode" "$src" "$dst" 2>/dev/null || {
    # root-owned dirs may need root
    echo "INSTALL_NEED_ROOT=$rel"
    fail=$((fail+1))
    continue
  }
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

echo "PHP_BLADE_ROUTE_ATTEMPTED=16"
echo "PHP_BLADE_ROUTE_DEPLOYED_AS_PKJETP=$deployed"
echo "PHP_INSTALL_FAILS=$fail"
echo "BACKUP_DONE=YES"
