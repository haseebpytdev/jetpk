#!/usr/bin/env bash
# Mirror Wave-7 tesseract assets into public_html (HTTPS edge serves webroot).
set -euo pipefail
APP="${APP_ROOT:-/home/pkjetp/jetpk_app}"
WEB="${WEB_ROOT:-/home/pkjetp/public_html}"
SRC="$APP/frontend/public/tesseract"
if [ ! -d "$SRC" ]; then
  echo "TESSERACT_SRC_MISSING=$SRC"
  exit 1
fi
mkdir -p "$WEB/tesseract"
if command -v rsync >/dev/null 2>&1; then
  rsync -a "$SRC/" "$WEB/tesseract/"
else
  cp -a "$SRC/." "$WEB/tesseract/"
fi
chown -R pkjetp:pkjetp "$WEB/tesseract" 2>/dev/null || true
echo "TESSERACT_PUBLIC_HTML_MIRROR=OK"
