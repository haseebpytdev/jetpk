#!/usr/bin/env bash
# Write component-specific deploy SHA markers after a SUCCESSFUL production deploy.
# Must not be called before deploy health gates pass.
#
# DEPLOY_COMPONENT=
#   application (default) → storage/app/deploy-application-sha.txt
#                           + legacy storage/app/deploy-sha.txt (compat)
#   frontend              → storage/app/deploy-frontend-sha.txt
#                           (+ frontend/.jetpk-frontend-sha if present path is caller-owned)
#   dashboard             → storage/app/deploy-dashboard-sha.txt
#
# Frontend/dashboard deploys MUST NOT overwrite deploy-application-sha.txt or
# the legacy deploy-sha.txt application provenance marker.
set -euo pipefail

APP="${APP:-/home/pkjetp/jetpk_app}"
DEPLOY_SHA="${DEPLOY_SHA:-${AUTHORIZED_SHA:-}}"
DEPLOY_COMPONENT="${DEPLOY_COMPONENT:-application}"

if [[ -z "${DEPLOY_SHA}" ]]; then
  echo "DEPLOY_SHA_MARKER_FAIL reason=missing_DEPLOY_SHA"
  exit 1
fi

if [[ ! -d "${APP}" ]]; then
  echo "DEPLOY_SHA_MARKER_FAIL reason=missing_APP_ROOT"
  exit 1
fi

case "${DEPLOY_COMPONENT}" in
  application|frontend|dashboard) ;;
  *)
    echo "DEPLOY_SHA_MARKER_FAIL reason=invalid_DEPLOY_COMPONENT"
    exit 1
    ;;
esac

MARKER_DIR="${APP}/storage/app"
mkdir -p "${MARKER_DIR}"

FULL_SHA="$(cd "${APP}" 2>/dev/null && git -C "${APP}" rev-parse "${DEPLOY_SHA}" 2>/dev/null || true)"
if [[ -z "${FULL_SHA}" ]]; then
  if [[ "${#DEPLOY_SHA}" -lt 40 ]]; then
    echo "DEPLOY_SHA_MARKER_FAIL reason=unresolvable_abbrev_sha"
    exit 1
  fi
  FULL_SHA="${DEPLOY_SHA}"
fi

write_marker() {
  local path="$1"
  local tmp="${path}.tmp.$$"
  printf '%s\n' "${FULL_SHA}" > "${tmp}"
  mv -f "${tmp}" "${path}"
  echo "DEPLOY_SHA_MARKER_WRITTEN=${FULL_SHA}"
  echo "DEPLOY_SHA_MARKER_PATH=${path}"
}

COMPONENT_FILE="${MARKER_DIR}/deploy-${DEPLOY_COMPONENT}-sha.txt"
write_marker "${COMPONENT_FILE}"

if [[ "${DEPLOY_COMPONENT}" == "application" ]]; then
  # Legacy readers: keep deploy-sha.txt aligned with application runtime only.
  write_marker "${MARKER_DIR}/deploy-sha.txt"
  echo "DEPLOY_SHA_LEGACY_COMPAT=application"
else
  echo "DEPLOY_SHA_LEGACY_COMPAT=unchanged"
fi

echo "DEPLOY_COMPONENT=${DEPLOY_COMPONENT}"
