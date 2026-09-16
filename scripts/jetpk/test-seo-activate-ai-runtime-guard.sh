#!/usr/bin/env bash
# Regression: SEO activate scripts must not bulk-copy app/ or bootstrap/ and must
# invoke verify-ai-runtime-after-seo-activate.sh fail-closed.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="${REPO_ROOT:-$(git -C "${SCRIPT_DIR}" rev-parse --show-toplevel 2>/dev/null || dirname "$(dirname "${SCRIPT_DIR}")")}"
ALLOWLIST="${SCRIPT_DIR}/deploy-seo-phase2-activate-allowlist.sh"
VERIFY="${REPO_ROOT}/scripts/verify-ai-runtime-after-seo-activate.sh"

fail() {
  echo "SEO_ACTIVATE_PRESERVES_AI=FAIL reason=$1"
  exit 1
}

[[ -f "${ALLOWLIST}" ]] || fail "missing_allowlist_script"
[[ -f "${VERIFY}" ]] || fail "missing_verify_script"

grep -q 'verify-ai-runtime-after-seo-activate.sh' "${ALLOWLIST}" || fail "allowlist_missing_verify_hook"
grep -q 'bulk_tree_copy_forbidden' "${ALLOWLIST}" || fail "allowlist_missing_bulk_guard"

# Scripts that must not bulk-copy app or bootstrap at activation time.
CANDIDATES=(
  "${REPO_ROOT}/tmp/deploy-seo-phase2-canonical-activate.sh"
  "${REPO_ROOT}/tmp/deploy-seo-phase2-activate.sh"
  "${REPO_ROOT}/tmp/deploy-seo-phase2-ad5f29-canonical.sh"
  "${REPO_ROOT}/tmp/deploy-seo-phase2-activate-targeted.sh"
)

for script in "${CANDIDATES[@]}"; do
  [[ -f "${script}" ]] || continue
  base="$(basename "${script}")"

  if grep -Eq 'for d in app bootstrap|copy_tree "\$REL/app" "\$APP/app"|copy_tree "\$REL/bootstrap" "\$APP/bootstrap"|copy_tree "\$REL/\$d" "\$APP/\$d"' "${script}"; then
    if ! grep -q 'deploy-seo-phase2-activate-allowlist.sh' "${script}"; then
      fail "bulk_copy_still_present_in_${base}"
    fi
  fi

  if [[ "${base}" == "deploy-seo-phase2-activate-targeted.sh" || "${base}" == "deploy-seo-phase2-canonical-activate.sh" ]]; then
    grep -q 'verify-ai-runtime-after-seo-activate.sh' "${script}" || fail "missing_verify_in_${base}"
  fi
done

# Dry-run fixture: protected files must remain unchanged after allowlist activate.
FIXTURE_ROOT="$(mktemp -d /tmp/jetpk-seo-activate-guard.XXXXXX)"
RELEASE="${FIXTURE_ROOT}/release"
APP="${FIXTURE_ROOT}/app"
PHP_BIN="$(command -v php || true)"

cleanup() {
  rm -rf "${FIXTURE_ROOT}"
}
trap cleanup EXIT

mkdir -p "${RELEASE}/app/Support/Seo" "${RELEASE}/app/Services/Seo" "${APP}/bootstrap" "${APP}/app/Providers" "${APP}/scripts"

cat > "${APP}/bootstrap/providers.php" <<'PHP'
<?php
return [App\Providers\AppServiceProvider::class, App\Providers\AiServiceProvider::class];
PHP

cat > "${APP}/app/Providers/AiServiceProvider.php" <<'PHP'
<?php
namespace App\Providers;
class AiServiceProvider {}
PHP

echo 'fixture-seo-marker' > "${RELEASE}/app/Support/Seo/marker.txt"
echo 'fixture-app-marker' > "${APP}/app/Providers/AppServiceProvider.php"

if [[ -n "${PHP_BIN}" ]]; then
  cat > "${APP}/scripts/verify-ai-runtime-after-seo-activate.sh" <<'SH'
#!/usr/bin/env bash
grep -q AiServiceProvider "$APP/bootstrap/providers.php" || exit 1
echo AI_RUNTIME_VERIFY=PASS
SH
  chmod +x "${APP}/scripts/verify-ai-runtime-after-seo-activate.sh"

  REL="${RELEASE}" APP="${APP}" PHP="${PHP_BIN}" SHA=fixture-sha SKIP_POST_DEPLOY=1 bash "${ALLOWLIST}" >/dev/null

  grep -q 'fixture-app-marker' "${APP}/app/Providers/AppServiceProvider.php" || fail "app_service_provider_overwritten"
  grep -q AiServiceProvider "${APP}/bootstrap/providers.php" || fail "providers_overwritten"
  grep -q 'fixture-seo-marker' "${APP}/app/Support/Seo/marker.txt" || fail "seo_tree_not_copied"
fi

echo "SEO_ACTIVATE_PRESERVES_AI=PASS"
