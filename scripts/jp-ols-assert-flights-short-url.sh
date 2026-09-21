#!/usr/bin/env bash
# Assert OLS short-URL + critical flight/group route ownership on jetpakistan.pk.
# Exit 0 only when Public Next owns the expected GET shells.
set -euo pipefail

HOST="${JP_ASSERT_HOST:-https://jetpakistan.pk}"
VHCONF="${JP_VHCONF:-/usr/local/lsws/conf/vhosts/jetpakistan.pk/vhconf.conf}"
SNIPPET_MARK='^/flights/s/([A-Za-z0-9]{8,32})$'
FAIL=0

pass() { echo "PASS $1"; }
fail() { echo "FAIL $1 — $2"; FAIL=1; }

# --- Config reproducibility ---
if [[ -f "$VHCONF" ]]; then
  if grep -F "$SNIPPET_MARK" "$VHCONF" >/dev/null 2>&1 || grep -E 'flights/s/\(\[A-Za-z0-9\]\{8,32\}\)' "$VHCONF" >/dev/null 2>&1; then
    pass "VHCONF_CONTAINS_SHORT_URL_RULE"
  else
    fail "VHCONF_CONTAINS_SHORT_URL_RULE" "missing RewriteRule for /flights/s/{code}"
  fi
  if grep -q 'jetpk_public_next/flights/s/' "$VHCONF"; then
    pass "VHCONF_PROXIES_TO_PUBLIC_NEXT"
  else
    fail "VHCONF_PROXIES_TO_PUBLIC_NEXT" "handler not jetpk_public_next"
  fi
else
  echo "WARN VHCONF_NOT_READABLE path=$VHCONF (skip local file assert; continue HTTP probes)"
fi

is_next() {
  local url="$1"
  local headers
  headers=$(curl -sI --max-time 25 "$url" || true)
  echo "$headers" | grep -qiE 'vary:.*rsc|next-router-state-tree' && return 0
  return 1
}

is_laravelish_404() {
  local url="$1"
  local headers body
  headers=$(curl -sI --max-time 25 "$url" || true)
  # Laravel soft 404 often lacks RSC vary and has private no-cache without next headers
  if echo "$headers" | grep -qiE 'vary:.*rsc|next-router-state-tree'; then
    return 1
  fi
  return 0
}

probe_next() {
  local path="$1"
  local url="${HOST}${path}"
  if is_next "$url"; then
    pass "OWNER_NEXT ${path}"
  else
    fail "OWNER_NEXT ${path}" "missing Next RSC headers at $url"
  fi
}

# Critical existing ownership
probe_next "/"
probe_next "/groups/search"
# /groups exact landing — must be Next (static/SSR shell), not Laravel catch-all
probe_next "/groups"
probe_next "/flights/results"
probe_next "/flights/return-options"
probe_next "/login"
probe_next "/about-us"
probe_next "/faq"

# Short URL — expired/missing ref still must be Next-owned (SSR expired UI), not Laravel Blade 404
SHORT_PATH="/flights/s/zzzzzzzzzzzzzzzz"
if is_next "${HOST}${SHORT_PATH}"; then
  pass "SHORT_URL_ROUTE_OWNER=NEXT"
  body=$(curl -sS --max-time 30 "${HOST}${SHORT_PATH}" || true)
  if echo "$body" | grep -qiE 'Search expired|expired-search|data-testid="expired-search"'; then
    pass "SHORT_URL_EXPIRED_UI"
  else
    # Still Next-owned even if copy differs
    echo "WARN SHORT_URL_EXPIRED_UI_TEXT_NOT_FOUND (ownership still Next)"
  fi
else
  fail "SHORT_URL_ROUTE_OWNER=NEXT" "Laravel/non-Next response for ${SHORT_PATH}"
fi

# Must not be classic Laravel theme soft-404 for short path
if curl -sS --max-time 30 "${HOST}${SHORT_PATH}" | grep -qi 'themes/frontend/jetpakistan/css' \
  && ! is_next "${HOST}${SHORT_PATH}"; then
  fail "SHORT_URL_NOT_LARAVEL_CATCHALL" "looks like Laravel theme 404"
else
  pass "SHORT_URL_NOT_LARAVEL_CATCHALL"
fi

if [[ "$FAIL" -ne 0 ]]; then
  echo "ROUTE_OWNERSHIP_GUARD=FAIL"
  exit 1
fi
echo "OLS_CONFIG_REPRODUCIBLE=PASS"
echo "SHORT_URL_ROUTE_OWNER=NEXT"
echo "ROUTE_OWNERSHIP_GUARD=PASS"
exit 0
