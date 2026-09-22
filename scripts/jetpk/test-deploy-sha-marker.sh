#!/usr/bin/env bash
# Self-test for deploy-sha marker semantics (no production mutation).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TMP_APP="$(mktemp -d)"
trap 'rm -rf "${TMP_APP}"' EXIT

OLD_SHA="205aa2613bd338a2a5366578e955c1093c493622"
NEW_SHA="ae30ba88b8cb66c4853a842e644aa1e166f022b8"

mkdir -p "${TMP_APP}/storage/app"
printf '%s\n' "${OLD_SHA}" > "${TMP_APP}/storage/app/deploy-sha.txt"

# Case 1: failed deploy before success gate must not advance marker.
MARKER_BEFORE="$(tr -d '\n' < "${TMP_APP}/storage/app/deploy-sha.txt")"
if [[ "${MARKER_BEFORE}" != "${OLD_SHA}" ]]; then
  echo "DEPLOY_SHA_MARKER_TEST_FAIL reason=initial_marker_seed"
  exit 1
fi
# Simulate deploy abort before finalize (no finalize call).
MARKER_AFTER_ABORT="$(tr -d '\n' < "${TMP_APP}/storage/app/deploy-sha.txt")"
if [[ "${MARKER_AFTER_ABORT}" != "${OLD_SHA}" ]]; then
  echo "DEPLOY_SHA_MARKER_TEST_FAIL reason=marker_changed_without_finalize"
  exit 1
fi

# Case 2: successful deploy updates marker atomically via finalize wrapper.
FINALIZE_OUT="$(
  APP="${TMP_APP}" DEPLOY_SHA="${NEW_SHA}" \
    MARKER_SCRIPT="${ROOT}/scripts/jetpk/write-deploy-sha-marker.sh" \
    bash "${ROOT}/scripts/jetpk/finalize-deploy-sha-marker.sh"
)"
echo "${FINALIZE_OUT}"
if ! grep -q "DEPLOY_COMPONENT_APPLIED=YES" <<< "${FINALIZE_OUT}"; then
  echo "DEPLOY_SHA_MARKER_TEST_FAIL reason=missing_component_applied"
  exit 1
fi
if ! grep -q "DEPLOY_SHA_MARKER=PASS" <<< "${FINALIZE_OUT}"; then
  echo "DEPLOY_SHA_MARKER_TEST_FAIL reason=marker_not_pass_on_success"
  exit 1
fi
if ! grep -q "FINAL_DEPLOY_STATUS=PASS" <<< "${FINALIZE_OUT}"; then
  echo "DEPLOY_SHA_MARKER_TEST_FAIL reason=final_status_not_pass_on_success"
  exit 1
fi
MARKER_AFTER_SUCCESS="$(tr -d '\n' < "${TMP_APP}/storage/app/deploy-sha.txt")"
if [[ "${MARKER_AFTER_SUCCESS}" != "${NEW_SHA}" ]]; then
  echo "DEPLOY_SHA_MARKER_TEST_FAIL reason=marker_not_updated_on_success"
  exit 1
fi

# Case 3: marker writer failure reports explicit marker failure without rollback.
FAIL_MARKER="${TMP_APP}/fail-marker.sh"
cat > "${FAIL_MARKER}" <<'EOF'
#!/usr/bin/env bash
echo "DEPLOY_SHA_MARKER_FAIL reason=simulated_writer_failure"
exit 1
EOF
chmod +x "${FAIL_MARKER}"
MARKER_BEFORE_FAIL="$(tr -d '\n' < "${TMP_APP}/storage/app/deploy-sha.txt")"
FAIL_OUT="$(
  APP="${TMP_APP}" DEPLOY_SHA="${NEW_SHA}" MARKER_SCRIPT="${FAIL_MARKER}" \
    bash "${ROOT}/scripts/jetpk/finalize-deploy-sha-marker.sh"
)"
echo "${FAIL_OUT}"
if ! grep -q "DEPLOY_COMPONENT_APPLIED=YES" <<< "${FAIL_OUT}"; then
  echo "DEPLOY_SHA_MARKER_TEST_FAIL reason=missing_component_applied_on_marker_fail"
  exit 1
fi
if ! grep -q "DEPLOY_SHA_MARKER=FAIL" <<< "${FAIL_OUT}"; then
  echo "DEPLOY_SHA_MARKER_TEST_FAIL reason=marker_fail_not_reported"
  exit 1
fi
if ! grep -q "FINAL_DEPLOY_STATUS=PARTIAL" <<< "${FAIL_OUT}"; then
  echo "DEPLOY_SHA_MARKER_TEST_FAIL reason=final_status_not_partial_on_marker_fail"
  exit 1
fi
MARKER_AFTER_FAIL="$(tr -d '\n' < "${TMP_APP}/storage/app/deploy-sha.txt")"
if [[ "${MARKER_AFTER_FAIL}" != "${MARKER_BEFORE_FAIL}" ]]; then
  echo "DEPLOY_SHA_MARKER_TEST_FAIL reason=marker_changed_on_writer_failure"
  exit 1
fi

echo "DEPLOY_SHA_MARKER_TEST=PASS"
