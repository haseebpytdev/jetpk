#!/usr/bin/env bash
# Safe fixture tests for assert-runtime-ownership.sh (no production mutation).
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
HELPER="${SCRIPT_DIR}/assert-runtime-ownership.sh"
FIXTURE_ROOT="$(mktemp -d /tmp/jetpk-ownership-gate-test.XXXXXX)"
RUNTIME_USER="${RUNTIME_USER:-pkjetp}"
RUNTIME_GROUP="${RUNTIME_GROUP:-pkjetp}"

cleanup() {
  rm -rf "${FIXTURE_ROOT}"
}
trap cleanup EXIT

mkdir -p "${FIXTURE_ROOT}/storage/framework/cache/data" \
  "${FIXTURE_ROOT}/storage/framework/views" \
  "${FIXTURE_ROOT}/storage/framework/sessions" \
  "${FIXTURE_ROOT}/storage/logs" \
  "${FIXTURE_ROOT}/bootstrap/cache"

if ! id "${RUNTIME_USER}" >/dev/null 2>&1; then
  echo "RUNTIME_USER_MISSING=${RUNTIME_USER}"
  exit 1
fi

chown -R "${RUNTIME_USER}:${RUNTIME_GROUP}" "${FIXTURE_ROOT}/storage" "${FIXTURE_ROOT}/bootstrap/cache"

POSITIVE_OUT="$(APP_ROOT="${FIXTURE_ROOT}" RUNTIME_USER="${RUNTIME_USER}" RUNTIME_GROUP="${RUNTIME_GROUP}" MODE=assert bash "${HELPER}")"
echo "${POSITIVE_OUT}" | grep -q 'RUNTIME_OWNERSHIP_GATE=PASS' || {
  echo "OWNERSHIP_GATE_POSITIVE_TEST=FAIL"
  exit 1
}
echo "OWNERSHIP_GATE_POSITIVE_TEST=PASS"

BAD_FILE="${FIXTURE_ROOT}/storage/framework/cache/data/.bad-owner"
touch "${BAD_FILE}"
chown root:root "${BAD_FILE}"

set +e
NEGATIVE_OUT="$(APP_ROOT="${FIXTURE_ROOT}" RUNTIME_USER="${RUNTIME_USER}" RUNTIME_GROUP="${RUNTIME_GROUP}" MODE=assert bash "${HELPER}" 2>&1)"
NEG_RC=$?
set -e
if [[ "${NEG_RC}" -eq 0 ]]; then
  echo "OWNERSHIP_GATE_NEGATIVE_TEST=FAIL expected_nonzero"
  echo "${NEGATIVE_OUT}"
  exit 1
fi
echo "${NEGATIVE_OUT}" | grep -q 'RUNTIME_OWNERSHIP_GATE=FAIL' || {
  echo "OWNERSHIP_GATE_NEGATIVE_TEST=FAIL missing_gate_fail"
  echo "${NEGATIVE_OUT}"
  exit 1
}
echo "OWNERSHIP_GATE_NEGATIVE_TEST=PASS"

FIRST_NORM="$(APP_ROOT="${FIXTURE_ROOT}" RUNTIME_USER="${RUNTIME_USER}" RUNTIME_GROUP="${RUNTIME_GROUP}" MODE=normalize bash "${HELPER}")"
echo "${FIRST_NORM}" | grep -q 'RUNTIME_OWNERSHIP_GATE=PASS' || {
  echo "IDEMPOTENCY_TEST=FAIL first_normalize"
  exit 1
}
SECOND_NORM="$(APP_ROOT="${FIXTURE_ROOT}" RUNTIME_USER="${RUNTIME_USER}" RUNTIME_GROUP="${RUNTIME_GROUP}" MODE=normalize bash "${HELPER}")"
echo "${SECOND_NORM}" | grep -q 'RUNTIME_OWNERSHIP_GATE=PASS' || {
  echo "IDEMPOTENCY_TEST=FAIL second_normalize"
  exit 1
}
echo "IDEMPOTENCY_TEST=PASS"
