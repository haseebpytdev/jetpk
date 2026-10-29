#!/usr/bin/env bash
set -euo pipefail

# Isolated Linux recert for PR139 fixture cleanup. Does not touch production app root.
SCRATCH=/home/pkjetp/tmp/pr139-cleanup-proof
SHA="${1:?usage: $0 <branch-or-sha>}"
rm -rf "$SCRATCH"
mkdir -p "$SCRATCH"
cd "$SCRATCH"
git clone --depth 1 --branch perf/jp-ui-perf-stability-01a-media https://github.com/haseebpytdev/jetpk.git repo
cd repo
git fetch --depth 1 origin "$SHA"
git checkout -f "$SHA"
echo "CHECKOUT=$(git rev-parse HEAD)"

# Ensure fixtures are not tracked in this tree
if git ls-files -- 'frontend/public/_evidence/**' | grep -q .; then
  echo "PUBLIC_EVIDENCE_FIXTURES_TRACKED_AFTER=YES"
  exit 1
fi
echo "PUBLIC_EVIDENCE_FIXTURES_TRACKED_AFTER=NO"

cd frontend
npm ci
export NODE_OPTIONS=--max-old-space-size=4096
export LARAVEL_URL=http://127.0.0.1:18080
export NEXT_PUBLIC_LARAVEL_URL=http://127.0.0.1:18080

npm run typecheck 2>&1 | tee /tmp/pr139-cleanup-typecheck.txt
echo "TYPECHECK_EXIT=${PIPESTATUS[0]}"
npm run lint 2>&1 | tee /tmp/pr139-cleanup-lint.txt
echo "LINT_EXIT=${PIPESTATUS[0]}"
rm -rf .next
# Start mock only during rebuild so homepage data cache can miss cleanly later
node scripts/pr139-responsive-evidence-mock-api.mjs >/tmp/pr139-mock-build.log 2>&1 &
MOCK_BUILD_PID=$!
sleep 1
npm run build 2>&1 | tee /tmp/pr139-cleanup-build.txt
echo "BUILD_EXIT=${PIPESTATUS[0]}"
kill "$MOCK_BUILD_PID" 2>/dev/null || true
fuser -k 18080/tcp 2>/dev/null || true

npx playwright install chromium
bash ../docs/evidence/jp-ui-perf-stability-01a-media/_run-playwright-evidence.sh
cp /tmp/pr139-playwright.txt /tmp/pr139-cleanup-playwright.txt || true
ls -la ../docs/evidence/jp-ui-perf-stability-01a-media/08* || true
test ! -d public/_evidence/pr139-responsive
echo "RECERT_COMPLETE=$(git rev-parse HEAD)"
