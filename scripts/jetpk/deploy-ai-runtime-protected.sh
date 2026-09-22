#!/usr/bin/env bash
# Protected Ask JetPakistan AI runtime deploy from AUTHORIZED_SHA.
# Laravel-only allowlist — does not rebuild Next or touch FlightSearch/SupplierProvider.
set -euo pipefail

AUTHORIZED_SHA="${AUTHORIZED_SHA:?AUTHORIZED_SHA is required}"
APP="${APP:-/home/pkjetp/jetpk_app}"
PHP="${PHP:-/usr/local/lsws/lsphp83/bin/php}"
REPO="${REPO:-/home/pkjetp/jetpk_git}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
REL="/home/pkjetp/releases/jetpk-ai-runtime-${AUTHORIZED_SHA}-${STAMP}"
BACKUP="/home/pkjetp/backups/jetpk-ai-runtime-${STAMP}"
OWNERSHIP="${APP}/scripts/jetpk/assert-runtime-ownership.sh"

mkdir -p "${BACKUP}" "${REL}"
echo "BACKUP_PATH=${BACKUP}"
echo "RELEASE_PATH=${REL}"
echo "AUTHORIZED_SHA=${AUTHORIZED_SHA}"

mkdir -p "${REPO}"
cd "${REPO}"
if [[ ! -d .git ]]; then
  echo "PROTECTED_DEPLOY_FAIL reason=missing_git_repo"
  exit 1
fi
git fetch --all --tags --prune || true
test "$(git cat-file -t "${AUTHORIZED_SHA}" 2>/dev/null || echo missing)" = "commit" \
  || { echo "PROTECTED_DEPLOY_FAIL reason=missing_AUTHORIZED_SHA"; exit 1; }

# Full AI allowlist (paths relative to repo root). Trees expanded via git archive pathspecs.
PATHSPECS=(
  app/Contracts/Ai
  app/Data/Ai
  app/Enums/CustomerQueryStatus.php
  app/Http/Controllers/Admin/AiAssistantStatusController.php
  app/Http/Controllers/Admin/AdminSettingsHubController.php
  app/Http/Controllers/Api/PublicAiAssistantController.php
  app/Http/Middleware/ApplyAiLabCanaryFaultHeader.php
  app/Models/AiAssistantSetting.php
  app/Models/AiConversation.php
  app/Models/AiHandoffAudit.php
  app/Models/AiMessage.php
  app/Models/CustomerQuery.php
  app/Providers/AiServiceProvider.php
  app/Services/Ai
  app/Services/PublicContent/PublicContentApiPresenter.php
  app/Support/Ai
  bootstrap/providers.php
  bootstrap/app.php
  config/ota.php
  config/ai_lab.php
  database/migrations/2026_09_01_010000_create_ai_conversations_tables.php
  database/migrations/2026_09_14_100000_create_ai_assistant_settings_table.php
  database/migrations/2026_09_14_200000_create_customer_queries_table.php
  resources/views/dashboard/admin/settings/ai-assistant.blade.php
  routes/web.php
  routes/admin.php
  scripts/jetpk/deploy-ai-runtime-protected.sh
  docs/deploy/jetpk-ai-lab-gateway.service
  ai-lab-gateway/server.py
  ai-lab-gateway/requirements.txt
)

git archive --format=tar "${AUTHORIZED_SHA}" -- "${PATHSPECS[@]}" | tar -x -C "${REL}"
printf '%s' "$(git rev-parse "${AUTHORIZED_SHA}")" > "${REL}/.jetpk-authorized-sha"

backup_and_copy() {
  local rel="$1"
  local src="${REL}/${rel}"
  local dst="${APP}/${rel}"
  if [[ ! -e "${src}" ]]; then
    echo "MISSING_IN_RELEASE ${rel}"
    return 1
  fi
  if [[ -e "${dst}" ]]; then
    mkdir -p "${BACKUP}/$(dirname "${rel}")"
    cp -a "${dst}" "${BACKUP}/${rel}"
  fi
  mkdir -p "$(dirname "${dst}")"
  if [[ -d "${src}" ]]; then
    rm -rf "${dst}"
    cp -a "${src}" "${dst}"
  else
    cp -f "${src}" "${dst}"
  fi
  echo "DEPLOYED ${rel}"
}

# Copy trees/files present in release
while IFS= read -r -d '' path; do
  rel="${path#${REL}/}"
  [[ "${rel}" == ".jetpk-authorized-sha" ]] && continue
  backup_and_copy "${rel}"
done < <(find "${REL}" -type f -print0)

printf '%s' "$(git rev-parse "${AUTHORIZED_SHA}")" > "${APP}/.jetpk-runtime-sha"
printf '%s' "$(git rev-parse "${AUTHORIZED_SHA}")" > "${APP}/.jetpk-authorized-sha"

if [[ -f "${OWNERSHIP}" ]]; then
  APP_ROOT="${APP}" RUNTIME_USER=pkjetp RUNTIME_GROUP=pkjetp MODE=normalize bash "${OWNERSHIP}" || true
fi

cd "${APP}"
"${PHP}" /usr/local/bin/composer dump-autoload -o --no-interaction 2>/dev/null \
  || "${PHP}" "$(command -v composer)" dump-autoload -o --no-interaction 2>/dev/null \
  || true
"${PHP}" artisan migrate --force --no-interaction
"${PHP}" artisan optimize:clear
"${PHP}" artisan config:cache
"${PHP}" artisan route:clear
"${PHP}" artisan view:cache || true

echo "RUNTIME_SHA=$(tr -d '\n' < "${APP}/.jetpk-runtime-sha")"

FINALIZE_SCRIPT="${REPO}/scripts/jetpk/finalize-deploy-sha-marker.sh"
MARKER_SCRIPT="${REPO}/scripts/jetpk/write-deploy-sha-marker.sh"
if [[ -f "${FINALIZE_SCRIPT}" ]]; then
  DEPLOY_SHA="${AUTHORIZED_SHA}" APP="${APP}" MARKER_SCRIPT="${MARKER_SCRIPT}" bash "${FINALIZE_SCRIPT}"
else
  echo "DEPLOY_COMPONENT_APPLIED=YES"
  echo "DEPLOY_SHA_MARKER=SKIP"
  echo "FINAL_DEPLOY_STATUS=PASS"
fi
echo "PROTECTED_AI_DEPLOY=PASS"
