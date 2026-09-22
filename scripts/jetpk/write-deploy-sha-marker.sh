#!/usr/bin/env bash
# Write storage/app/deploy-sha.txt after a SUCCESSFUL canonical production deploy.
# Must not be called before deploy health gates pass.
set -euo pipefail

APP="${APP:-/home/pkjetp/jetpk_app}"
DEPLOY_SHA="${DEPLOY_SHA:-${AUTHORIZED_SHA:-}}"

if [[ -z "${DEPLOY_SHA}" ]]; then
  echo "DEPLOY_SHA_MARKER_FAIL reason=missing_DEPLOY_SHA"
  exit 1
fi

if [[ ! -d "${APP}" ]]; then
  echo "DEPLOY_SHA_MARKER_FAIL reason=missing_APP_ROOT"
  exit 1
fi

MARKER_DIR="${APP}/storage/app"
MARKER_FILE="${MARKER_DIR}/deploy-sha.txt"
mkdir -p "${MARKER_DIR}"

FULL_SHA="$(cd "${APP}" 2>/dev/null && git -C "${APP}" rev-parse "${DEPLOY_SHA}" 2>/dev/null || true)"
if [[ -z "${FULL_SHA}" ]]; then
  # Production app tree may not be a git checkout; accept explicit full SHA.
  if [[ "${#DEPLOY_SHA}" -lt 40 ]]; then
    echo "DEPLOY_SHA_MARKER_FAIL reason=unresolvable_abbrev_sha"
    exit 1
  fi
  FULL_SHA="${DEPLOY_SHA}"
fi

TMP="${MARKER_FILE}.tmp.$$"
printf '%s\n' "${FULL_SHA}" > "${TMP}"
mv -f "${TMP}" "${MARKER_FILE}"

echo "DEPLOY_SHA_MARKER_WRITTEN=${FULL_SHA}"
echo "DEPLOY_SHA_MARKER_PATH=${MARKER_FILE}"
