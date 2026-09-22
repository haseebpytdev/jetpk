#!/usr/bin/env bash
# Self-test for deploy-sha marker writer (no production mutation).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TMP_APP="$(mktemp -d)"
trap 'rm -rf "${TMP_APP}"' EXIT

mkdir -p "${TMP_APP}/storage/app"
export APP="${TMP_APP}"
export DEPLOY_SHA="ae30ba88b8cb66c4853a842e644aa1e166f022b8"

bash "${ROOT}/scripts/jetpk/write-deploy-sha-marker.sh" >/dev/null
MARKER="$(tr -d '\n' < "${TMP_APP}/storage/app/deploy-sha.txt")"
if [[ "${MARKER}" != "${DEPLOY_SHA}" ]]; then
  echo "DEPLOY_SHA_MARKER_TEST_FAIL reason=marker_mismatch"
  exit 1
fi

# Failed deploy must not advance marker when wrapper skips write (simulate).
printf '%s\n' "205aa2613bd338a2a5366578e955c1093c493622" > "${TMP_APP}/storage/app/deploy-sha.txt"
if bash "${ROOT}/scripts/jetpk/write-deploy-sha-marker.sh" >/dev/null; then
  NEW="$(tr -d '\n' < "${TMP_APP}/storage/app/deploy-sha.txt")"
  if [[ "${NEW}" != "${DEPLOY_SHA}" ]]; then
    echo "DEPLOY_SHA_MARKER_TEST_FAIL reason=overwrite_failed"
    exit 1
  fi
fi

echo "DEPLOY_SHA_MARKER_TEST=PASS"
