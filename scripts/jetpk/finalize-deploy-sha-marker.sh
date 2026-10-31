#!/usr/bin/env bash
# Emit deploy marker status after the application component has already been applied.
# Does not roll back a successful component deploy when marker persistence fails.
set -euo pipefail

APP="${APP:-/home/pkjetp/jetpk_app}"
DEPLOY_SHA="${DEPLOY_SHA:-${AUTHORIZED_SHA:-}}"
MARKER_SCRIPT="${MARKER_SCRIPT:-$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/write-deploy-sha-marker.sh}"

echo "DEPLOY_COMPONENT_APPLIED=YES"

if [[ -z "${DEPLOY_SHA}" ]]; then
  echo "DEPLOY_SHA_MARKER=FAIL"
  echo "DEPLOY_SHA_MARKER_FAIL reason=missing_DEPLOY_SHA"
  echo "FINAL_DEPLOY_STATUS=PARTIAL"
  exit 0
fi

if [[ ! -f "${MARKER_SCRIPT}" ]]; then
  echo "DEPLOY_SHA_MARKER=SKIP"
  echo "FINAL_DEPLOY_STATUS=PASS"
  exit 0
fi

DEPLOY_COMPONENT="${DEPLOY_COMPONENT:-application}"
if DEPLOY_SHA="${DEPLOY_SHA}" APP="${APP}" DEPLOY_COMPONENT="${DEPLOY_COMPONENT}" bash "${MARKER_SCRIPT}"; then
  echo "DEPLOY_SHA_MARKER=PASS"
  echo "DEPLOY_COMPONENT=${DEPLOY_COMPONENT}"
  echo "FINAL_DEPLOY_STATUS=PASS"
  exit 0
fi

echo "DEPLOY_SHA_MARKER=FAIL"
echo "FINAL_DEPLOY_STATUS=PARTIAL"
exit 0
