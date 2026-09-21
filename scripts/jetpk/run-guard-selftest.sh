#!/usr/bin/env bash
set -euo pipefail
FIXTURE_ROOT="$(mktemp -d /tmp/jetpk-allowlist-selftest.XXXXXX)"
RELEASE="${FIXTURE_ROOT}/release"
APP="${FIXTURE_ROOT}/app"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ALLOWLIST="${SCRIPT_DIR}/deploy-seo-phase2-activate-allowlist.sh"

mkdir -p "${RELEASE}/app/Support/Seo" "${APP}/bootstrap" "${APP}/app/Providers" "${APP}/scripts"
cat > "${APP}/bootstrap/providers.php" <<'PHP'
<?php
return [App\Providers\AiServiceProvider::class];
PHP
echo 'fixture-app' > "${APP}/app/Providers/AppServiceProvider.php"
echo 'fixture-seo' > "${RELEASE}/app/Support/Seo/marker.txt"
cat > "${APP}/scripts/verify-ai-runtime-after-seo-activate.sh" <<'SH'
#!/usr/bin/env bash
grep -q AiServiceProvider "$APP/bootstrap/providers.php" || exit 1
echo AI_RUNTIME_VERIFY=PASS
SH
chmod +x "${APP}/scripts/verify-ai-runtime-after-seo-activate.sh"

set -x
REL="${RELEASE}" APP="${APP}" PHP="$(command -v php || echo true)" SHA=fixture-sha SKIP_POST_DEPLOY=1 bash "${ALLOWLIST}"
grep -q fixture-app "${APP}/app/Providers/AppServiceProvider.php"
grep -q fixture-seo "${APP}/app/Support/Seo/marker.txt"
echo SELFTEST=PASS
rm -rf "${FIXTURE_ROOT}"
