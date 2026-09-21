#!/usr/bin/env bash
# Ensure AUTHORIZED_SHA is set, resolves, and matches optional staged/runtime stamps.
# Usage:
#   AUTHORIZED_SHA=<sha> bash scripts/jetpk/guard-authorized-sha.sh
# Optional:
#   REQUIRE_ON_BRANCH=main|checkpoint/...
#   STAGED_SOURCE_SHA=...
#   RUNTIME_SHA_FILE=/home/pkjetp/jetpk_app/.jetpk-runtime-sha
#   ALLOWLIST_SHAS="sha1 sha2"   # authorized deploy targets (space-separated)
set -euo pipefail

AUTHORIZED_SHA="${AUTHORIZED_SHA:?AUTHORIZED_SHA is required}"
REPO_ROOT="${REPO_ROOT:-$(git rev-parse --show-toplevel 2>/dev/null || true)}"
if [[ -z "${REPO_ROOT}" || ! -d "${REPO_ROOT}/.git" ]]; then
  echo "REPO_ROOT_INVALID"
  echo "SOURCE_SHA_NOT_AUTHORIZED=FAIL"
  exit 1
fi
cd "${REPO_ROOT}"

if ! git cat-file -e "${AUTHORIZED_SHA}^{commit}" 2>/dev/null; then
  echo "AUTHORIZED_SHA_MISSING=${AUTHORIZED_SHA}"
  echo "SOURCE_SHA_NOT_AUTHORIZED=FAIL"
  exit 1
fi
AUTHORIZED_SHA="$(git rev-parse "${AUTHORIZED_SHA}")"
echo "AUTHORIZED_SHA=${AUTHORIZED_SHA}"

if [[ -n "${REQUIRE_ON_BRANCH:-}" ]]; then
  if ! git rev-parse --verify "${REQUIRE_ON_BRANCH}" >/dev/null 2>&1; then
    echo "REQUIRE_ON_BRANCH_MISSING=${REQUIRE_ON_BRANCH}"
    echo "SOURCE_SHA_NOT_AUTHORIZED=FAIL"
    exit 1
  fi
  if ! git merge-base --is-ancestor "${AUTHORIZED_SHA}" "${REQUIRE_ON_BRANCH}"; then
    echo "AUTHORIZED_SHA_NOT_ON_BRANCH=${REQUIRE_ON_BRANCH}"
    echo "SOURCE_SHA_NOT_AUTHORIZED=FAIL"
    exit 1
  fi
fi

if [[ -n "${ALLOWLIST_SHAS:-}" ]]; then
  ok=0
  for s in ${ALLOWLIST_SHAS}; do
    full="$(git rev-parse "${s}" 2>/dev/null || true)"
    if [[ "${full}" == "${AUTHORIZED_SHA}" ]]; then
      ok=1
      break
    fi
  done
  if [[ "${ok}" -ne 1 ]]; then
    echo "AUTHORIZED_SHA_NOT_IN_ALLOWLIST"
    echo "SOURCE_SHA_NOT_AUTHORIZED=FAIL"
    exit 1
  fi
fi

if [[ -n "${STAGED_SOURCE_SHA:-}" ]]; then
  STAGED_SOURCE_SHA="$(git rev-parse "${STAGED_SOURCE_SHA}")"
  if [[ "${STAGED_SOURCE_SHA}" != "${AUTHORIZED_SHA}" ]]; then
    echo "STAGED_SOURCE_SHA=${STAGED_SOURCE_SHA}"
    echo "BUILD_SOURCE_SHA_MISMATCH=FAIL"
    echo "SOURCE_SHA_NOT_AUTHORIZED=FAIL"
    exit 1
  fi
fi

if [[ -n "${RUNTIME_SHA_FILE:-}" && -f "${RUNTIME_SHA_FILE}" ]]; then
  runtime="$(tr -d '[:space:]' < "${RUNTIME_SHA_FILE}")"
  echo "RUNTIME_SHA_FILE_VALUE=${runtime}"
fi

echo "BUILD_SOURCE_SHA_MISMATCH=PASS"
echo "SOURCE_SHA_NOT_AUTHORIZED=PASS"
