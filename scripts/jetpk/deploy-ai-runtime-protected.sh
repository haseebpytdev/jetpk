#!/usr/bin/env bash
# Protected Ask JetPakistan AI runtime deploy from AUTHORIZED_SHA.
# Copies only authorized allowlist paths that changed since CURRENT_DEPLOYED_SHA.
# Publishes approved public AI assets to the live web document root when changed.
set -euo pipefail

AUTHORIZED_SHA="${AUTHORIZED_SHA:?AUTHORIZED_SHA is required}"
APP="${APP:-/home/pkjetp/jetpk_app}"
PHP="${PHP:-/usr/local/lsws/lsphp83/bin/php}"
REPO="${REPO:-/home/pkjetp/jetpk_git}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
REL="${REL:-/home/pkjetp/releases/jetpk-ai-runtime-${AUTHORIZED_SHA}-${STAMP}}"
BACKUP="${BACKUP:-/home/pkjetp/backups/jetpk-ai-runtime-${STAMP}}"
OWNERSHIP="${APP}/scripts/jetpk/assert-runtime-ownership.sh"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# shellcheck source=ai-runtime-deploy-allowlist.sh
source "${SCRIPT_DIR}/ai-runtime-deploy-allowlist.sh"

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
AUTHORIZED_SHA="$(git rev-parse "${AUTHORIZED_SHA}")"

CURRENT_DEPLOYED_SHA="${CURRENT_DEPLOYED_SHA:-}"
if [[ -z "${CURRENT_DEPLOYED_SHA}" && -f "${APP}/.jetpk-runtime-sha" ]]; then
  CURRENT_DEPLOYED_SHA="$(tr -d '\n' < "${APP}/.jetpk-runtime-sha")"
fi
echo "CURRENT_DEPLOYED_SHA=${CURRENT_DEPLOYED_SHA:-NONE}"

mapfile -t CHANGED_PATHS < <(
  CURRENT_DEPLOYED_SHA="${CURRENT_DEPLOYED_SHA}" AUTHORIZED_SHA="${AUTHORIZED_SHA}" REPO="${REPO}" APP="${APP}" \
    bash "${SCRIPT_DIR}/resolve-ai-runtime-changed-paths.sh" 2>/dev/null \
    | grep -v '^CURRENT_DEPLOYED_SHA=' || true
)

if [[ ${#CHANGED_PATHS[@]} -eq 0 ]]; then
  echo "CHANGED_PATH_COUNT=0"
  echo "DEPLOY_COMPONENT_APPLIED=SKIP"
  echo "EMBED_DEPLOY_STATUS=SKIP"
  PUBLIC_OUT="$(
    CHANGED_ONLY=1 CURRENT_DEPLOYED_SHA="${CURRENT_DEPLOYED_SHA}" AUTHORIZED_SHA="${AUTHORIZED_SHA}" \
      APP="${APP}" WEB_ROOT="${WEB_ROOT:-/home/pkjetp/public_html}" PUBLISH_SUDO="${PUBLISH_SUDO:-auto}" \
      bash "${SCRIPT_DIR}/publish-ai-public-assets.sh" 2>&1 || true
  )"
  echo "${PUBLIC_OUT}"
  if grep -q '^PUBLIC_ASSET_PUBLISH=FAIL' <<< "${PUBLIC_OUT}"; then
    echo "EMBED_DEPLOY_STATUS=PARTIAL"
    echo "FINAL_DEPLOY_STATUS=PARTIAL"
    exit 1
  fi
  FINALIZE_SCRIPT="${REPO}/scripts/jetpk/finalize-deploy-sha-marker.sh"
  MARKER_SCRIPT="${REPO}/scripts/jetpk/write-deploy-sha-marker.sh"
  if [[ -f "${FINALIZE_SCRIPT}" ]]; then
    DEPLOY_SHA="${AUTHORIZED_SHA}" APP="${APP}" MARKER_SCRIPT="${MARKER_SCRIPT}" bash "${FINALIZE_SCRIPT}"
  else
    echo "DEPLOY_SHA_MARKER=SKIP"
    echo "FINAL_DEPLOY_STATUS=PASS"
  fi
  echo "PROTECTED_AI_DEPLOY=PASS"
  exit 0
fi

echo "CHANGED_PATH_COUNT=${#CHANGED_PATHS[@]}"

declare -A CHANGED_SET=()
for rel in "${CHANGED_PATHS[@]}"; do
  [[ -n "${rel}" ]] && CHANGED_SET["${rel}"]=1
done

git archive --format=tar "${AUTHORIZED_SHA}" -- "${CHANGED_PATHS[@]}" | tar -x -C "${REL}"
printf '%s' "${AUTHORIZED_SHA}" > "${REL}/.jetpk-authorized-sha"

destination_is_writable() {
  local dst="$1"
  local dir
  dir="$(dirname "${dst}")"
  [[ -d "${dir}" ]] || return 1
  if [[ -e "${dst}" ]]; then
    [[ -w "${dst}" ]]
    return
  fi
  [[ -w "${dir}" ]]
}

backup_and_copy() {
  local rel="$1"
  local src="${REL}/${rel}"
  local dst="${APP}/${rel}"
  if [[ ! -e "${src}" ]]; then
    echo "MISSING_IN_RELEASE ${rel}"
    return 1
  fi
  if [[ -z "${CHANGED_SET[${rel}]:-}" ]]; then
    echo "SKIPPED_UNCHANGED ${rel}"
    return 0
  fi
  if ! destination_is_writable "${dst}"; then
    local owner="unknown"
    owner="$(stat -c '%U:%G %a' "${dst}" 2>/dev/null || stat -c '%U:%G %a' "$(dirname "${dst}")" 2>/dev/null || echo unknown)"
    echo "PROTECTED_DEPLOY_FAIL reason=changed_path_not_writable rel=${rel} target=${owner}"
    exit 1
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

DEPLOYED_COUNT=0
while IFS= read -r -d '' path; do
  rel="${path#${REL}/}"
  [[ "${rel}" == ".jetpk-authorized-sha" ]] && continue
  if [[ -n "${CHANGED_SET[${rel}]:-}" ]]; then
    backup_and_copy "${rel}"
    DEPLOYED_COUNT=$((DEPLOYED_COUNT + 1))
  else
    echo "SKIPPED_UNCHANGED ${rel}"
  fi
done < <(find "${REL}" -type f -print0)

echo "DEPLOYED_FILE_COUNT=${DEPLOYED_COUNT}"

printf '%s' "${AUTHORIZED_SHA}" > "${APP}/.jetpk-runtime-sha"
printf '%s' "${AUTHORIZED_SHA}" > "${APP}/.jetpk-authorized-sha"

if [[ -f "${OWNERSHIP}" ]]; then
  APP_ROOT="${APP}" RUNTIME_USER=pkjetp RUNTIME_GROUP=pkjetp MODE=normalize bash "${OWNERSHIP}" || true
fi

cd "${APP}"
if [[ "${SKIP_LARAVEL_POST_DEPLOY:-0}" != "1" ]]; then
  "${PHP}" /usr/local/bin/composer dump-autoload -o --no-interaction 2>/dev/null \
    || "${PHP}" "$(command -v composer)" dump-autoload -o --no-interaction 2>/dev/null \
    || true
  "${PHP}" artisan migrate --force --no-interaction
  "${PHP}" artisan optimize:clear
  "${PHP}" artisan config:cache
  "${PHP}" artisan route:clear
  "${PHP}" artisan view:cache || true
else
  echo "SKIP_LARAVEL_POST_DEPLOY=YES"
fi

echo "RUNTIME_SHA=$(tr -d '\n' < "${APP}/.jetpk-runtime-sha")"

PUBLIC_OUT="$(
  CHANGED_ONLY=1 CURRENT_DEPLOYED_SHA="${CURRENT_DEPLOYED_SHA}" AUTHORIZED_SHA="${AUTHORIZED_SHA}" \
    APP="${APP}" WEB_ROOT="${WEB_ROOT:-/home/pkjetp/public_html}" PUBLISH_SUDO="${PUBLISH_SUDO:-auto}" \
    bash "${SCRIPT_DIR}/publish-ai-public-assets.sh" 2>&1 || true
)"
echo "${PUBLIC_OUT}"

EMBED_CHANGED=0
for rel in "${AI_RUNTIME_PUBLIC_ASSET_RELS[@]}"; do
  [[ -n "${CHANGED_SET[${rel}]:-}" ]] && EMBED_CHANGED=1
done

EMBED_DEPLOY_STATUS=SKIP
if [[ "${EMBED_CHANGED}" -eq 1 ]]; then
  if grep -q '^PUBLIC_ASSET_PUBLISH=PASS' <<< "${PUBLIC_OUT}"; then
    EMBED_DEPLOY_STATUS=PASS
  else
    EMBED_DEPLOY_STATUS=PARTIAL
  fi
fi
echo "EMBED_DEPLOY_STATUS=${EMBED_DEPLOY_STATUS}"

FINALIZE_SCRIPT="${REPO}/scripts/jetpk/finalize-deploy-sha-marker.sh"
MARKER_SCRIPT="${REPO}/scripts/jetpk/write-deploy-sha-marker.sh"
FINALIZE_OUT=""
if [[ -f "${FINALIZE_SCRIPT}" ]]; then
  FINALIZE_OUT="$(
    DEPLOY_SHA="${AUTHORIZED_SHA}" APP="${APP}" MARKER_SCRIPT="${MARKER_SCRIPT}" \
      bash "${FINALIZE_SCRIPT}"
  )"
  echo "${FINALIZE_OUT}"
else
  echo "DEPLOY_COMPONENT_APPLIED=YES"
  echo "DEPLOY_SHA_MARKER=SKIP"
  echo "FINAL_DEPLOY_STATUS=PASS"
fi

if [[ "${EMBED_DEPLOY_STATUS}" == "PARTIAL" ]] && ! grep -q '^FINAL_DEPLOY_STATUS=PARTIAL' <<< "${FINALIZE_OUT}"; then
  echo "FINAL_DEPLOY_STATUS=PARTIAL"
fi

echo "PROTECTED_AI_DEPLOY=PASS"
