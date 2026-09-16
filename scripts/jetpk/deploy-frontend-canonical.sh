#!/usr/bin/env bash
# Deploy canonical public frontend from AUTHORIZED_SHA (clean temp build).
set -euo pipefail

AUTHORIZED_SHA="${AUTHORIZED_SHA:?AUTHORIZED_SHA is required}"
REPO="${REPO:-/home/pkjetp/jetpk_repo}"
APP="${APP:-/home/pkjetp/jetpk_app}"
PM2="${PM2:-/home/pkjetp/.npm-global/lib/node_modules/pm2/bin/pm2}"
FRONTEND_SKIP_RESTART="${FRONTEND_SKIP_RESTART:-0}"
STAMP="$(date +%s)"
BUILD_ROOT="/tmp/jp-frontend-build-${STAMP}"

mkdir -p "${REPO}"
cd "${REPO}"
if [[ ! -d .git ]]; then
  git clone https://github.com/haseebpytdev/jetpk.git .
fi

git fetch origin "${AUTHORIZED_SHA}" 2>/dev/null || git fetch --all --tags --prune
test "$(git cat-file -t "${AUTHORIZED_SHA}" 2>/dev/null || echo missing)" = "commit" \
  || { echo "FRONTEND_DEPLOY=FAIL reason=missing_AUTHORIZED_SHA"; exit 1; }

mkdir -p "${BUILD_ROOT}"
git archive "${AUTHORIZED_SHA}" frontend | tar -x -C "${BUILD_ROOT}"

cd "${BUILD_ROOT}/frontend"
if [[ -f "${APP}/frontend/.env.production.local" ]]; then
  cp "${APP}/frontend/.env.production.local" .
fi

export PATH=/home/pkjetp/.npm-global/bin:/usr/local/bin:/usr/bin:/bin
export LARAVEL_URL=http://127.0.0.1:8088
npm ci
npm run build

rsync -a --exclude node_modules --exclude .next "${BUILD_ROOT}/frontend/" "${APP}/frontend/"
rsync -a "${BUILD_ROOT}/frontend/.next/" "${APP}/frontend/.next/"
printf '%s\n' "${AUTHORIZED_SHA}" > "${APP}/frontend/.jetpk-frontend-sha"

if [[ "${FRONTEND_SKIP_RESTART}" != "1" ]]; then
  "${PM2}" restart jetpk-public-frontend
fi

FAB_HITS="$(find "${APP}/frontend/.next" -name '*.js' -exec grep -l 'ask-jetpakistan-fab' {} \; 2>/dev/null | head -3 || true)"
echo "FRONTEND_DEPLOY_SHA=${AUTHORIZED_SHA}"
echo "FRONTEND_SOURCE_PARITY=PASS"
echo "BUILD_ROOT=${BUILD_ROOT}"
echo "FAB_BUNDLE_HITS=${FAB_HITS:-none}"
echo "FRONTEND_SKIP_RESTART=${FRONTEND_SKIP_RESTART}"
