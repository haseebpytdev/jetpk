#!/usr/bin/env bash
# SEO Phase 2 activation — allowlist only. Never bulk-copy app/ or bootstrap/.
# Shared Laravel runtime (AI providers, bootstrap, PublicContentApiPresenter) stays
# owned by the canonical application release, not SEO activation.
set -euo pipefail

APP="${APP:-/home/pkjetp/jetpk_app}"
PHP="${PHP:-/usr/local/lsws/lsphp83/bin/php}"
PM2="${PM2:-/home/pkjetp/.npm-global/lib/node_modules/pm2/bin/pm2}"
REL="${REL:?REL release directory is required}"
SHA="${SHA:-}"
OWNERSHIP="${OWNERSHIP:-${APP}/scripts/jetpk/assert-runtime-ownership.sh}"

if [[ ! -d "${REL}" ]]; then
  echo "SEO_ACTIVATE=FAIL reason=release_dir_missing path=${REL}"
  exit 1
fi

if [[ -z "${SHA}" ]]; then
  if [[ -f "${REL}/.jetpk-authorized-sha" ]]; then
    SHA="$(tr -d '\n' < "${REL}/.jetpk-authorized-sha")"
  elif [[ -f "${APP}/.jetpk-authorized-sha" ]]; then
    SHA="$(tr -d '\n' < "${APP}/.jetpk-authorized-sha")"
  fi
fi

is_ai_protected_path() {
  local path="$1"
  case "${path}" in
    bootstrap/providers.php|bootstrap/app.php) return 0 ;;
    app/Providers/AiServiceProvider.php|app/Providers/AppServiceProvider.php) return 0 ;;
    app/Services/PublicContent/PublicContentApiPresenter.php) return 0 ;;
    app/Http/Middleware/ApplyAiLabCanaryFaultHeader.php) return 0 ;;
    app/Contracts/Ai/*|app/Services/Ai/*) return 0 ;;
    frontend/features/public-content/services/public-config-service.ts) return 0 ;;
    *) return 1 ;;
  esac
}

copy_file() {
  local src="$1" dst="$2"
  local rel="${dst#${APP}/}"

  if is_ai_protected_path "${rel}"; then
    echo "SKIP_PROTECTED ${rel}"
    return 0
  fi

  if [[ ! -f "${src}" ]]; then
    echo "SKIP_MISSING ${rel}"
    return 0
  fi

  mkdir -p "$(dirname "${dst}")"
  cp -f "${src}" "${dst}"
  echo "COPY_FILE ${rel}"
}

copy_tree() {
  local src="$1" dst="$2"
  local rel="${dst#${APP}/}"

  if [[ "${rel}" == app || "${rel}" == bootstrap || "${rel}" == app/* || "${rel}" == bootstrap/* ]]; then
    echo "SEO_ACTIVATE=FAIL reason=bulk_tree_copy_forbidden path=${rel}"
    exit 1
  fi

  if [[ ! -d "${src}" ]]; then
    echo "SKIP_MISSING_TREE ${rel}"
    return 0
  fi

  mkdir -p "${dst}"
  if command -v rsync >/dev/null 2>&1; then
    rsync -a --no-perms --no-owner --no-group --omit-dir-times "${src}/" "${dst}/"
  else
    cp -a "${src}/." "${dst}/"
  fi
  echo "COPY_TREE ${rel}"
}

if [[ -f "${OWNERSHIP}" ]]; then
  APP_ROOT="${APP}" RUNTIME_USER=pkjetp RUNTIME_GROUP=pkjetp MODE=normalize bash "${OWNERSHIP}"
fi

# SEO-owned trees (safe to create/refresh).
copy_tree "${REL}/app/Support/Seo" "${APP}/app/Support/Seo"
copy_tree "${REL}/app/Services/Seo" "${APP}/app/Services/Seo"
copy_tree "${REL}/resources/views/dashboard/admin/seo" "${APP}/resources/views/dashboard/admin/seo"

# SEO-owned individual files.
for f in \
  app/Http/Controllers/Admin/SeoManagementController.php \
  app/Policies/SeoManagementPolicy.php \
  routes/admin-seo.php \
  app/Services/Client/ClientPageSeoResolver.php \
  config/services.php \
  resources/views/dashboard/admin/cms-pages/form.blade.php \
  resources/views/themes/admin/jetpakistan/page-settings/partials/global-sections.blade.php \
  resources/views/themes/admin/jetpakistan/partials/sidebar.blade.php \
  frontend/app/(public)/layout.tsx \
  frontend/features/public-content/components/SiteVerificationMeta.tsx \
  frontend/features/public-content/utils/laravel-api.ts \
  frontend/app/api/internal/revalidate/seo/route.ts; do
  copy_file "${REL}/${f}" "${APP}/${f}"
done

if [[ -n "${SHA}" ]]; then
  printf '%s' "${SHA}" > "${APP}/.jetpk-runtime-sha"
  printf '%s' "${SHA}" > "${APP}/.jetpk-authorized-sha"
fi

if [[ "${SKIP_POST_DEPLOY:-0}" != "1" ]]; then
  cd "${APP}"
  "${PHP}" artisan optimize:clear
  "${PHP}" artisan config:cache
  "${PHP}" artisan route:list --path=admin/seo > /dev/null
  "${PHP}" artisan view:clear
  "${PHP}" artisan view:cache
fi

if [[ "${SKIP_POST_DEPLOY:-0}" != "1" && -d "${REL}/frontend" ]]; then
  ENV_BACKUP="${APP}/frontend/.env.production.local"
  if [[ -f "${ENV_BACKUP}" ]]; then
    cp -a "${ENV_BACKUP}" "${APP}/frontend/.env.production.local.bak-seo-activate"
  fi

  for f in \
    frontend/app/(public)/layout.tsx \
    frontend/features/public-content/components/SiteVerificationMeta.tsx \
    frontend/features/public-content/utils/laravel-api.ts \
    frontend/app/api/internal/revalidate/seo/route.ts; do
    copy_file "${REL}/${f}" "${APP}/${f}"
  done

  cd "${APP}/frontend"
  export PATH=/home/pkjetp/.npm-global/bin:/usr/local/bin:/usr/bin:/bin
  export LARAVEL_URL=http://127.0.0.1:8088
  npm ci
  npm run build

  if [[ -f "${APP}/frontend/.env.production.local.bak-seo-activate" ]]; then
    mv -f "${APP}/frontend/.env.production.local.bak-seo-activate" "${APP}/frontend/.env.production.local"
  fi

  "${PM2}" restart jetpk-public-frontend 2>/dev/null || "${PM2}" restart jetpk-next 2>/dev/null || "${PM2}" restart all
  "${PM2}" restart jetpk-dashboard 2>/dev/null || true
  "${PM2}" list
fi

if [[ -f "${OWNERSHIP}" ]]; then
  APP_ROOT="${APP}" RUNTIME_USER=pkjetp RUNTIME_GROUP=pkjetp MODE=normalize bash "${OWNERSHIP}"
fi

VERIFY="${APP}/scripts/verify-ai-runtime-after-seo-activate.sh"
if [[ ! -f "${VERIFY}" ]]; then
  echo "SEO_ACTIVATE=FAIL reason=verify_script_missing"
  exit 1
fi

APP="${APP}" PHP="${PHP}" bash "${VERIFY}"

echo "SEO_ACTIVATE=PASS"
echo "DEPLOYED_SHA=${SHA}"
echo "RELEASE_PATH=${REL}"
