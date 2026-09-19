#!/bin/bash
set -e
APP=/home/pkjetp/jetpk_app
echo "PUBLIC_BUILD_ID=$(cat "$APP/frontend/.next/BUILD_ID" 2>/dev/null || echo MISSING)"
echo "DASHBOARD_BUILD_ID=$(cat "$APP/dashboard/.next/BUILD_ID" 2>/dev/null || echo MISSING)"
if [ -d "$APP/.git" ]; then
  echo "PRODUCTION_RUNTIME_SHA=$(git -C "$APP" rev-parse HEAD)"
elif [ -f "$APP/CODE_SHA" ]; then
  echo "PRODUCTION_RUNTIME_SHA=$(cat "$APP/CODE_SHA")"
elif [ -f "$APP/CURRENT_SHA" ]; then
  echo "PRODUCTION_RUNTIME_SHA=$(cat "$APP/CURRENT_SHA")"
else
  echo "PRODUCTION_RUNTIME_SHA=UNKNOWN"
  ls "$APP" | head -n 40
fi
if [ -f "$APP/frontend/.next/BUILD_ID" ]; then
  echo "PUBLIC_BUILD_MTIME=$(stat -c %y "$APP/frontend/.next/BUILD_ID" 2>/dev/null || true)"
fi
if [ -f "$APP/dashboard/.next/BUILD_ID" ]; then
  echo "DASHBOARD_BUILD_MTIME=$(stat -c %y "$APP/dashboard/.next/BUILD_ID" 2>/dev/null || true)"
fi
# Prefer deploy stamp / marker if present
for f in "$APP/DEPLOY_STAMP" "$APP/RUNTIME_MARKER" "$APP/frontend/RUNTIME_MARKER"; do
  if [ -f "$f" ]; then echo "MARKER_FILE=$f"; cat "$f"; fi
done
