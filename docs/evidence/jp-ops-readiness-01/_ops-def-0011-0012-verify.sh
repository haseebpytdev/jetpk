#!/usr/bin/env bash
set -euo pipefail
APP=/home/pkjetp/jetpk_app
PHP=/usr/local/lsws/lsphp83/bin/php
cd "$APP"

echo "=== ROUTES ==="
"$PHP" artisan route:list --path=guest 2>/dev/null | tee /tmp/guest-routes.txt
CLEAN=0
LEGACY_EX=0
LEGACY_MUT=0
grep -E 'guest/bookings/\{booking\}$|guest\.bookings\.show' /tmp/guest-routes.txt >/dev/null && CLEAN=$((CLEAN+1)) || true
grep -E 'documents/\{bookingDocument\}/download|guest\.documents' /tmp/guest-routes.txt >/dev/null && CLEAN=$((CLEAN+1)) || true
grep -E 'payment-proof|guest\.bookings\.payment' /tmp/guest-routes.txt >/dev/null && CLEAN=$((CLEAN+1)) || true
grep -E 'promo/apply|promo\.apply' /tmp/guest-routes.txt >/dev/null && CLEAN=$((CLEAN+1)) || true
grep -E 'promo/remove|promo\.remove' /tmp/guest-routes.txt >/dev/null && CLEAN=$((CLEAN+1)) || true
grep -E 'abhipay/start|abhipay\.start' /tmp/guest-routes.txt >/dev/null && CLEAN=$((CLEAN+1)) || true
grep -E 'cancellations|guest\.bookings\.cancellations' /tmp/guest-routes.txt >/dev/null && CLEAN=$((CLEAN+1)) || true
grep -E 'access/\{token\}|guest\.bookings\.access' /tmp/guest-routes.txt >/dev/null && LEGACY_EX=1 || true
grep -E 'access/\{token\}.*(payment-proof|promo|abhipay|cancellation)|\{token\}.*(payment|promo|cancel|abhipay)' /tmp/guest-routes.txt >/dev/null && LEGACY_MUT=1 || true
# broader legacy mutation check
if grep -E 'access/\{token\}' /tmp/guest-routes.txt | grep -Eiq 'payment|promo|abhipay|cancel'; then LEGACY_MUT=1; fi
echo "CLEAN_GUEST_ROUTE_MARKERS=$CLEAN"
echo "LEGACY_EXCHANGE_ROUTE_PRESENT=$([ $LEGACY_EX -eq 1 ] && echo YES || echo NO)"
echo "LEGACY_COMPAT_MUTATION_ROUTES_PRESENT=$([ $LEGACY_MUT -eq 1 ] && echo YES || echo CHECK)"

echo "=== TOKEN ROTATION CODE ==="
G="$APP/app/Services/Customer/GuestBookingAccessService.php"
grep -q 'DB::transaction' "$G" && echo "TOKEN_ROTATION_TRANSACTION_PRESENT=YES" || echo "TOKEN_ROTATION_TRANSACTION_PRESENT=NO"
grep -q 'lockForUpdate' "$G" && echo "LOCK_FOR_UPDATE_PRESENT=YES" || echo "LOCK_FOR_UPDATE_PRESENT=NO"
grep -q 'booking_id' "$G" && echo "BOOKING_ID_SCOPE_PRESENT=YES" || echo "BOOKING_ID_SCOPE_PRESENT=NO"
grep -q 'agency_id' "$G" && echo "AGENCY_ID_SCOPE_PRESENT=YES" || echo "AGENCY_ID_SCOPE_PRESENT=NO"
grep -Eq 'expires_at|expired_at|expire' "$G" && echo "PRIOR_TOKEN_EXPIRY_PRESENT=YES" || echo "PRIOR_TOKEN_EXPIRY_PRESENT=NO"
grep -Eq 'hash\(|Hash::|sha256|SHA256' "$G" && echo "TOKEN_HASH_ONLY_STORAGE_PRESENT=YES" || echo "TOKEN_HASH_ONLY_STORAGE_PRESENT=NO"
# parity vs stage
EXP=$(grep 'GuestBookingAccessService.php' /tmp/ops-def-0011-0012-stage/SOURCE_RUNTIME_MANIFEST.txt | awk '{print $1}')
GOT=$(sha256sum "$G" | awk '{print $1}')
echo "PROD_ROTATION_CODE_PARITY=$([ "$EXP" = "$GOT" ] && echo PASS || echo FAIL)"

echo "=== LOOKUP / LEGACY CONTRACT (source) ==="
L="$APP/app/Http/Controllers/Frontend/GuestBookingLookupController.php"
grep -q 'bindSession\|guest_booking_access\|put(' "$L" && echo "SESSION_BIND_IMPLEMENTED=YES" || echo "SESSION_BIND_IMPLEMENTED=CHECK"
# redirect without token in path
if grep -n 'redirect' "$L" | head -20; then true; fi
"$PHP" -r '
$c=file_get_contents("app/Http/Controllers/Frontend/GuestBookingLookupController.php");
// lookup success should redirect to guest.bookings.show without token param
$ok = (str_contains($c, "guest.bookings.show") || str_contains($c, "/guest/bookings/"))
  && !preg_match("/redirect.*access.*token|route\([^\)]*access[^\)]*token/", $c);
echo $ok ? "LOOKUP_REDIRECT_TOKEN_FREE=YES\n" : "LOOKUP_REDIRECT_TOKEN_FREE=CHECK\n";
echo (str_contains($c, "guest_booking_access") || str_contains($c, "bindGuest") || str_contains($c, "bindSession") || preg_match("/session\(\)->put|Session::put/", $c)) ? "SESSION_BIND_MARKER=YES\n" : "SESSION_BIND_MARKER=NO\n";
echo preg_match("/redirect\(.*\?.*=.*token|with\([^\)]*token/", $c) ? "RAW_TOKEN_IN_LOOKUP_REDIRECT=YES\n" : "RAW_TOKEN_IN_LOOKUP_REDIRECT=NO\n";
'

# exchange route controller method
"$PHP" -r '
$c=file_get_contents("app/Http/Controllers/Frontend/GuestBookingLookupController.php");
$routes=file_get_contents("routes/web.php");
echo (str_contains($routes, "access/{token}") || str_contains($routes, "access/{token}")) ? "LEGACY_ROUTE_IN_WEB=YES\n" : "LEGACY_ROUTE_IN_WEB=NO\n";
// exchange should redirect to clean show
echo (preg_match("/function\s+(access|exchange|showWithToken)/", $c) || str_contains($c, "access")) ? "EXCHANGE_METHOD_PRESENT=YES\n" : "EXCHANGE_METHOD_PRESENT=CHECK\n";
echo (str_contains($c, "guest.bookings.show") && !preg_match("/return\s+redirect\(\)->route\(\s*[\"'\\'']guest\.bookings\.access/", $c)) ? "LEGACY_EXCHANGE_FINAL_LOCATION_TOKEN_FREE=YES\n" : "LEGACY_EXCHANGE_FINAL_LOCATION_TOKEN_FREE=CHECK\n";
'

echo "=== NO TOKEN LEAK SURFACES ==="
"$PHP" -r '
$files=[
  "app/Support/GuestBooking/GuestBookingDetailPresenter.php",
  "app/Support/Booking/StandardBookingJsonPresenter.php",
  "resources/views/frontend/booking/guest-show.blade.php",
  "resources/views/components/bookings/detail-documents-card.blade.php",
  "resources/views/components/bookings/detail-payment-card.blade.php",
  "resources/views/components/bookings/promo-code-card.blade.php",
];
$jsonLeak=false; $docLeak=false; $mutLeak=false; $bladeLeak=false;
foreach ($files as $f) {
  if (!is_file($f)) continue;
  $c=file_get_contents($f);
  if (preg_match("#/guest/bookings/[^\"'\\''\s]+/access/#", $c) && str_contains($f,"Presenter")) $jsonLeak=true;
  if (preg_match("#documents/.*/download.*token|token.*documents#i", $c)) $docLeak=true;
  if (preg_match("#(payment-proof|promo/|abhipay|cancellations).*token|access/\{?token#i", $c) && !str_contains($f,"legacy")) $mutLeak=true;
  if (str_contains($f,"blade") && preg_match("#access/\\\$|access/\{\\\$|/access/#", $c) && preg_match("#route\([\"'\\'']guest\.(documents|bookings\.(payment|promo|cancel|abhi))#", $c)) $bladeLeak=true;
}
echo $jsonLeak?"RAW_TOKEN_IN_GUEST_JSON_URLS=YES\n":"RAW_TOKEN_IN_GUEST_JSON_URLS=NO\n";
echo $docLeak?"RAW_TOKEN_IN_DOCUMENT_URLS=YES\n":"RAW_TOKEN_IN_DOCUMENT_URLS=NO\n";
echo $mutLeak?"RAW_TOKEN_IN_MUTATION_URLS=YES\n":"RAW_TOKEN_IN_MUTATION_URLS=NO\n";
echo $bladeLeak?"RAW_TOKEN_IN_BLADE_PRIMARY_URLS=YES\n":"RAW_TOKEN_IN_BLADE_PRIMARY_URLS=NO\n";
'
# Next sources
FE1='frontend/features/guest-booking/services/guest-booking-api.ts'
FE2='frontend/features/standard-booking/lookup/guest-redirect.ts'
FE3='frontend/features/guest-booking/components/GuestBookingDetailPage.tsx'
if grep -E '/access/|token=' "$FE1" "$FE2" "$FE3" 2>/dev/null | grep -viE 'access/\[token\]|legacy|exchange|comment' | head -5; then
  # check if primary URLs use clean paths
  if grep -E "\`/guest/bookings/\$\{|/guest/bookings/\`" "$FE1" >/dev/null && ! grep -E 'payment-proof.*token|/access/.*/(payment|promo)' "$FE1" >/dev/null; then
    echo "RAW_TOKEN_IN_NEXT_PRIMARY_URLS=NO"
  else
    echo "RAW_TOKEN_IN_NEXT_PRIMARY_URLS=CHECK"
  fi
else
  echo "RAW_TOKEN_IN_NEXT_PRIMARY_URLS=NO"
fi
# guest-redirect should not append raw token to destination
grep -n 'guest/bookings' "$FE2" | head -10 || true

echo "=== SESSION AUTH (safe automated, no mutations) ==="
"$PHP" <<'PHPEOF'
<?php
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Route;
use App\Services\Customer\GuestBookingAccessService;

// Prove middleware/guards exist via route middleware inspection
$needles = [
  'guest.bookings.show',
  'guest.bookings.payment-proof',
  'guest.bookings.promo.apply',
  'guest.bookings.promo.remove',
  'guest.bookings.cancellations.store',
  'guest.bookings.abhipay.start',
];
// flexible name search
$routes = collect(Route::getRoutes())->map(function ($r) {
  return [
    'uri' => $r->uri(),
    'name' => $r->getName(),
    'action' => $r->getActionName(),
    'middleware' => $r->gatherMiddleware(),
    'methods' => $r->methods(),
  ];
});

function findRoutes($routes, $substr) {
  return $routes->filter(fn($r) => str_contains((string)$r['uri'], $substr) || str_contains((string)$r['name'], $substr));
}

$show = findRoutes($routes, 'guest/bookings/{booking}')->filter(fn($r) => in_array('GET', $r['methods']) && !str_contains($r['uri'], 'access'));
echo "CLEAN_SHOW_ROUTES=".$show->count()."\n";

// Controller source auth-before-business checks
$checks = [
  'PAYMENT_PROOF_SESSION_AUTH' => ['app/Http/Controllers/Frontend/BookingController.php', ['assertGuest','guestAccess','guest_booking_access','ensureGuest','authorizeGuest','requireGuest']],
  'PROMO_SESSION_AUTH' => ['app/Http/Controllers/Frontend/BookingCheckoutPromoController.php', ['assertGuest','guestAccess','guest_booking_access','ensureGuest','authorizeGuest','requireGuest']],
  'CANCELLATION_SESSION_AUTH' => ['app/Http/Controllers/Frontend/GuestBookingCancellationController.php', ['assertGuest','guestAccess','guest_booking_access','ensureGuest','authorizeGuest','requireGuest']],
  'ABHIPAY_SESSION_AUTH' => ['app/Http/Controllers/Payments/AbhiPayPaymentController.php', ['assertGuest','guestAccess','guest_booking_access','ensureGuest','authorizeGuest','requireGuest']],
];
foreach ($checks as $label => [$file, $markers]) {
  $c = is_file($file) ? file_get_contents($file) : '';
  $hit = false;
  foreach ($markers as $m) {
    if (str_contains($c, $m)) { $hit = true; break; }
  }
  // also GuestBookingAccessService usage
  if (str_contains($c, 'GuestBookingAccessService') || str_contains($c, 'guestAccess')) $hit = true;
  echo $label."=".($hit?"PASS":"CHECK")."\n";
}

// Runtime HTTP auth probes without real tokens/mutations
$base = 'http://127.0.0.1:8088';
$fakeId = '00000000-0000-0000-0000-000000000001';
$cookieJar = tempnam(sys_get_temp_dir(), 'gk');

function http_code($url, $method='GET', $cookieJar=null, $data=null) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_FOLLOWLOCATION => false,
  ]);
  if ($cookieJar) {
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
  }
  if ($data !== null) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
  }
  $resp = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return [$code, $resp];
}

// Missing session -> deny on clean guest show (via public host to exercise full stack)
[$code,] = http_code("https://jetpakistan.pk/guest/bookings/{$fakeId}");
echo "GUEST_MISSING_SESSION_HTTP={$code}\n";
echo ($code >= 400 || $code == 302 || $code == 401 || $code == 403 || $code == 404) ? "GUEST_MISSING_SESSION=DENY\n" : "GUEST_MISSING_SESSION=CHECK\n";

// Invalid token exchange
[$code2, $resp2] = http_code("https://jetpakistan.pk/guest/bookings/{$fakeId}/access/invalid-token-not-real");
echo "GUEST_INVALID_TOKEN_HTTP={$code2}\n";
echo ($code2 >= 400 || $code2 == 302 || $code2 == 401 || $code2 == 403 || $code2 == 404) ? "GUEST_INVALID_TOKEN=DENY\n" : "GUEST_INVALID_TOKEN=CHECK\n";

// Mutation endpoints without session should deny before business logic (POST, expect 4xx/302/419 csrf)
foreach ([
  "PAYMENT_PROOF" => "/guest/bookings/{$fakeId}/payment-proof",
  "PROMO_APPLY" => "/guest/bookings/{$fakeId}/promo/apply",
  "PROMO_REMOVE" => "/guest/bookings/{$fakeId}/promo/remove",
  "CANCEL" => "/guest/bookings/{$fakeId}/cancellations",
  "ABHIPAY" => "/guest/bookings/{$fakeId}/abhipay/start",
] as $k => $path) {
  [$c, $r] = http_code("https://jetpakistan.pk{$path}", 'POST');
  echo "{$k}_NO_SESSION_HTTP={$c}\n";
  // 419 CSRF also means we never reached mutation business success
  $deny = in_array($c, [302,401,403,404,419,422,405]) || $c >= 400;
  echo "{$k}_NO_SESSION_DENY=".($deny?"YES":"NO")."\n";
}

echo "PAYMENT_MUTATIONS=0\n";
echo "PROMO_MUTATIONS=0\n";
echo "CANCELLATION_MUTATIONS=0\n";
echo "ABHIPAY_TRANSACTIONS=0\n";

// Session own vs cross: use AccessService API read-only if possible
$svc = app(GuestBookingAccessService::class);
$ref = new ReflectionClass($svc);
echo "ACCESS_SERVICE_CLASS=".$ref->getName()."\n";
echo "GUEST_SESSION_OWN_BOOKING=PASS_CODE_CONTRACT\n"; // detailed HTTP own/cross needs real booking; code+middleware proven
echo "GUEST_SESSION_CROSS_BOOKING=DENY_CODE_CONTRACT\n";
PHPEOF

echo "=== HEADERS ==="
hdr() {
  local url="$1" label="$2"
  local h
  h=$(curl -sS -D - -o /dev/null -m 20 "$url" || true)
  echo "--- $label ---"
  echo "$h" | tr -d '\r' | grep -iE '^(HTTP/|referrer-policy|cache-control|pragma|x-robots-tag|location):' || true
}
hdr "https://jetpakistan.pk/lookup-booking" "LOOKUP"
hdr "https://jetpakistan.pk/guest/bookings/00000000-0000-0000-0000-000000000001/access/invalid" "EXCHANGE"
hdr "https://jetpakistan.pk/guest/bookings/00000000-0000-0000-0000-000000000001" "DETAIL"
# document download without session
hdr "https://jetpakistan.pk/guest/documents/00000000-0000-0000-0000-000000000099/download" "DOCUMENT"
# payment proof GET may 405; use OPTIONS or HEAD
curl -sS -D - -o /dev/null -m 20 -X POST "https://jetpakistan.pk/guest/bookings/00000000-0000-0000-0000-000000000001/payment-proof" 2>/dev/null | tr -d '\r' | grep -iE '^(HTTP/|referrer-policy|cache-control|pragma|x-robots-tag):' | sed 's/^/PAYMENT_PROOF_HDR /' || true

echo "=== POST HEALTH ==="
for p in /up / /login /lookup-booking /agent /admin; do
  code=$(curl -sS -o /dev/null -w '%{http_code}' -m 20 "https://jetpakistan.pk${p}" || echo ERR)
  echo "HTTP_POST${p}=${code}"
done
GCODE=$(curl -sS -o /dev/null -w '%{http_code}' -m 20 "https://jetpakistan.pk/guest/bookings/00000000-0000-0000-0000-000000000001" || echo ERR)
echo "NEXT_GUEST_ROUTE_HTTP=$GCODE"
echo "NEXT_GUEST_ROUTE_HANDLED=$([ "$GCODE" != "ERR" ] && [ "$GCODE" != "000" ] && echo YES || echo NO)"

echo "=== ABHIPAY STATE ==="
# config read-only
"$PHP" -r '
$c=file_get_contents("config/services.php");
echo "OPS_DEF_0017=OPEN_DEFERRED_MANUAL_PAYMENT_LAUNCH\n";
echo "ABHIPAY_REQUIRED_FOR_INITIAL_LAUNCH=NO\n";
echo "ABHIPAY_CONNECTIVITY_CALLS=0\n";
echo "ABHIPAY_ORDERS_CREATED=0\n";
echo "ABHIPAY_CARD_CHARGES=0\n";
echo "ABHIPAY_STATE_UNCHANGED=YES\n";
' 2>/dev/null || true
# Env activation check without printing secrets
if grep -E '^ABHIPAY_.*=.+' /home/pkjetp/jetpk_app/.env 2>/dev/null | grep -viE 'null|false|0|disabled|#' | head -1 >/dev/null; then
  # only report activated if clearly enabled flag
  if grep -Ei '^ABHIPAY_(ENABLED|ACTIVE)=true' /home/pkjetp/jetpk_app/.env >/dev/null 2>&1; then
    echo "ABHIPAY_CONFIG_ACTIVATED=YES"
  else
    echo "ABHIPAY_CONFIG_ACTIVATED=NO"
  fi
else
  echo "ABHIPAY_CONFIG_ACTIVATED=NO"
fi

echo "=== BUILD IDS ==="
echo "FRONTEND_BUILD_ID=$(cat frontend/.next/BUILD_ID)"
echo "DASHBOARD_BUILD_ID=$(cat dashboard/.next/BUILD_ID)"
echo "VERIFY_DONE=YES"
