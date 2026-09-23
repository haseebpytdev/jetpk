#!/usr/bin/env bash
# Sandbox tests for AI deploy component hardening (no production mutation).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SCRIPT_DIR="${ROOT}/scripts/jetpk"
TMP="$(mktemp -d)"
REPO="${TMP}/repo"
APP="${TMP}/app"
WEB="${TMP}/public_html"
BACKUP="${TMP}/backup"
trap 'rm -rf "${TMP}"' EXIT

fail() {
  echo "AI_DEPLOY_HARDENING_TEST=FAIL reason=$1"
  exit 1
}

init_repo() {
  rm -rf "${REPO}" "${APP}" "${WEB}"
  mkdir -p "${REPO}" "${APP}" "${WEB}/js" "${WEB}/css"
  git -C "${REPO}" init -q
  git -C "${REPO}" config user.email "deploy-test@jetpakistan.pk"
  git -C "${REPO}" config user.name "Deploy Test"
  mkdir -p "${REPO}/scripts/jetpk" "${REPO}/docs/deploy" "${REPO}/ai-lab-gateway"
  cp "${SCRIPT_DIR}/ai-runtime-deploy-allowlist.sh" "${REPO}/scripts/jetpk/"
  cp "${SCRIPT_DIR}/resolve-ai-runtime-changed-paths.sh" "${REPO}/scripts/jetpk/"
  cp "${SCRIPT_DIR}/publish-ai-public-assets.sh" "${REPO}/scripts/jetpk/"
  cp "${SCRIPT_DIR}/deploy-ai-runtime-protected.sh" "${REPO}/scripts/jetpk/"
  cp "${SCRIPT_DIR}/finalize-deploy-sha-marker.sh" "${REPO}/scripts/jetpk/" 2>/dev/null || true
  cp "${SCRIPT_DIR}/write-deploy-sha-marker.sh" "${REPO}/scripts/jetpk/" 2>/dev/null || true
  cp "${SCRIPT_DIR}/test-deploy-sha-marker.sh" "${REPO}/scripts/jetpk/" 2>/dev/null || true

  mkdir -p "${REPO}/app/Services/Ai" "${REPO}/public/js" "${REPO}/public/css"
  echo 'base gateway' > "${REPO}/ai-lab-gateway/server.py"
  echo 'base service' > "${REPO}/docs/deploy/jetpk-ai-lab-gateway.service"
  echo 'base ai' > "${REPO}/app/Services/Ai/marker.txt"
  echo 'body{}' > "${REPO}/public/css/ai-embed.css"
  echo 'console.log("embed");' > "${REPO}/public/js/ai-embed.js"
  git -C "${REPO}" add -A
  git -C "${REPO}" commit -q -m "base"
}

BASE_SHA="$(git -C "${REPO}" rev-parse HEAD 2>/dev/null || true)"

# Test A: unchanged root-owned simulated file is not copied; deploy succeeds.
init_repo
BASE_SHA="$(git -C "${REPO}" rev-parse HEAD)"
echo 'changed ai only' > "${REPO}/app/Services/Ai/marker.txt"
git -C "${REPO}" add app/Services/Ai/marker.txt
git -C "${REPO}" commit -q -m "change ai marker"
AUTH_SHA="$(git -C "${REPO}" rev-parse HEAD)"

mkdir -p "${APP}/ai-lab-gateway" "${APP}/docs/deploy" "${APP}/app/Services/Ai" "${APP}/public/js" "${APP}/public/css"
echo 'live gateway root-owned' > "${APP}/ai-lab-gateway/server.py"
echo 'live unit root-owned' > "${APP}/docs/deploy/jetpk-ai-lab-gateway.service"
echo 'live ai' > "${APP}/app/Services/Ai/marker.txt"
echo 'live embed js' > "${APP}/public/js/ai-embed.js"
echo 'live embed css' > "${APP}/public/css/ai-embed.css"
chmod 444 "${APP}/ai-lab-gateway/server.py" "${APP}/docs/deploy/jetpk-ai-lab-gateway.service"
printf '%s' "${BASE_SHA}" > "${APP}/.jetpk-runtime-sha"
mkdir -p "${APP}/storage/app"
printf '%s' "${BASE_SHA}" > "${APP}/storage/app/deploy-sha.txt"

OUT="$(
  AUTHORIZED_SHA="${AUTH_SHA}" CURRENT_DEPLOYED_SHA="${BASE_SHA}" APP="${APP}" REPO="${REPO}" \
    BACKUP="${TMP}/backup-a" REL="${TMP}/release-a" WEB_ROOT="${WEB}" \
    PHP="$(command -v php || echo false)" SKIP_LARAVEL_POST_DEPLOY=1 \
    bash "${REPO}/scripts/jetpk/deploy-ai-runtime-protected.sh" 2>&1 || true
)"
echo "${OUT}"
[[ "$(cat "${APP}/ai-lab-gateway/server.py")" == "live gateway root-owned" ]] || fail "A_root_owned_overwritten"
grep -q 'DEPLOYED app/Services/Ai/marker.txt' <<< "${OUT}" || fail "A_missing_changed_deploy"
grep -q 'PROTECTED_AI_DEPLOY=PASS' <<< "${OUT}" || fail "A_deploy_not_pass"

# Test B: changed writable file gets backup + copy.
[[ "$(cat "${APP}/app/Services/Ai/marker.txt")" == "changed ai only" ]] || fail "B_copy_missing"
[[ -f "${TMP}/backup-a/app/Services/Ai/marker.txt" ]] || fail "B_backup_missing"

# Test C: changed required non-writable file hard fails.
init_repo
BASE_SHA="$(git -C "${REPO}" rev-parse HEAD)"
echo 'mutated gateway' > "${REPO}/ai-lab-gateway/server.py"
git -C "${REPO}" add ai-lab-gateway/server.py
git -C "${REPO}" commit -q -m "change gateway"
AUTH_SHA="$(git -C "${REPO}" rev-parse HEAD)"
mkdir -p "${APP}/ai-lab-gateway"
echo 'live gateway' > "${APP}/ai-lab-gateway/server.py"
chmod 444 "${APP}/ai-lab-gateway/server.py"
printf '%s' "${BASE_SHA}" > "${APP}/.jetpk-runtime-sha"
OUT_C="$(
  AUTHORIZED_SHA="${AUTH_SHA}" CURRENT_DEPLOYED_SHA="${BASE_SHA}" APP="${APP}" REPO="${REPO}" \
    PHP="$(command -v php || echo false)" SKIP_LARAVEL_POST_DEPLOY=1 \
    bash "${REPO}/scripts/jetpk/deploy-ai-runtime-protected.sh" 2>&1 || true
)"
grep -q 'PROTECTED_DEPLOY_FAIL reason=changed_path_not_writable rel=ai-lab-gateway/server.py' <<< "${OUT_C}" \
  || fail "C_missing_hard_fail"

# Test D/E: embed asset publish required; failure reports PARTIAL.
init_repo
BASE_SHA="$(git -C "${REPO}" rev-parse HEAD)"
echo 'console.log("embed2");' > "${REPO}/public/js/ai-embed.js"
git -C "${REPO}" add public/js/ai-embed.js
git -C "${REPO}" commit -q -m "change embed js"
AUTH_SHA="$(git -C "${REPO}" rev-parse HEAD)"
mkdir -p "${APP}/public/js" "${APP}/public/css" "${APP}/app/Services/Ai"
echo 'old' > "${APP}/public/js/ai-embed.js"
echo 'old css' > "${APP}/public/css/ai-embed.css"
echo 'old web embed' > "${WEB}/js/ai-embed.js"
chmod 444 "${WEB}/js/ai-embed.js"
chmod 555 "${WEB}/js"
printf '%s' "${BASE_SHA}" > "${APP}/.jetpk-runtime-sha"
OUT_E="$(
  AUTHORIZED_SHA="${AUTH_SHA}" CURRENT_DEPLOYED_SHA="${BASE_SHA}" APP="${APP}" REPO="${REPO}" \
    WEB_ROOT="${WEB}" PHP="$(command -v php || echo false)" PUBLISH_SUDO=no SKIP_LARAVEL_POST_DEPLOY=1 \
    bash "${REPO}/scripts/jetpk/deploy-ai-runtime-protected.sh" 2>&1 || true
)"
echo "${OUT_E}" >&2
grep -q 'EMBED_DEPLOY_STATUS=PARTIAL' <<< "${OUT_E}" || fail "E_missing_partial_on_publish_fail"
grep -q 'PUBLIC_ASSET_PUBLISH=FAIL' <<< "${OUT_E}" || fail "E_missing_publish_fail"

# Test F: marker only updates via finalize on success (reuse existing test if present).
if [[ -f "${SCRIPT_DIR}/test-deploy-sha-marker.sh" ]]; then
  bash "${SCRIPT_DIR}/test-deploy-sha-marker.sh" >/dev/null || fail "F_marker_semantics"
fi

# Test G: unauthorized path rejected by resolve (not in allowlist output).
init_repo
BASE_SHA="$(git -C "${REPO}" rev-parse HEAD)"
mkdir -p "${REPO}/app/Evil"
echo 'nope' > "${REPO}/app/Evil/hack.php"
git -C "${REPO}" add app/Evil/hack.php
git -C "${REPO}" commit -q -m "evil"
AUTH_SHA="$(git -C "${REPO}" rev-parse HEAD)"
CHANGED="$(CURRENT_DEPLOYED_SHA="${BASE_SHA}" AUTHORIZED_SHA="${AUTH_SHA}" REPO="${REPO}" bash "${REPO}/scripts/jetpk/resolve-ai-runtime-changed-paths.sh" 2>/dev/null || true)"
grep -q 'app/Evil/hack.php' <<< "${CHANGED}" && fail "G_unauthorized_path_leaked"

# Test H: Embed-02 runtime registry/ops files remain in the protected deploy allowlist.
ALLOWLIST_FILE="${SCRIPT_DIR}/ai-runtime-deploy-allowlist.sh"
for required in \
  ai-assistant/knowledge \
  app/Console/Commands/AiEmbedKeyRotateCommand.php \
  app/Console/Commands/AiEmbedTenantStatusCommand.php \
  app/Console/Commands/AiEmbedTenantSyncJetPakistanCommand.php \
  app/Console/Commands/AiEmbedTenantUpsertCommand.php \
  app/Models/AiEmbedAuditEvent.php \
  app/Models/AiEmbedTenant.php \
  app/Models/AiEmbedTenantKey.php \
  app/Providers/AiEmbedServiceProvider.php \
  database/migrations/2026_09_23_140000_create_ai_embed_tenant_registry_tables.php
do
  grep -Fxq "  ${required}" "${ALLOWLIST_FILE}" || fail "H_missing_embed02_allowlist_path_${required//\//_}"
done

echo "AI_DEPLOY_HARDENING_TEST=PASS"
