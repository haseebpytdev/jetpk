#!/usr/bin/env bash
# Publish approved AI public assets from APP/public to the live web document root.
set -euo pipefail

APP="${APP:-/home/pkjetp/jetpk_app}"
WEB_ROOT="${WEB_ROOT:-/home/pkjetp/public_html}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# shellcheck source=ai-runtime-deploy-allowlist.sh
source "${SCRIPT_DIR}/ai-runtime-deploy-allowlist.sh"

PUBLISH_SUDO="${PUBLISH_SUDO:-auto}"
CHANGED_ONLY="${CHANGED_ONLY:-0}"
CURRENT_DEPLOYED_SHA="${CURRENT_DEPLOYED_SHA:-}"
AUTHORIZED_SHA="${AUTHORIZED_SHA:-}"

declare -A PUBLISH_SET=()
for rel in "${AI_RUNTIME_PUBLIC_ASSET_RELS[@]}"; do
  PUBLISH_SET["${rel}"]=1
done

if [[ "${CHANGED_ONLY}" == "1" && -n "${AUTHORIZED_SHA}" ]]; then
  mapfile -t CHANGED_RELS < <(
    CURRENT_DEPLOYED_SHA="${CURRENT_DEPLOYED_SHA}" AUTHORIZED_SHA="${AUTHORIZED_SHA}" REPO="${REPO:-$(cd "${SCRIPT_DIR}/../.." && pwd)}" \
      bash "${SCRIPT_DIR}/resolve-ai-runtime-changed-paths.sh" 2>/dev/null || true
  )
  if [[ ${#CHANGED_RELS[@]} -eq 0 ]]; then
    echo "PUBLIC_ASSET_PUBLISH=SKIP"
    echo "PUBLIC_ASSET_PUBLISH_REASON=no_changed_public_assets"
    exit 0
  fi
  NEEDS_PUBLISH=0
  for rel in "${CHANGED_RELS[@]}"; do
    [[ -n "${PUBLISH_SET[${rel}]:-}" ]] && NEEDS_PUBLISH=1
  done
  if [[ "${NEEDS_PUBLISH}" -eq 0 ]]; then
    echo "PUBLIC_ASSET_PUBLISH=SKIP"
    echo "PUBLIC_ASSET_PUBLISH_REASON=no_embed_assets_in_change_set"
    exit 0
  fi
fi

publish_one() {
  local rel="$1"
  local src="${APP}/${rel}"
  local web_rel="${rel#public/}"
  local dst="${WEB_ROOT}/${web_rel}"

  if [[ ! -f "${src}" ]]; then
    echo "PUBLIC_ASSET_PUBLISH_FAIL reason=missing_source rel=${rel}"
    return 1
  fi

  local dst_dir
  dst_dir="$(dirname "${dst}")"
  if [[ ! -d "${dst_dir}" ]]; then
    echo "PUBLIC_ASSET_PUBLISH_FAIL reason=missing_webroot_dir dir=${dst_dir}"
    return 1
  fi

  if [[ -w "${dst_dir}" && ( ! -e "${dst}" || -w "${dst}" ) ]]; then
    cp -f "${src}" "${dst}"
    echo "PUBLISHED ${rel} -> ${dst}"
    return 0
  fi

  case "${PUBLISH_SUDO}" in
    0|no|false)
      echo "PUBLIC_ASSET_PUBLISH_FAIL reason=destination_not_writable rel=${rel} owner=$(stat -c '%U:%G' "${dst_dir}" 2>/dev/null || echo unknown)"
      return 1
      ;;
  esac

  if ! command -v sudo >/dev/null 2>&1; then
    echo "PUBLIC_ASSET_PUBLISH_FAIL reason=sudo_unavailable rel=${rel}"
    return 1
  fi

  if ! sudo -n true >/dev/null 2>&1; then
    echo "PUBLIC_ASSET_PUBLISH_FAIL reason=sudo_requires_password rel=${rel}"
    echo "PUBLIC_ASSET_PUBLISH_HINT=run: sudo install -o pkjetp -g pkjetp -m 0644 ${src} ${dst}"
    return 1
  fi

  sudo install -o pkjetp -g pkjetp -m 0644 "${src}" "${dst}"
  echo "PUBLISHED_SUDO ${rel} -> ${dst}"
}

FAIL=0
for rel in "${AI_RUNTIME_PUBLIC_ASSET_RELS[@]}"; do
  if [[ "${CHANGED_ONLY}" == "1" && -n "${AUTHORIZED_SHA:-}" ]]; then
    if ! printf '%s\n' "${CHANGED_RELS[@]:-}" | grep -Fxq "${rel}"; then
      echo "SKIPPED_UNCHANGED_PUBLIC ${rel}"
      continue
    fi
  fi
  publish_one "${rel}" || FAIL=1
done

if [[ "${FAIL}" -eq 0 ]]; then
  echo "PUBLIC_ASSET_PUBLISH=PASS"
  exit 0
fi

echo "PUBLIC_ASSET_PUBLISH=FAIL"
exit 1
