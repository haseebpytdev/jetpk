#!/usr/bin/env bash
set -euo pipefail
dump() {
  local label="$1" url="$2" method="${3:-GET}"
  echo "=== ${label} ==="
  echo "URL=${url}"
  if [ "$method" = "POST" ]; then
    curl -sS -D - -o /dev/null -m 20 -X POST "$url" || true
  else
    curl -sS -D - -o /dev/null -m 20 "$url" || true
  fi | tr -d '\r' | grep -iE '^(HTTP/|referrer-policy|cache-control|pragma|x-robots-tag|location|content-type):' || true
  echo
}
dump LOOKUP "https://jetpakistan.pk/lookup-booking"
dump EXCHANGE "https://jetpakistan.pk/guest/bookings/00000000-0000-0000-0000-000000000001/access/invalid-token"
dump DETAIL "https://jetpakistan.pk/guest/bookings/00000000-0000-0000-0000-000000000001"
dump DOCUMENT "https://jetpakistan.pk/guest/documents/00000000-0000-0000-0000-000000000099/download"
dump PAYMENT_PROOF "https://jetpakistan.pk/guest/bookings/00000000-0000-0000-0000-000000000001/payment-proof" POST

# Internal Laravel listener for Blade/helper-backed responses
dump LARAVEL_EXCHANGE "http://127.0.0.1:8088/guest/bookings/1/access/invalid-token"
dump LARAVEL_DETAIL "http://127.0.0.1:8088/guest/bookings/1"
dump LARAVEL_DOCUMENT "http://127.0.0.1:8088/guest/documents/1/download"
dump LARAVEL_PAYMENT_PROOF "http://127.0.0.1:8088/guest/bookings/1/payment-proof" POST

echo "HELPER_SOURCE_MARKERS:"
grep -n "Referrer-Policy\|no-store\|X-Robots-Tag\|Pragma" /home/pkjetp/jetpk_app/app/Http/Controllers/Frontend/GuestBookingLookupController.php | head -20
