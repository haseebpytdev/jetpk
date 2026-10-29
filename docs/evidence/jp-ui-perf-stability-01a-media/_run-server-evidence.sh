#!/usr/bin/env bash
set -euo pipefail

ROOT=/home/pkjetp/tmp/pr139-build-proof/repo/frontend
cd "$ROOT"

fuser -k 3019/tcp 2>/dev/null || true
fuser -k 18080/tcp 2>/dev/null || true
pkill -f pr139-responsive-evidence-mock-api.mjs 2>/dev/null || true
pkill -f pr139-evidence-server.mjs 2>/dev/null || true
sleep 2

# Bust build-time unstable_cache homepage payload (1h TTL) so mock CMS is visible.
rm -rf .next/cache

node scripts/pr139-responsive-evidence-mock-api.mjs >/tmp/pr139-mock.log 2>&1 &
MOCK_PID=$!
sleep 1
curl -fsS http://127.0.0.1:18080/api/public/content/homepage >/tmp/pr139-mock-homepage.json
echo "MOCK_BYTES=$(wc -c </tmp/pr139-mock-homepage.json)"

export LARAVEL_URL=http://127.0.0.1:18080
export NEXT_PUBLIC_LARAVEL_URL=http://127.0.0.1:18080
export OTA_ALLOW_CONTENT_FIXTURE=false
export NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES=false
export PLAYWRIGHT_PORT=3019
export PR139_MOCK_API_PORT=18080

node scripts/pr139-evidence-server.mjs >/tmp/pr139-next.log 2>&1 &
NEXT_PID=$!

HTML_OK=0
for _ in $(seq 1 90); do
  if curl -fsS http://127.0.0.1:3019/ >/tmp/pr139-home.html 2>/dev/null; then
    HTML_OK=1
    break
  fi
  sleep 1
done

echo "MOCK_PID=$MOCK_PID NEXT_PID=$NEXT_PID HTML_OK=$HTML_OK"
echo "NEXT_LOG_HEAD=$(head -8 /tmp/pr139-next.log | tr '\n' ' | ')"
if [[ "$HTML_OK" -ne 1 ]]; then
  echo SSR_PROBE_FAILED
  tail -80 /tmp/pr139-next.log || true
  exit 1
fi

echo "HTML_BYTES=$(wc -c </tmp/pr139-home.html)"
grep -oE 'rel=["'\'']preload["'\''][^>]*>' /tmp/pr139-home.html | head -20 || echo NO_PRELOAD
grep -oE '_evidence/pr139-responsive/[^"[:space:]]+' /tmp/pr139-home.html | sort -u | head -40 || echo NO_EVIDENCE_URLS
grep -oE 'Responsive media proof|PR139 evidence|hero-pakistan|Explore the world' /tmp/pr139-home.html | sort -u || true
grep -c 'homepage-hero-responsive-picture\|data-responsive-cms' /tmp/pr139-home.html || true
cp /tmp/pr139-home.html /home/pkjetp/tmp/pr139-build-proof/home.html

if [[ "${KEEP_SERVERS:-0}" != "1" ]]; then
  kill "$NEXT_PID" "$MOCK_PID" 2>/dev/null || true
  fuser -k 3019/tcp 2>/dev/null || true
  fuser -k 18080/tcp 2>/dev/null || true
else
  echo "SERVERS_KEPT=1"
  echo "$MOCK_PID" >/tmp/pr139-mock.pid
  echo "$NEXT_PID" >/tmp/pr139-next.pid
fi
