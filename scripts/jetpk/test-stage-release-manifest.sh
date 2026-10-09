#!/usr/bin/env bash
# Local manifest regression for stage-release-from-sha.sh (no production mutation).
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
STAGE_SCRIPT="${SCRIPT_DIR}/stage-release-from-sha.sh"
FIX="$(mktemp -d /tmp/jetpk-stage-manifest.XXXXXX)"
trap 'rm -rf "${FIX}"' EXIT

PASS=()
fail() {
  echo "STAGE_MANIFEST_SELFTEST_FAIL=${1}"
  exit 1
}

stage_dry() {
  local authorized="$1"
  local base="$2"
  local scope="$3"
  REPO_ROOT="${FIX}" STAGE_ROOT="${FIX}/releases" AUTHORIZED_SHA="${authorized}" BASE_SHA="${base}" RELEASE_SCOPE="${scope}" DRY_RUN=1 bash "${STAGE_SCRIPT}"
}

stage_dry_expect_empty() {
  local authorized="$1"
  local base="$2"
  local scope="$3"
  local err_file
  err_file="$(mktemp)"
  if REPO_ROOT="${FIX}" STAGE_ROOT="${FIX}/releases" AUTHORIZED_SHA="${authorized}" BASE_SHA="${base}" RELEASE_SCOPE="${scope}" DRY_RUN=1 bash "${STAGE_SCRIPT}" >"${err_file}" 2>&1; then
    echo "expected EMPTY_RUNTIME_MANIFEST for scope=${scope}"
    cat "${err_file}"
    rm -f "${err_file}"
    fail "no_change_should_fail"
  fi
  grep -q 'EMPTY_RUNTIME_MANIFEST' "${err_file}" || fail "missing_empty_manifest_marker"
  rm -f "${err_file}"
}

manifest_lines() {
  printf '%s\n' "$1" | awk '!/=/ && NF'
}

manifest_has() {
  local out="$1"
  local path="$2"
  printf '%s\n' "${out}" | grep -qx "${path}"
}

manifest_lacks() {
  local out="$1"
  local path="$2"
  if printf '%s\n' "${out}" | grep -qx "${path}"; then
    return 1
  fi
  return 0
}

git -C "${FIX}" init -q
git -C "${FIX}" config user.email "stage@test.local"
git -C "${FIX}" config user.name "stage"

mkdir -p \
  "${FIX}/frontend/src" \
  "${FIX}/dashboard/features" \
  "${FIX}/app/Support" \
  "${FIX}/docs/evidence" \
  "${FIX}/dashboard/tests" \
  "${FIX}/secrets"

cat > "${FIX}/frontend/package.json" <<'EOF'
{"name":"fixture-frontend"}
EOF
echo 'export const v = 1;' > "${FIX}/frontend/src/page.tsx"
echo 'export const Dash = 1;' > "${FIX}/dashboard/features/runtime.tsx"
echo '<?php // v1' > "${FIX}/app/Support/Runtime.php"
echo '# doc' > "${FIX}/docs/evidence/readme.md"
echo 'describe("x", () => {});' > "${FIX}/dashboard/tests/runtime.spec.ts"
echo 'SECRET=1' > "${FIX}/.env.production"
echo 'key' > "${FIX}/secrets/deploy.pem"

git -C "${FIX}" add frontend dashboard app docs secrets .env.production
git -C "${FIX}" commit -qm "base"
BASE="$(git -C "${FIX}" rev-parse HEAD)"

# 1. DASHBOARD_ONLY_DIFF (RELEASE_SCOPE=frontend)
echo 'export const Dash = 2;' > "${FIX}/dashboard/features/runtime.tsx"
git -C "${FIX}" add dashboard/features/runtime.tsx
git -C "${FIX}" commit -qm "dashboard only"
SHA_DASH_ONLY="$(git -C "${FIX}" rev-parse HEAD)"
OUT_DASH="$(stage_dry "${SHA_DASH_ONLY}" "${BASE}" frontend)"
echo "${OUT_DASH}" | grep -q 'RELEASE_STAGED_DRY_RUN_COMPLETE' || fail "dashboard_only_dry"
manifest_has "${OUT_DASH}" "dashboard/features/runtime.tsx" || fail "dashboard_only_missing_dashboard"
manifest_has "${OUT_DASH}" "frontend/package.json" || fail "dashboard_only_missing_anchor"
manifest_lacks "${OUT_DASH}" "docs/evidence/readme.md" || fail "dashboard_only_leaked_docs"
PASS+=("DASHBOARD_ONLY_FRONTEND_SCOPE=PASS")

# 2. LARAVEL_ONLY_DIFF (RELEASE_SCOPE=frontend) — new base from dashboard commit
echo '<?php // v2' > "${FIX}/app/Support/Runtime.php"
git -C "${FIX}" add app/Support/Runtime.php
git -C "${FIX}" commit -qm "laravel only"
SHA_LARAVEL_ONLY="$(git -C "${FIX}" rev-parse HEAD)"
BASE_LARAVEL="${SHA_DASH_ONLY}"
OUT_LARAVEL="$(stage_dry "${SHA_LARAVEL_ONLY}" "${BASE_LARAVEL}" frontend)"
echo "${OUT_LARAVEL}" | grep -q 'RELEASE_STAGED_DRY_RUN_COMPLETE' || fail "laravel_only_dry"
manifest_has "${OUT_LARAVEL}" "app/Support/Runtime.php" || fail "laravel_only_missing_app"
manifest_has "${OUT_LARAVEL}" "frontend/package.json" || fail "laravel_only_missing_anchor"
PASS+=("LARAVEL_ONLY_FRONTEND_SCOPE=PASS")

# 3. DASHBOARD_PLUS_LARAVEL_DIFF (single commit from BASE)
git -C "${FIX}" checkout -q "${BASE}"
git -C "${FIX}" checkout -B jpqa-dash-laravel "${BASE}"
echo 'export const Dash = 3;' > "${FIX}/dashboard/features/runtime.tsx"
echo '<?php // v3' > "${FIX}/app/Support/Runtime.php"
git -C "${FIX}" add dashboard/features/runtime.tsx app/Support/Runtime.php
git -C "${FIX}" commit -qm "dashboard plus laravel"
SHA_BOTH="$(git -C "${FIX}" rev-parse HEAD)"
OUT_BOTH="$(stage_dry "${SHA_BOTH}" "${BASE}" frontend)"
manifest_has "${OUT_BOTH}" "dashboard/features/runtime.tsx" || fail "both_missing_dashboard"
manifest_has "${OUT_BOTH}" "app/Support/Runtime.php" || fail "both_missing_laravel"
manifest_has "${OUT_BOTH}" "frontend/package.json" || fail "both_missing_anchor"
PASS+=("DASHBOARD_LARAVEL_FRONTEND_SCOPE=PASS")

# 4. NO_CHANGE
stage_dry_expect_empty "${SHA_BOTH}" "${SHA_BOTH}" frontend
PASS+=("NO_CHANGE_EMPTY_MANIFEST=PASS")

# 5. FULL_STACK_DIFF
git -C "${FIX}" checkout -q "${BASE}"
git -C "${FIX}" checkout -B jpqa-full-stack "${BASE}"
echo 'export const v = 2;' > "${FIX}/frontend/src/page.tsx"
echo 'export const Dash = 4;' > "${FIX}/dashboard/features/runtime.tsx"
echo '<?php // v4' > "${FIX}/app/Support/Runtime.php"
git -C "${FIX}" add frontend/src/page.tsx dashboard/features/runtime.tsx app/Support/Runtime.php
git -C "${FIX}" commit -qm "full stack"
SHA_FULL="$(git -C "${FIX}" rev-parse HEAD)"
OUT_FULL="$(stage_dry "${SHA_FULL}" "${BASE}" frontend)"
manifest_has "${OUT_FULL}" "frontend/src/page.tsx" || fail "full_stack_missing_frontend"
manifest_has "${OUT_FULL}" "dashboard/features/runtime.tsx" || fail "full_stack_missing_dashboard"
manifest_has "${OUT_FULL}" "app/Support/Runtime.php" || fail "full_stack_missing_laravel"
# Anchor-only package.json must not be the sole frontend path when a real frontend file changed.
if manifest_has "${OUT_FULL}" "frontend/package.json"; then
  echo "full stack should not add synthetic anchor when real frontend path exists"
  fail "full_stack_synthetic_anchor"
fi
PASS+=("FULL_STACK_SCOPE=PASS")

# 6. RELEASE_SCOPE=dashboard-laravel (meta normalizes to frontend on real stage)
git -C "${FIX}" checkout -q "${BASE}"
git -C "${FIX}" checkout -B jpqa-dash-laravel-scope "${BASE}"
echo 'export const Dash = 5;' > "${FIX}/dashboard/features/runtime.tsx"
git -C "${FIX}" add dashboard/features/runtime.tsx
git -C "${FIX}" commit -qm "dashboard for dash-laravel scope"
SHA_DL="$(git -C "${FIX}" rev-parse HEAD)"
OUT_DL="$(stage_dry "${SHA_DL}" "${BASE}" dashboard-laravel)"
manifest_has "${OUT_DL}" "dashboard/features/runtime.tsx" || fail "dash_laravel_scope_missing_dashboard"
manifest_has "${OUT_DL}" "frontend/package.json" || fail "dash_laravel_scope_missing_anchor"
STAGE_ROOT_DL="${FIX}/releases-dl"
mkdir -p "${STAGE_ROOT_DL}"
REPO_ROOT="${FIX}" STAGE_ROOT="${STAGE_ROOT_DL}" AUTHORIZED_SHA="${SHA_DL}" BASE_SHA="${BASE}" RELEASE_SCOPE=dashboard-laravel bash "${STAGE_SCRIPT}" >/tmp/jetpk-stage-dl-meta.out
META="$(find "${STAGE_ROOT_DL}" -name '.jetpk-release-meta.env' | head -n1)"
grep -q 'RELEASE_SCOPE=frontend' "${META}" || fail "dash_laravel_meta_not_normalized"
PASS+=("DASHBOARD_LARAVEL_SCOPE=PASS")

# 7. EXCLUDED_PATHS — attempt to diff excluded files only (must still fail NO_CHANGE)
git -C "${FIX}" checkout -q "${BASE}"
echo '# changed doc' > "${FIX}/docs/evidence/readme.md"
echo 'describe("y", () => {});' > "${FIX}/dashboard/tests/runtime.spec.ts"
echo 'SECRET=2' > "${FIX}/.env.production"
echo 'key2' > "${FIX}/secrets/deploy.pem"
git -C "${FIX}" add docs/evidence/readme.md dashboard/tests/runtime.spec.ts .env.production secrets/deploy.pem
git -C "${FIX}" commit -qm "excluded only"
SHA_EXCLUDED="$(git -C "${FIX}" rev-parse HEAD)"
stage_dry_expect_empty "${SHA_EXCLUDED}" "${BASE}" frontend
# Mixed commit: runtime + excluded paths
git -C "${FIX}" checkout -q "${BASE}"
echo 'export const Dash = 6;' > "${FIX}/dashboard/features/runtime.tsx"
echo '# mixed doc' > "${FIX}/docs/evidence/readme.md"
echo 'describe("z", () => {});' > "${FIX}/dashboard/tests/runtime.spec.ts"
git -C "${FIX}" add dashboard/features/runtime.tsx docs/evidence/readme.md dashboard/tests/runtime.spec.ts
git -C "${FIX}" commit -qm "mixed runtime and excluded"
SHA_MIXED="$(git -C "${FIX}" rev-parse HEAD)"
OUT_MIXED="$(stage_dry "${SHA_MIXED}" "${BASE}" frontend)"
manifest_has "${OUT_MIXED}" "dashboard/features/runtime.tsx" || fail "excluded_missing_runtime"
manifest_lacks "${OUT_MIXED}" "docs/evidence/readme.md" || fail "excluded_leaked_doc"
manifest_lacks "${OUT_MIXED}" "dashboard/tests/runtime.spec.ts" || fail "excluded_leaked_spec"
manifest_lacks "${OUT_MIXED}" ".env.production" || fail "excluded_leaked_env"
manifest_lacks "${OUT_MIXED}" "secrets/deploy.pem" || fail "excluded_leaked_pem"
PASS+=("EXCLUDED_PATHS=PASS")

echo "STAGE_RELEASE_MANIFEST_SELFTEST=PASS"
printf '%s\n' "${PASS[@]}"
echo "ALL_SCOPE_CASES_PASS=YES"
