#!/bin/bash
# JETPK staged deploy script — run on production after release upload/stage
# Usage: jetpk-deploy.sh <release_dir> <timestamp>
#
# Optional env:
#   APPLY_DELETIONS=1 (default) — apply exact DELETE_RUNTIME_FILES allowlist
#   SCOPED_FRONTEND_ONLY=1 — when release meta scope=frontend, copy only staged frontend files
RELEASE_DIR="$1"
TS="$2"
if [ -z "$RELEASE_DIR" ] || [ -z "$TS" ]; then
  echo "USAGE: jetpk-deploy.sh <release_dir> <timestamp>"
  exit 1
fi

PHP=/usr/local/lsws/lsphp83/bin/php
APP=/home/pkjetp/jetpk_app
WEB=/home/pkjetp/public_html
RUNTIME_USER="${RUNTIME_USER:-pkjetp}"
RUNTIME_GROUP="${RUNTIME_GROUP:-pkjetp}"
APPLY_DELETIONS="${APPLY_DELETIONS:-1}"
SCRIPT_HELPERS="${JETPK_SCRIPTS_ROOT:-/home/pkjetp/jetpk_app/scripts/jetpk}"
OWNERSHIP_HELPER="${SCRIPT_HELPERS}/assert-runtime-ownership.sh"

SCOPE=""
if [ -f "$RELEASE_DIR/.jetpk-release-meta.env" ]; then
  # shellcheck disable=SC1090
  . "$RELEASE_DIR/.jetpk-release-meta.env"
  SCOPE="${RELEASE_SCOPE:-}"
fi

echo "=== PHASE: source deploy ==="
if [ "$SCOPE" = "laravel" ]; then
  echo "COPY scoped Laravel runtime from Git SHA"
  for d in app config routes resources database ai-assistant; do
    if [ -d "$RELEASE_DIR/$d" ]; then
      echo "COPY $d"
      mkdir -p "$APP/$d"
      if command -v rsync >/dev/null 2>&1; then
        rsync -a "$RELEASE_DIR/$d/" "$APP/$d/"
      else
        cp -a "$RELEASE_DIR/$d/." "$APP/$d/"
      fi
      if [ $? -ne 0 ]; then echo "COPY_FAIL_$d"; exit 1; fi
    fi
  done
  if [ ! -d "$RELEASE_DIR/resources" ] && [ ! -d "$RELEASE_DIR/app" ]; then
    echo "SCOPED_LARAVEL_MISSING"
    exit 1
  fi
elif [ "$SCOPE" = "frontend" ] || [ "${SCOPED_FRONTEND_ONLY:-0}" = "1" ]; then
  if [ ! -d "$RELEASE_DIR/frontend" ]; then
    echo "SCOPED_FRONTEND_MISSING"
    exit 1
  fi
  echo "COPY scoped frontend runtime"
  mkdir -p "$APP/frontend"
  # Copy only staged files while preserving relative paths.
  if command -v rsync >/dev/null 2>&1; then
    rsync -a "$RELEASE_DIR/frontend/" "$APP/frontend/"
  else
    cp -a "$RELEASE_DIR/frontend/." "$APP/frontend/"
  fi
  if [ $? -ne 0 ]; then echo "COPY_FAIL_frontend"; exit 1; fi
    if [ -d "$RELEASE_DIR/database" ]; then
      echo "COPY database"
      mkdir -p "$APP/database"
      if command -v rsync >/dev/null 2>&1; then
        rsync -a "$RELEASE_DIR/database/" "$APP/database/"
      else
        cp -a "$RELEASE_DIR/database/." "$APP/database/"
      fi
      if [ $? -ne 0 ]; then echo "COPY_FAIL_database"; exit 1; fi
    fi
    if [ -d "$RELEASE_DIR/app" ]; then
    echo "COPY scoped Laravel app runtime from Git SHA"
    mkdir -p "$APP/app"
    if command -v rsync >/dev/null 2>&1; then
      rsync -a "$RELEASE_DIR/app/" "$APP/app/"
    else
      cp -a "$RELEASE_DIR/app/." "$APP/app/"
    fi
    if [ $? -ne 0 ]; then echo "COPY_FAIL_app"; exit 1; fi
  fi
  if [ -d "$RELEASE_DIR/config" ]; then
    echo "COPY scoped Laravel config runtime from Git SHA"
    mkdir -p "$APP/config"
    if command -v rsync >/dev/null 2>&1; then
      rsync -a "$RELEASE_DIR/config/" "$APP/config/"
    else
      cp -a "$RELEASE_DIR/config/." "$APP/config/"
    fi
    if [ $? -ne 0 ]; then echo "COPY_FAIL_config"; exit 1; fi
  fi
  if [ -d "$RELEASE_DIR/routes" ]; then
    echo "COPY scoped Laravel routes runtime from Git SHA"
    mkdir -p "$APP/routes"
    if command -v rsync >/dev/null 2>&1; then
      rsync -a "$RELEASE_DIR/routes/" "$APP/routes/"
    else
      cp -a "$RELEASE_DIR/routes/." "$APP/routes/"
    fi
    if [ $? -ne 0 ]; then echo "COPY_FAIL_routes"; exit 1; fi
  fi
  if [ -d "$RELEASE_DIR/resources" ]; then
    echo "COPY scoped Laravel resources runtime from Git SHA"
    mkdir -p "$APP/resources"
    if command -v rsync >/dev/null 2>&1; then
      rsync -a "$RELEASE_DIR/resources/" "$APP/resources/"
    else
      cp -a "$RELEASE_DIR/resources/." "$APP/resources/"
    fi
    if [ $? -ne 0 ]; then echo "COPY_FAIL_resources"; exit 1; fi
  fi
  if [ -d "$RELEASE_DIR/dashboard" ]; then
    echo "COPY scoped dashboard runtime from Git SHA"
    mkdir -p "$APP/dashboard"
    if command -v rsync >/dev/null 2>&1; then
      rsync -a "$RELEASE_DIR/dashboard/" "$APP/dashboard/"
    else
      cp -a "$RELEASE_DIR/dashboard/." "$APP/dashboard/"
    fi
    if [ $? -ne 0 ]; then echo "COPY_FAIL_dashboard"; exit 1; fi
  fi
else
  for d in app bootstrap config database resources routes frontend dashboard public; do
    if [ -d "$RELEASE_DIR/$d" ]; then
      echo "COPY $d"
      cp -a "$RELEASE_DIR/$d/." "$APP/$d/"
      if [ $? -ne 0 ]; then echo "COPY_FAIL_$d"; exit 1; fi
    fi
  done

  for f in artisan composer.json composer.lock package.json package-lock.json vite.config.js vite.config.ts; do
    if [ -f "$RELEASE_DIR/$f" ]; then
      cp -a "$RELEASE_DIR/$f" "$APP/$f"
      if [ $? -ne 0 ]; then echo "COPY_FAIL_$f"; exit 1; fi
    fi
  done
fi

if [ "$APPLY_DELETIONS" = "1" ] && [ -f "$RELEASE_DIR/DELETE_RUNTIME_FILES" ]; then
  echo "=== PHASE: allowlisted runtime deletions ==="
  if [ -n "$SCRIPT_HELPERS" ] && [ -f "$SCRIPT_HELPERS/apply-delete-manifest.sh" ]; then
    APP_ROOT="$APP" DELETE_MANIFEST="$RELEASE_DIR/DELETE_RUNTIME_FILES" \
      bash "$SCRIPT_HELPERS/apply-delete-manifest.sh"
    if [ $? -ne 0 ]; then echo "DELETE_FAIL"; exit 1; fi
  else
    # Inline exact-path delete (same safety rules as tracked helper).
    while IFS= read -r rel || [ -n "$rel" ]; do
      [ -z "$rel" ] && continue
      case "$rel" in
        *'*'*|*'?'*|*'['*) echo "WILDCARD_FORBIDDEN=$rel"; exit 1 ;;
        /*|~*|../*|*/../*|*/..|..) echo "PATH_ESCAPE_FORBIDDEN=$rel"; exit 1 ;;
        frontend/*|app/*|config/*|routes/*) ;;
        *) echo "SCOPE_FORBIDDEN=$rel"; exit 1 ;;
      esac
      target="$APP/$rel"
      if [ -d "$target" ]; then
        echo "DIRECTORY_DELETE_FORBIDDEN=$rel"
        exit 1
      fi
      if [ -e "$target" ] || [ -L "$target" ]; then
        rm -f -- "$target"
        echo "DELETED=$rel"
      else
        echo "ALREADY_ABSENT=$rel"
      fi
    done < "$RELEASE_DIR/DELETE_RUNTIME_FILES"
  fi
fi

# Full-tree Laravel releases still run composer / asset mirror.
if [ "$SCOPE" != "frontend" ] && [ "$SCOPE" != "laravel" ] && [ "${SCOPED_FRONTEND_ONLY:-0}" != "1" ]; then
  echo "=== PHASE: composer install ==="
  cd "$APP"
  sudo -u "$RUNTIME_USER" "$PHP" "$(command -v composer)" install --no-dev --optimize-autoloader
  if [ $? -ne 0 ]; then echo "COMPOSER_FAIL"; exit 1; fi

  echo "=== PHASE: root vite build (if package.json present) ==="
  if [ -f package.json ]; then
    sudo -u "$RUNTIME_USER" npm ci
    if [ $? -ne 0 ]; then echo "ROOT_NPM_CI_FAIL"; exit 1; fi
    sudo -u "$RUNTIME_USER" npm run build
    if [ $? -ne 0 ]; then echo "ROOT_NPM_BUILD_FAIL"; exit 1; fi
  fi

  echo "=== PHASE: migrate status ==="
  sudo -u "$RUNTIME_USER" "$PHP" artisan migrate:status | tail -3
  PENDING=$(sudo -u "$RUNTIME_USER" "$PHP" artisan migrate:status 2>/dev/null | grep -c Pending || true)
  echo "PENDING_MIGRATIONS=$PENDING"
  if [ "$PENDING" != "0" ]; then
    echo "UNEXPECTED_PENDING_MIGRATIONS"
    exit 1
  fi

  echo "=== PHASE: mirror public assets to public_html ==="
  for d in themes css js build client-assets; do
    if [ -d "$APP/public/$d" ]; then
      if [ "$d" = "client-assets" ]; then
        mkdir -p "$WEB/client-assets"
        cp -a "$APP/public/client-assets/." "$WEB/client-assets/" 2>/dev/null || true
      else
        mkdir -p "$WEB/$d"
        cp -a "$APP/public/$d/." "$WEB/$d/"
        if [ $? -ne 0 ]; then echo "MIRROR_FAIL_$d"; exit 1; fi
      fi
    fi
  done

  echo "=== PHASE: verify storage symlink ==="
  ls -la "$WEB/storage"
  if [ ! -L "$WEB/storage" ]; then
    echo "STORAGE_SYMLINK_BROKEN"
    exit 1
  fi
else
  echo "=== PHASE: scoped frontend logo mirror (if present) ==="
  if [ -f "$APP/frontend/public/client-assets/jetpk/logo/logo.png" ]; then
    mkdir -p "$WEB/client-assets/jetpk/logo"
    cp -a "$APP/frontend/public/client-assets/jetpk/logo/logo.png" "$WEB/client-assets/jetpk/logo/logo.png"
  fi
  if [ -f "$APP/frontend/public/client-assets/jetpk/logo/logo.svg" ]; then
    mkdir -p "$WEB/client-assets/jetpk/logo"
    cp -a "$APP/frontend/public/client-assets/jetpk/logo/logo.svg" "$WEB/client-assets/jetpk/logo/logo.svg"
  fi
  if [ -f "$APP/frontend/public/client-assets/jetpk-assets/logo/logo.png" ]; then
    mkdir -p "$WEB/client-assets/jetpk-assets/logo"
    cp -a "$APP/frontend/public/client-assets/jetpk-assets/logo/logo.png" "$WEB/client-assets/jetpk-assets/logo/logo.png"
  fi
fi

PHP="${PHP:-/usr/local/lsws/lsphp83/bin/php}"
if [ "$SCOPE" = "laravel" ] || [ "$SCOPE" = "frontend" ]; then
  if [ -f "$RELEASE_DIR/.jetpk-release-meta.env" ]; then
    # shellcheck disable=SC1090
    . "$RELEASE_DIR/.jetpk-release-meta.env"
  fi
  if [ -n "${STAGED_SOURCE_SHA:-}" ]; then
    printf '%s' "$STAGED_SOURCE_SHA" > "$APP/.jetpk-runtime-sha"
    printf '%s' "$STAGED_SOURCE_SHA" > "$APP/.jetpk-authorized-sha"
    chown "$RUNTIME_USER:$RUNTIME_GROUP" "$APP/.jetpk-runtime-sha" "$APP/.jetpk-authorized-sha" 2>/dev/null || true
    echo "PRODUCTION_RUNTIME_SHA=$STAGED_SOURCE_SHA"
  fi
  if [ -x "$PHP" ]; then
    sudo -u "$RUNTIME_USER" "$PHP" "$APP/artisan" optimize:clear
    echo "OPTIMIZE_CLEAR=OK"
    if [ "$SCOPE" = "laravel" ] && [ -d "$RELEASE_DIR/database/migrations" ]; then
      sudo -u "$RUNTIME_USER" "$PHP" "$APP/artisan" migrate --force --no-interaction
      echo "MIGRATE_OK"
    fi
  fi
fi

echo "=== PHASE: runtime ownership normalize + assert ==="
if [ -f "$OWNERSHIP_HELPER" ]; then
  APP_ROOT="$APP" RUNTIME_USER="$RUNTIME_USER" RUNTIME_GROUP="$RUNTIME_GROUP" MODE=normalize \
    bash "$OWNERSHIP_HELPER"
  if [ $? -ne 0 ]; then echo "RUNTIME_OWNERSHIP_NORMALIZE_FAIL"; exit 1; fi
  APP_ROOT="$APP" RUNTIME_USER="$RUNTIME_USER" RUNTIME_GROUP="$RUNTIME_GROUP" MODE=assert \
    bash "$OWNERSHIP_HELPER"
  if [ $? -ne 0 ]; then echo "RUNTIME_OWNERSHIP_ASSERT_FAIL"; exit 1; fi
else
  echo "OWNERSHIP_HELPER_MISSING=$OWNERSHIP_HELPER"
  exit 1
fi

echo "LARAVEL_DEPLOY_COMPLETE"
echo "DEPLOY_TIMESTAMP=$TS"
echo "DEPLOY_RELEASE_DIR=$RELEASE_DIR"
