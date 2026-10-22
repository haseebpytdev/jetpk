#!/usr/bin/env bash
# Phase 4-5: process-scoped sandbox search certification (max 8 calls)
set -euo pipefail
APP=/home/pkjetp/jetpk_app
PHP=/usr/local/lsws/lsphp83/bin/php
CONN=22
LOG=/tmp/ab-sandbox-search-matrix.log
: >"$LOG"
cd "$APP"

assert_sandbox() {
  "$PHP" -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$c=App\Models\SupplierConnection::find(22);
$env=(string)($c->environment?->value ?? "");
$host=parse_url((string)$c->base_url, PHP_URL_HOST) ?: "";
echo "AIRBLUE_ENVIRONMENT=$env\n";
echo "AIRBLUE_ENDPOINT_HOST_ONLY=$host\n";
if ($env !== "sandbox" || $host !== "otatest4.zapways.com") {
  fwrite(STDERR, "STOP_ENV_DRIFT\n");
  exit(2);
}
'
}

echo "=== PHASE4_PRECHECK ==="
assert_sandbox
echo "AIRBLUE_EXTERNAL_CALLS_GLOBAL=$("$PHP" -r 'echo filter_var(getenv("AIRBLUE_EXTERNAL_CALLS_ENABLED") ?: "false", FILTER_VALIDATE_BOOLEAN) ? "YES" : "NO";')"
echo "AIRBLUE_CLI_LIVE_GLOBAL=$("$PHP" -r 'echo filter_var(getenv("AIRBLUE_CLI_LIVE_ENABLED") ?: "false", FILTER_VALIDATE_BOOLEAN) ? "YES" : "NO";')"

# Future dates
D1=$(date -u -d '+45 days' +%Y-%m-%d 2>/dev/null || date -u -v+45d +%Y-%m-%d)
D2=$(date -u -d '+60 days' +%Y-%m-%d 2>/dev/null || date -u -v+60d +%Y-%m-%d)
D3=$(date -u -d '+90 days' +%Y-%m-%d 2>/dev/null || date -u -v+90d +%Y-%m-%d)

# Bounded matrix: domestic + common PA routes
ROUTES=(
  "ISB|KHI|$D1"
  "KHI|ISB|$D1"
  "LHE|KHI|$D1"
  "ISB|DXB|$D2"
  "KHI|DXB|$D2"
  "ISB|JED|$D3"
  "LHE|DXB|$D2"
  "ISB|KHI|$D2"
)

CALLS=0
OFFER_FOUND=NO
AUTH_ACCEPTED=NO
TRANSPORT_PASS=NO
PROVIDER_PASS=NO
WINNING_ROUTE=""
WINNING_DATE=""

run_search() {
  local from="$1" to="$2" date="$3"
  CALLS=$((CALLS+1))
  assert_sandbox
  echo "=== SEARCH_CALL=$CALLS route=${from}-${to} date=${date} ===" | tee -a "$LOG"
  set +e
  OUT=$(AIRBLUE_EXTERNAL_CALLS_ENABLED=true AIRBLUE_CLI_LIVE_ENABLED=true \
    "$PHP" artisan airblue:test-search \
      --connection="$CONN" --allow-live \
      --from="$from" --to="$to" --date="$date" \
      --adults=1 --children=0 --infants=0 --currency=PKR 2>&1)
  RC=$?
  set -e
  printf '%s\n' "$OUT" | tee -a "$LOG" | head -30
  echo "SEARCH_RC=$RC" | tee -a "$LOG"

  # Classify without dumping SOAP
  offers=$(printf '%s\n' "$OUT" | sed -n 's/^offers_count=//p' | head -1)
  err=$(printf '%s\n' "$OUT" | sed -n 's/^error_code=//p' | head -1)
  echo "CLASSIFIED_OFFERS=${offers:-0}" | tee -a "$LOG"
  echo "CLASSIFIED_ERROR=${err:-none}" | tee -a "$LOG"

  if [[ -z "${err:-}" || "$err" == "none" ]]; then
    # no error_code line or empty — treat as provider structural success if RC=0 or offers present
    if [[ "$RC" -eq 0 || "${offers:-0}" =~ ^[0-9]+$ ]]; then
      PROVIDER_PASS=YES
      TRANSPORT_PASS=YES
      AUTH_ACCEPTED=YES
    fi
  fi

  # Auth / transport heuristics
  case "${err:-}" in
    auth*|unauthorized*|forbidden*|tls*|certificate*|ssl*|connection*|timeout*|http_*)
      echo "PROVIDER_CLASS=TRANSPORT_OR_AUTH_FAIL" | tee -a "$LOG"
      ;;
    *)
      if [[ "$RC" -eq 0 ]]; then
        TRANSPORT_PASS=YES
        AUTH_ACCEPTED=YES
        PROVIDER_PASS=YES
      fi
      ;;
  esac

  if [[ "${offers:-0}" =~ ^[1-9][0-9]*$ ]]; then
    OFFER_FOUND=YES
    WINNING_ROUTE="${from}-${to}"
    WINNING_DATE="$date"
    echo "OFFER_FOUND=YES" | tee -a "$LOG"
    return 0
  fi
  return 1
}

for spec in "${ROUTES[@]}"; do
  IFS='|' read -r from to date <<<"$spec"
  if run_search "$from" "$to" "$date"; then
    break
  fi
  if [[ "$CALLS" -ge 8 ]]; then
    break
  fi
  # gentle pacing
  sleep 2
done

echo "=== PHASE5_SUMMARY ==="
echo "SANDBOX_SEARCH_CALLS=$CALLS"
echo "AUTH_ACCEPTED=$AUTH_ACCEPTED"
echo "SEARCH_TRANSPORT_PASS=$TRANSPORT_PASS"
echo "SEARCH_PROVIDER_PASS=$PROVIDER_PASS"
echo "SEARCH_OFFER_FOUND=$OFFER_FOUND"
echo "WINNING_ROUTE=${WINNING_ROUTE:-none}"
echo "WINNING_DATE=${WINNING_DATE:-none}"

if [[ "$OFFER_FOUND" == "YES" ]]; then
  echo "AIRBLUE_SEARCH_STATUS=PASS_WITH_OFFERS"
elif [[ "$PROVIDER_PASS" == "YES" && "$TRANSPORT_PASS" == "YES" && "$AUTH_ACCEPTED" == "YES" ]]; then
  echo "AIRBLUE_SEARCH_STATUS=PASS_NO_INVENTORY"
else
  echo "AIRBLUE_SEARCH_STATUS=FAIL"
fi

# Prove global kill switches still off outside process env
echo "POST_SEARCH_GLOBAL_EXTERNAL=$("$PHP" -r 'echo filter_var(env("AIRBLUE_EXTERNAL_CALLS_ENABLED", false), FILTER_VALIDATE_BOOLEAN)?"YES":"NO";')"
echo "POST_SEARCH_GLOBAL_CLI=$("$PHP" -r 'echo filter_var(env("AIRBLUE_CLI_LIVE_ENABLED", false), FILTER_VALIDATE_BOOLEAN)?"YES":"NO";')"
assert_sandbox
echo "PHASE5_COMPLETE"
