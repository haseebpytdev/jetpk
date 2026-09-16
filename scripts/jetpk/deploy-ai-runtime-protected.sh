#!/usr/bin/env bash
# Protected AI runtime deploy from AUTHORIZED_SHA — no SCP-only source.
set -euo pipefail

AUTHORIZED_SHA="${AUTHORIZED_SHA:?AUTHORIZED_SHA is required}"
APP="${APP:-/home/pkjetp/jetpk_app}"
PHP="${PHP:-/usr/local/lsws/lsphp83/bin/php}"
PM2="${PM2:-/home/pkjetp/.npm-global/lib/node_modules/pm2/bin/pm2}"
REPO="${REPO:-/home/pkjetp/jetpk_repo}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
REL="/home/pkjetp/releases/jetpk-ai-runtime-${AUTHORIZED_SHA}-${STAMP}"
OWNERSHIP="${APP}/scripts/jetpk/assert-runtime-ownership.sh"
BACKUP="/home/pkjetp/backups/jetpk-ai-runtime-${STAMP}"

mkdir -p "${BACKUP}" "${REL}"
for path in bootstrap/providers.php bootstrap/app.php app/Providers/AiServiceProvider.php app/Providers/AppServiceProvider.php app/Services/PublicContent/PublicContentApiPresenter.php .jetpk-runtime-sha .jetpk-authorized-sha; do
  if [[ -f "${APP}/${path}" ]]; then
    mkdir -p "${BACKUP}/$(dirname "${path}")"
    cp -a "${APP}/${path}" "${BACKUP}/${path}"
  fi
done
echo "BACKUP_PATH=${BACKUP}"

mkdir -p "${REPO}"
cd "${REPO}"
if [[ -d .git ]]; then
  git fetch jetpk fix/jp-ai-closure-ls12-leads 2>/dev/null || git fetch origin fix/jp-ai-closure-ls12-leads 2>/dev/null || git fetch --all
else
  git clone https://github.com/haseebpytdev/jetpk.git .
fi
git checkout -f "${AUTHORIZED_SHA}"
test "$(git rev-parse HEAD)" = "$(git rev-parse "${AUTHORIZED_SHA}")"

RUNTIME_FILES=(
  app/Providers/AiServiceProvider.php
  app/Providers/AppServiceProvider.php
  app/Http/Controllers/Api/PublicAiAssistantController.php
  app/Services/Ai/AiChatOrchestrator.php
  app/Services/PublicContent/PublicContentApiPresenter.php
  bootstrap/providers.php
  bootstrap/app.php
  config/ota.php
  config/ai_lab.php
  routes/web.php
  scripts/verify-ai-runtime-after-seo-activate.sh
  scripts/jetpk/deploy-seo-phase2-activate-allowlist.sh
  scripts/jetpk/test-seo-activate-ai-runtime-guard.sh
)

git archive --format=tar "${AUTHORIZED_SHA}" -- "${RUNTIME_FILES[@]}" | tar -x -C "${REL}"
printf '%s' "$(git rev-parse "${AUTHORIZED_SHA}")" > "${REL}/.jetpk-authorized-sha"

copy_file() {
  local rel="$1"
  mkdir -p "${APP}/$(dirname "${rel}")"
  cp -f "${REL}/${rel}" "${APP}/${rel}"
  echo "DEPLOYED ${rel}"
}

for rel in "${RUNTIME_FILES[@]}"; do
  copy_file "${rel}"
done

printf '%s' "$(git rev-parse "${AUTHORIZED_SHA}")" > "${APP}/.jetpk-runtime-sha"
printf '%s' "$(git rev-parse "${AUTHORIZED_SHA}")" > "${APP}/.jetpk-authorized-sha"

if [[ -f "${OWNERSHIP}" ]]; then
  APP_ROOT="${APP}" RUNTIME_USER=pkjetp RUNTIME_GROUP=pkjetp MODE=normalize bash "${OWNERSHIP}"
fi

cd "${APP}"
"${PHP}" artisan optimize:clear
"${PHP}" artisan config:cache
"${PHP}" artisan route:clear
"${PHP}" artisan view:clear
"${PHP}" artisan view:cache

# LiteSpeed PHP opcache refresh (graceful reload preferred).
if command -v /usr/local/lsws/bin/lswsctrl >/dev/null 2>&1; then
  /usr/local/lsws/bin/lswsctrl restart >/dev/null 2>&1 || true
fi

APP="${APP}" PHP="${PHP}" bash "${APP}/scripts/verify-ai-runtime-after-seo-activate.sh"

echo "PROTECTED_DEPLOY_TEST=PASS"
echo "AUTHORIZED_SHA=$(git rev-parse "${AUTHORIZED_SHA}")"
echo "RELEASE_PATH=${REL}"
echo "BACKUP_PATH=${BACKUP}"
