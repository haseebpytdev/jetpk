#!/usr/bin/env bash
# Local manifest regression for stage-release-from-sha.sh (no production mutation).
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
FIX="$(mktemp -d /tmp/jetpk-stage-manifest.XXXXXX)"
trap 'rm -rf "${FIX}"' EXIT

git -C "${FIX}" init -q
git -C "${FIX}" config user.email "stage@test.local"
git -C "${FIX}" config user.name "stage"

mkdir -p "${FIX}/frontend" "${FIX}/dashboard/features" "${FIX}/app/Support"
echo v1 > "${FIX}/frontend/package.json"
echo v1 > "${FIX}/dashboard/features/x.tsx"
echo v1 > "${FIX}/app/Support/Foo.php"
git -C "${FIX}" add .
git -C "${FIX}" commit -qm "base"
BASE="$(git -C "${FIX}" rev-parse HEAD)"

echo v2 > "${FIX}/dashboard/features/x.tsx"
git -C "${FIX}" add dashboard/features/x.tsx
git -C "${FIX}" commit -qm "dashboard only"
AUTH="$(git -C "${FIX}" rev-parse HEAD)"

OUT="$(REPO_ROOT="${FIX}" STAGE_ROOT="${FIX}/releases" AUTHORIZED_SHA="${AUTH}" BASE_SHA="${BASE}" RELEASE_SCOPE=dashboard-laravel DRY_RUN=1 bash "${SCRIPT_DIR}/stage-release-from-sha.sh")"
echo "${OUT}" | grep -q 'RELEASE_STAGED_DRY_RUN_COMPLETE'
echo "${OUT}" | grep -q 'dashboard/features/x.tsx'
echo "${OUT}" | grep -q 'frontend/package.json'

echo v3 > "${FIX}/app/Support/Foo.php"
git -C "${FIX}" add app/Support/Foo.php
git -C "${FIX}" commit -qm "laravel only"
AUTH2="$(git -C "${FIX}" rev-parse HEAD)"

OUT2="$(REPO_ROOT="${FIX}" STAGE_ROOT="${FIX}/releases" AUTHORIZED_SHA="${AUTH2}" BASE_SHA="${AUTH}" RELEASE_SCOPE=laravel DRY_RUN=1 bash "${SCRIPT_DIR}/stage-release-from-sha.sh")"
echo "${OUT2}" | grep -q 'app/Support/Foo.php'

if REPO_ROOT="${FIX}" STAGE_ROOT="${FIX}/releases" AUTHORIZED_SHA="${AUTH2}" BASE_SHA="${AUTH2}" RELEASE_SCOPE=frontend DRY_RUN=1 bash "${SCRIPT_DIR}/stage-release-from-sha.sh" >/tmp/empty.out 2>&1; then
  echo "expected NO_CHANGE empty manifest"; exit 1
fi
grep -q 'EMPTY_RUNTIME_MANIFEST' /tmp/empty.out

echo "STAGE_RELEASE_MANIFEST_SELFTEST=PASS"
