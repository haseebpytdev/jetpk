#!/usr/bin/env bash
# Wrapper: run all post-recovery deploy guards before build/restart.
# Usage (example):
#   AUTHORIZED_SHA=78dadc7b... \
#   ALLOWLIST_SHAS="78dadc7b... cbd7686f..." \
#   APP_DIR=/home/pkjetp/jetpk_app/frontend \
#   bash scripts/jetpk/run-post-recovery-deploy-guards.sh
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

bash "${SCRIPT_DIR}/guard-dirty-worktree.sh"
bash "${SCRIPT_DIR}/guard-disk-space.sh"
AUTHORIZED_SHA="${AUTHORIZED_SHA:?AUTHORIZED_SHA required}" \
  ALLOWLIST_SHAS="${ALLOWLIST_SHAS:-}" \
  STAGED_SOURCE_SHA="${STAGED_SOURCE_SHA:-}" \
  bash "${SCRIPT_DIR}/guard-authorized-sha.sh"

if [[ -n "${APP_DIR:-}" ]]; then
  APP_DIR="${APP_DIR}" \
    EXPECTED_BUILD_ID="${EXPECTED_BUILD_ID:-}" \
    REQUIRE_SOURCE_SHA="${REQUIRE_SOURCE_SHA:-${AUTHORIZED_SHA}}" \
    bash "${SCRIPT_DIR}/guard-build-before-restart.sh"
fi

echo "POST_RECOVERY_DEPLOY_GUARDS=PASS"
