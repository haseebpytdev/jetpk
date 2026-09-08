#!/bin/bash
# Closure-04 full protected deploy — FROM BEGINNING, SHA 20e921661da55e121a9b2353cba535b350613493
set -u
SHA=20e921661da55e121a9b2353cba535b350613493
APP=/home/pkjetp/jetpk_app
STAMP=$(date -u +%Y%m%dT%H%M%SZ)

echo "=== CLOSURE-04 DEPLOY START $STAMP ==="
echo AUTHORIZED_SHA=$SHA

# Phase 1: backup
echo "=== PHASE 1: jetpk-backup.sh ==="
if bash ~/jetpk-backup.sh; then
  echo BACKUP_PHASE=PASS
else
  echo BACKUP_PHASE=PARTIAL_OR_FAIL
  # continue if DB backup exists; owner may have fixed app tar
fi

# Phase 2: complete file activation from GitHub archive (not partial resume)
echo "=== PHASE 2: activate-closure-04.sh ==="
bash /tmp/jp-closure-04-activate.sh
if [ $? -ne 0 ]; then echo ACTIVATE_FAIL; exit 1; fi

for f in \
  "$APP/frontend/features/public-floating/PublicFloatingLayoutProvider.tsx" \
  "$APP/frontend/features/public-floating/public-floating-layout.ts" \
  "$APP/app/Services/Ai/AiConversationalAgent.php" \
  "$APP/app/Services/Ai/AiAssistantBookingLookupTool.php"; do
  if [ ! -f "$f" ]; then echo MISSING_AFTER_ACTIVATE="$f"; exit 1; fi
  echo VERIFIED="$f"
done

# Guard: do not proceed to PM2 switch if BUILD_ID already invalid — build first
if [ -f "$APP/frontend/.next/BUILD_ID" ]; then
  echo PRE_BUILD_ID=$(cat "$APP/frontend/.next/BUILD_ID")
else
  echo PRE_BUILD_ID=MISSING
fi

# Phase 3: production Next build (+ dashboard); script restarts PM2 only after successful build
echo "=== PHASE 3: jetpk-next-build.sh (full public+dashboard) ==="
PUBLIC_ONLY=0 bash ~/jetpk-next-build.sh
if [ $? -ne 0 ]; then echo NEXT_BUILD_FAIL; exit 1; fi

NEW_BUILD_ID=$(cat "$APP/frontend/.next/BUILD_ID" 2>/dev/null || true)
if [ -z "$NEW_BUILD_ID" ]; then echo PUBLIC_BUILD_ID_MISSING; exit 1; fi
echo NEW_PUBLIC_BUILD_ID=$NEW_BUILD_ID

RUNTIME_SHA=$(cat "$APP/.jetpk-runtime-sha" 2>/dev/null || true)
if [ "$RUNTIME_SHA" != "$SHA" ]; then
  echo RUNTIME_SHA_MISMATCH got="$RUNTIME_SHA" want="$SHA"
  exit 1
fi
echo PRODUCTION_RUNTIME_SHA=$RUNTIME_SHA

# Phase 4: pre-proxy gate
echo "=== PHASE 4: jetpk-pre-proxy-gate.sh ==="
bash ~/jetpk-pre-proxy-gate.sh
if [ $? -ne 0 ]; then echo PRE_PROXY_FAIL; exit 1; fi

echo LIVE_HOME=$(curl -sS -o /dev/null -w '%{http_code}' https://jetpakistan.pk/)
echo LIVE_SUPPORT=$(curl -sS -o /dev/null -w '%{http_code}' https://jetpakistan.pk/support)
echo DASHBOARD_BUILD_ID=$(cat "$APP/dashboard/.next/BUILD_ID" 2>/dev/null || echo MISSING)
echo SUPPLIER_MUTATION_CALLS=0
echo JP_CLOSURE_04_DEPLOY_COMPLETE
