#!/usr/bin/env bash
set -euo pipefail

ROOT=/home/pkjetp/tmp/pr139-build-proof/repo/frontend
cd "$ROOT"

fuser -k 18080/tcp 2>/dev/null || true
node scripts/pr139-responsive-evidence-mock-api.mjs >/tmp/pr139-mock-build.log 2>&1 &
MOCK_PID=$!
sleep 1

export LARAVEL_URL=http://127.0.0.1:18080
export NEXT_PUBLIC_LARAVEL_URL=http://127.0.0.1:18080
export NODE_OPTIONS=--max-old-space-size=4096
rm -rf .next
npm run build 2>&1 | tee /home/pkjetp/tmp/pr139-build-proof/build-with-mock.txt
echo "BUILD_EXIT=${PIPESTATUS[0]}"
echo "BUILD_ID=$(cat .next/BUILD_ID)"

kill "$MOCK_PID" 2>/dev/null || true
fuser -k 18080/tcp 2>/dev/null || true
