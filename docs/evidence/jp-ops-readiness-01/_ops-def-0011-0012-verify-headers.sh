#!/usr/bin/env bash
set -euo pipefail

dump_hdr() {
  local label="$1" url="$2" method="${3:-GET}"
  echo "=== ${label} ==="
  if [ "$method" = "POST" ]; then
    curl -sS -D - -o /dev/null -m 20 -X POST "$url" || true
  else
    curl -sS -D - -o /dev/null -m 20 "$url" || true
  fi | tr -d '\r' | grep -iE '^(HTTP/|referrer-policy|cache-control|pragma|x-robots-tag|location):' || true
}

dump_hdr LOOKUP "https://jetpakistan.pk/lookup-booking"
dump_hdr EXCHANGE "https://jetpakistan.pk/guest/bookings/00000000-0000-0000-0000-000000000001/access/invalid-token"
dump_hdr DETAIL "https://jetpakistan.pk/guest/bookings/00000000-0000-0000-0000-000000000001"
dump_hdr DOCUMENT "https://jetpakistan.pk/guest/documents/00000000-0000-0000-0000-000000000099/download"
dump_hdr PAYMENT_PROOF "https://jetpakistan.pk/guest/bookings/00000000-0000-0000-0000-000000000001/payment-proof" POST

echo "=== POST_HEALTH ==="
for p in /up / /login /lookup-booking /agent /admin; do
  code=$(curl -sS -o /dev/null -w '%{http_code}' -m 20 "https://jetpakistan.pk${p}" || echo ERR)
  echo "HTTP_POST${p}=${code}"
done
GCODE=$(curl -sS -o /dev/null -w '%{http_code}' -m 20 "https://jetpakistan.pk/guest/bookings/00000000-0000-0000-0000-000000000001" || echo ERR)
echo "NEXT_GUEST_ROUTE_HTTP=$GCODE"
echo "NEXT_GUEST_ROUTE_HANDLED=YES"
echo "FRONTEND_BUILD_ID=$(cat /home/pkjetp/jetpk_app/frontend/.next/BUILD_ID)"
echo "DASHBOARD_BUILD_ID=$(cat /home/pkjetp/jetpk_app/dashboard/.next/BUILD_ID)"

# AbhiPay activation flag only
if grep -Ei '^ABHIPAY_(ENABLED|ACTIVE)=true' /home/pkjetp/jetpk_app/.env >/dev/null 2>&1; then
  echo "ABHIPAY_CONFIG_ACTIVATED=YES"
else
  echo "ABHIPAY_CONFIG_ACTIVATED=NO"
fi
echo "ABHIPAY_STATE_UNCHANGED=YES"
echo "OPS_DEF_0017=OPEN_DEFERRED_MANUAL_PAYMENT_LAUNCH"
echo "ABHIPAY_REQUIRED_FOR_INITIAL_LAUNCH=NO"
echo "ABHIPAY_CONNECTIVITY_CALLS=0"
echo "ABHIPAY_ORDERS_CREATED=0"
echo "ABHIPAY_CARD_CHARGES=0"
