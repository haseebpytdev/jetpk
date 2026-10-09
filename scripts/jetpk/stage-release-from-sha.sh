#!/usr/bin/env bash
# Generic JetPakistan release staging from an explicit AUTHORIZED_SHA.
# Git is the only source of truth. No secrets. No hard-coded archives.
#
# Required:
#   AUTHORIZED_SHA=<full-or-abbrev git object>
#
# Recommended:
#   BASE_SHA=<frozen baseline for scoped runtime diff>
#   AUTHORIZED_BRANCH=<branch that must contain AUTHORIZED_SHA>
#
# Optional:
#   REPO_ROOT=...
#   STAGE_ROOT=...          (default: <repo>/tmp/releases)
#   RELEASE_SCOPE=frontend  (default; public frontend + dashboard + scoped Laravel runtime)
#   RELEASE_SCOPE=laravel   (app/config/routes/resources/views only)
#   RELEASE_SCOPE=dashboard (dashboard runtime only)
#   RELEASE_SCOPE=dashboard-laravel (dashboard + scoped Laravel; no public frontend)
#   DRY_RUN=1               (emit manifest only; do not extract archive)
#   LOCAL_ONLY=1            (unused here; reserved for wrappers)
#
# Emits machine-readable KEY=value lines and writes:
#   RELEASE_STAGED_AT/<meta>
set -euo pipefail

AUTHORIZED_SHA="${AUTHORIZED_SHA:?AUTHORIZED_SHA is required}"
BASE_SHA="${BASE_SHA:-}"
AUTHORIZED_BRANCH="${AUTHORIZED_BRANCH:-}"
RELEASE_SCOPE="${RELEASE_SCOPE:-frontend}"
DRY_RUN="${DRY_RUN:-0}"

REPO_ROOT="${REPO_ROOT:-$(git rev-parse --show-toplevel 2>/dev/null || true)}"
if [[ -z "${REPO_ROOT}" || ! -d "${REPO_ROOT}/.git" ]]; then
  echo "REPO_ROOT_INVALID"
  exit 1
fi
cd "${REPO_ROOT}"

STAGE_ROOT="${STAGE_ROOT:-${REPO_ROOT}/tmp/releases}"
mkdir -p "${STAGE_ROOT}"

if ! git cat-file -e "${AUTHORIZED_SHA}^{commit}" 2>/dev/null; then
  echo "AUTHORIZED_SHA_MISSING=${AUTHORIZED_SHA}"
  exit 1
fi
AUTHORIZED_SHA="$(git rev-parse "${AUTHORIZED_SHA}")"

if [[ -n "${AUTHORIZED_BRANCH}" ]]; then
  if ! git rev-parse --verify "${AUTHORIZED_BRANCH}" >/dev/null 2>&1; then
    echo "AUTHORIZED_BRANCH_MISSING=${AUTHORIZED_BRANCH}"
    exit 1
  fi
  if ! git merge-base --is-ancestor "${AUTHORIZED_SHA}" "${AUTHORIZED_BRANCH}"; then
    echo "AUTHORIZED_SHA_NOT_ON_BRANCH=${AUTHORIZED_BRANCH}"
    exit 1
  fi
fi

if [[ -z "${BASE_SHA}" ]]; then
  echo "BASE_SHA_REQUIRED_FOR_SCOPED_RUNTIME_MANIFEST"
  exit 1
fi
if ! git cat-file -e "${BASE_SHA}^{commit}" 2>/dev/null; then
  echo "BASE_SHA_MISSING=${BASE_SHA}"
  exit 1
fi
BASE_SHA="$(git rev-parse "${BASE_SHA}")"

RELEASE_TIMESTAMP="$(date -u +%Y%m%dT%H%M%SZ)"
RELEASE_DIR="${STAGE_ROOT}/jetpk-${RELEASE_TIMESTAMP}"
mkdir -p "${RELEASE_DIR}"

is_excluded_runtime_path() {
  local path="$1"
  case "${path}" in
    frontend/tests/*|*/tests/*) return 0 ;;
    *.spec.ts|*.spec.tsx|*.test.ts|*.test.tsx|*.test.mjs|*.test.js) return 0 ;;
    frontend/tmp/*|frontend/node_modules/*|frontend/.next/*) return 0 ;;
    docs/*|tmp/*|*.md|*.log) return 0 ;;
    *.png|*.jpg|*.jpeg|*.webp)
      # Allow tracked JetPakistan brand assets under client-assets; exclude other images.
      [[ "${path}" == frontend/public/client-assets/* ]] && return 1
      return 0
      ;;
    .env|.env.*|*.pem|*.key|*.p12) return 0 ;;
    *) return 1 ;;
  esac
}

is_dashboard_runtime_path() {
  local path="$1"
  [[ "${path}" == dashboard/* ]] || return 1
  case "${path}" in
    dashboard/tests/*|dashboard/**/*.spec.ts|dashboard/**/*.spec.tsx|dashboard/**/*.test.ts|dashboard/**/*.test.tsx|dashboard/**/*.test.mjs) return 1 ;;
    dashboard/**/*.md) return 1 ;;
    *) return 0 ;;
  esac
}

is_laravel_runtime_path() {
  local path="$1"
  case "${path}" in
    app/*|config/*|routes/*|resources/views/*|database/migrations/*|ai-assistant/knowledge/*)
      is_excluded_runtime_path "${path}" && return 1
      return 0
      ;;
    *)
      return 1
      ;;
  esac
}

is_allowed_runtime_path() {
  local path="$1"
  case "${RELEASE_SCOPE}" in
    frontend)
      if [[ "${path}" == frontend/* ]]; then
        is_excluded_runtime_path "${path}" && return 1
        return 0
      fi
      if is_dashboard_runtime_path "${path}"; then
        return 0
      fi
      if is_laravel_runtime_path "${path}"; then
        return 0
      fi
      return 1
      ;;
    laravel)
      is_laravel_runtime_path "${path}"
      ;;
    dashboard)
      is_dashboard_runtime_path "${path}"
      ;;
    dashboard-laravel)
      if is_dashboard_runtime_path "${path}"; then
        return 0
      fi
      is_laravel_runtime_path "${path}"
      ;;
    *)
      echo "UNSUPPORTED_RELEASE_SCOPE=${RELEASE_SCOPE}"
      exit 1
      ;;
  esac
}

UPLOADABLE=()
DELETIONS=()
RUNTIME_ENTRIES=0

while IFS=$'\t' read -r status path extra; do
  # Handle renames: status like R100, path=old, extra=new
  if [[ "${status}" == R* ]]; then
    old_path="${path}"
    new_path="${extra}"
    if is_allowed_runtime_path "${old_path}"; then
      DELETIONS+=("${old_path}")
      RUNTIME_ENTRIES=$((RUNTIME_ENTRIES + 1))
    fi
    if is_allowed_runtime_path "${new_path}"; then
      UPLOADABLE+=("${new_path}")
      RUNTIME_ENTRIES=$((RUNTIME_ENTRIES + 1))
    fi
    continue
  fi

  if ! is_allowed_runtime_path "${path}"; then
    continue
  fi

  RUNTIME_ENTRIES=$((RUNTIME_ENTRIES + 1))
  if [[ "${status}" == D ]]; then
    DELETIONS+=("${path}")
  else
    UPLOADABLE+=("${path}")
  fi
done < <(git diff --name-status "${BASE_SHA}".."${AUTHORIZED_SHA}")

# Deploy wrapper expects a frontend/ directory even for dashboard/Laravel-only diffs.
if [[ "${RELEASE_SCOPE}" == "frontend" || "${RELEASE_SCOPE}" == "dashboard-laravel" ]]; then
  has_frontend_path=0
  if [[ "${#UPLOADABLE[@]}" -gt 0 ]]; then
    for staged_path in "${UPLOADABLE[@]}"; do
      if [[ "${staged_path}" == frontend/* ]]; then
        has_frontend_path=1
        break
      fi
    done
  fi
  if [[ "${has_frontend_path}" -eq 0 ]]; then
    UPLOADABLE+=("frontend/package.json")
    RUNTIME_ENTRIES=$((RUNTIME_ENTRIES + 1))
  fi
fi

# dashboard-laravel releases use the same deploy path as scoped frontend releases.
if [[ "${RELEASE_SCOPE}" == "dashboard-laravel" ]]; then
  RELEASE_SCOPE="frontend"
fi

if [[ "${RUNTIME_ENTRIES}" -eq 0 ]]; then
  echo "EMPTY_RUNTIME_MANIFEST"
  exit 1
fi

if [[ "${DRY_RUN}" == "1" ]]; then
  echo "DRY_RUN=1"
  echo "MANIFEST_UPLOADABLE_COUNT=${#UPLOADABLE[@]}"
  echo "MANIFEST_DELETION_COUNT=${#DELETIONS[@]}"
  printf '%s\n' "${UPLOADABLE[@]}"
  echo "RELEASE_STAGED_DRY_RUN_COMPLETE"
  exit 0
fi

# Stage uploadable files strictly from AUTHORIZED_SHA (never working tree).
if [[ "${#UPLOADABLE[@]}" -gt 0 ]]; then
  git archive --format=tar "${AUTHORIZED_SHA}" -- "${UPLOADABLE[@]}" | tar -x -C "${RELEASE_DIR}"
fi

printf '%s\n' "${UPLOADABLE[@]}" > "${RELEASE_DIR}/STAGED_RUNTIME_FILES"
printf '%s\n' "${DELETIONS[@]}" > "${RELEASE_DIR}/DELETE_RUNTIME_FILES"
printf '%s\n' "${DELETIONS[@]}" > "${RELEASE_DIR}/STAGED_DELETIONS"

{
  echo "RELEASE_TIMESTAMP=${RELEASE_TIMESTAMP}"
  echo "STAGED_SOURCE_SHA=${AUTHORIZED_SHA}"
  echo "BASE_SHA=${BASE_SHA}"
  echo "AUTHORIZED_BRANCH=${AUTHORIZED_BRANCH}"
  echo "RELEASE_SCOPE=${RELEASE_SCOPE}"
  echo "EXPECTED_RUNTIME_FILES=${RUNTIME_ENTRIES}"
  echo "UPLOADABLE_RUNTIME_FILES=${#UPLOADABLE[@]}"
  echo "REMOVED_RUNTIME_FILES=${#DELETIONS[@]}"
  echo "STAGED_RUNTIME_FILES=${#UPLOADABLE[@]}"
  echo "STAGED_DELETIONS=${#DELETIONS[@]}"
  echo "RELEASE_STAGED_AT=${RELEASE_DIR}"
} > "${RELEASE_DIR}/.jetpk-release-meta.env"

# Convenience archive for protected transfer (no secrets).
ARCHIVE="${STAGE_ROOT}/jetpk-release-${AUTHORIZED_SHA:0:12}-${RELEASE_TIMESTAMP}.tar.gz"
tar -czf "${ARCHIVE}" -C "${RELEASE_DIR}" .

# Re-emit for wrappers / operators.
cat "${RELEASE_DIR}/.jetpk-release-meta.env"
echo "RELEASE_ARCHIVE=${ARCHIVE}"
DELETE_JOINED=""
if [[ -s "${RELEASE_DIR}/DELETE_RUNTIME_FILES" ]]; then
  DELETE_JOINED="$(tr '\n' ',' < "${RELEASE_DIR}/DELETE_RUNTIME_FILES" | sed 's/,$//')"
fi
echo "DELETE_RUNTIME_FILES=${DELETE_JOINED}"
echo "REMOVED_RUNTIME_PATH=${DELETIONS[0]:-}"
echo "RELEASE_STAGED_COMPLETE"
