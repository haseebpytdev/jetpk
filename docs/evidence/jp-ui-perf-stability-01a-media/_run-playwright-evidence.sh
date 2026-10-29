#!/usr/bin/env bash
set -euo pipefail

ROOT=/home/pkjetp/tmp/pr139-build-proof/repo/frontend
cd "$ROOT"

fuser -k 3019/tcp 2>/dev/null || true
fuser -k 18080/tcp 2>/dev/null || true
pkill -f pr139-responsive-evidence-mock-api.mjs 2>/dev/null || true
pkill -f pr139-evidence-server.mjs 2>/dev/null || true
sleep 2

rm -rf .next/cache
mkdir -p ../docs/evidence/jp-ui-perf-stability-01a-media

export PLAYWRIGHT_PORT=3019
export PR139_MOCK_API_PORT=18080
export LARAVEL_URL=http://127.0.0.1:18080
export NEXT_PUBLIC_LARAVEL_URL=http://127.0.0.1:18080
export OTA_ALLOW_CONTENT_FIXTURE=false
export NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES=false

npx playwright test -c playwright.pr139-responsive-evidence.config.ts 2>&1 | tee /home/pkjetp/tmp/pr139-build-proof/playwright.txt
echo "PLAYWRIGHT_EXIT=${PIPESTATUS[0]}"
ls -la ../docs/evidence/jp-ui-perf-stability-01a-media/08* || true

fuser -k 3019/tcp 2>/dev/null || true
fuser -k 18080/tcp 2>/dev/null || true
