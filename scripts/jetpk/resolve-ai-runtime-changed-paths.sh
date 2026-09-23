#!/usr/bin/env bash
# List authorized AI allowlist paths changed between CURRENT_DEPLOYED_SHA and AUTHORIZED_SHA.
set -euo pipefail

AUTHORIZED_SHA="${AUTHORIZED_SHA:?AUTHORIZED_SHA is required}"
REPO="${REPO:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# shellcheck source=ai-runtime-deploy-allowlist.sh
source "${SCRIPT_DIR}/ai-runtime-deploy-allowlist.sh"

CURRENT_DEPLOYED_SHA="${CURRENT_DEPLOYED_SHA:-}"
if [[ -z "${CURRENT_DEPLOYED_SHA}" && -n "${APP:-}" && -f "${APP}/.jetpk-runtime-sha" ]]; then
  CURRENT_DEPLOYED_SHA="$(tr -d '\n' < "${APP}/.jetpk-runtime-sha")"
fi

cd "${REPO}"
git fetch --all --tags --prune >/dev/null 2>&1 || true

test "$(git cat-file -t "${AUTHORIZED_SHA}" 2>/dev/null || echo missing)" = "commit" \
  || { echo "RESOLVE_CHANGED_PATHS_FAIL reason=missing_AUTHORIZED_SHA" >&2; exit 1; }
AUTHORIZED_SHA="$(git rev-parse "${AUTHORIZED_SHA}")"

if [[ -z "${CURRENT_DEPLOYED_SHA}" ]]; then
  echo "CURRENT_DEPLOYED_SHA=NONE"
  printf '%s\n' "${AI_RUNTIME_DEPLOY_PATHSPECS[@]}"
  exit 0
fi

test "$(git cat-file -t "${CURRENT_DEPLOYED_SHA}" 2>/dev/null || echo missing)" = "commit" \
  || { echo "RESOLVE_CHANGED_PATHS_FAIL reason=missing_CURRENT_DEPLOYED_SHA" >&2; exit 1; }
CURRENT_DEPLOYED_SHA="$(git rev-parse "${CURRENT_DEPLOYED_SHA}")"

echo "CURRENT_DEPLOYED_SHA=${CURRENT_DEPLOYED_SHA}" >&2
echo "AUTHORIZED_SHA=${AUTHORIZED_SHA}" >&2

if [[ "${CURRENT_DEPLOYED_SHA}" == "${AUTHORIZED_SHA}" ]]; then
  exit 0
fi

git diff --name-only "${CURRENT_DEPLOYED_SHA}" "${AUTHORIZED_SHA}" -- "${AI_RUNTIME_DEPLOY_PATHSPECS[@]}"
