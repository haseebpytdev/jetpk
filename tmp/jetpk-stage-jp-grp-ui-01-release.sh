#!/usr/bin/env bash
# Stage JP-GRP-UI-01 runtime from AUTHORIZED_SHA (Git objects only).
set -euo pipefail

AUTHORIZED_SHA="${AUTHORIZED_SHA:?AUTHORIZED_SHA required}"
BASE_SHA="${BASE_SHA:-636584a395cbc93221d7f005fcde7311915f973e}"
REPO_ROOT="${REPO_ROOT:-$(git rev-parse --show-toplevel)}"
MANIFEST="${REPO_ROOT}/tmp/jp-grp-ui-01/runtime-manifest.txt"
EXPECTED_COUNT="${EXPECTED_COUNT:-35}"

cd "${REPO_ROOT}"
AUTHORIZED_SHA="$(git rev-parse "${AUTHORIZED_SHA}")"
BASE_SHA="$(git rev-parse "${BASE_SHA}")"

[[ -f "${MANIFEST}" ]] || { echo MANIFEST_MISSING; exit 1; }

mapfile -t RUNTIME < "${MANIFEST}"
COUNT=0
CLEAN=()
for path in "${RUNTIME[@]}"; do
  path="${path//$'\r'/}"
  path="${path#"${path%%[![:space:]]*}"}"
  path="${path%"${path##*[![:space:]]}"}"
  [[ -n "${path}" ]] || continue
  CLEAN+=("${path}")
  COUNT=$((COUNT + 1))
done
[[ "${COUNT}" -eq "${EXPECTED_COUNT}" ]] || {
  echo "RUNTIME_MANIFEST_COUNT_FAIL expected=${EXPECTED_COUNT} got=${COUNT}"
  exit 1
}

LARAVEL_COUNT=0
CONFIG_COUNT=0
FE_COUNT=0
for path in "${CLEAN[@]}"; do
  case "${path}" in
    app/*) LARAVEL_COUNT=$((LARAVEL_COUNT + 1)) ;;
    config/*) CONFIG_COUNT=$((CONFIG_COUNT + 1)) ;;
    frontend/*) FE_COUNT=$((FE_COUNT + 1)) ;;
    *)
      echo "UNEXPECTED_RUNTIME_PATH=${path}"
      exit 1
      ;;
  esac
done

echo "STAGED_LARAVEL=${LARAVEL_COUNT}"
echo "STAGED_CONFIG=${CONFIG_COUNT}"
echo "STAGED_FRONTEND=${FE_COUNT}"
echo "STAGED_TOTAL=${COUNT}"
echo "EXACT_DEPLOYABLE_FILE_COUNT=${COUNT}"
echo "MIGRATIONS=0"
[[ "${LARAVEL_COUNT}" -eq 10 ]] || { echo "LARAVEL_COUNT_FAIL expected=10 got=${LARAVEL_COUNT}"; exit 1; }
[[ "${CONFIG_COUNT}" -eq 0 ]] || { echo CONFIG_COUNT_FAIL; exit 1; }
[[ "${FE_COUNT}" -eq 25 ]] || { echo "FE_COUNT_FAIL expected=25 got=${FE_COUNT}"; exit 1; }

if git diff --name-only "${BASE_SHA}".."${AUTHORIZED_SHA}" | grep -E '^database/migrations/' >/dev/null; then
  echo "MIGRATION_IN_RUNTIME_DIFF_HARD_STOP"
  exit 1
fi

for path in "${CLEAN[@]}"; do
  git cat-file -e "${AUTHORIZED_SHA}:${path}" 2>/dev/null || {
    echo "PATH_MISSING_AT_SHA=${path}"
    exit 1
  }
done

RELEASE_TIMESTAMP="$(date -u +%Y%m%dT%H%M%SZ)"
STAGE_ROOT="${REPO_ROOT}/tmp/releases"
RELEASE_DIR="${STAGE_ROOT}/jetpk-jp-grp-ui-01-${RELEASE_TIMESTAMP}"
mkdir -p "${RELEASE_DIR}"

for path in "${CLEAN[@]}"; do
  dest="${RELEASE_DIR}/${path}"
  mkdir -p "$(dirname "${dest}")"
  git show "${AUTHORIZED_SHA}:${path}" > "${dest}"
done
printf '%s\n' "${CLEAN[@]}" > "${RELEASE_DIR}/STAGED_RUNTIME_FILES"
: > "${RELEASE_DIR}/DELETE_RUNTIME_FILES"

{
  echo "RELEASE_TIMESTAMP=${RELEASE_TIMESTAMP}"
  echo "STAGED_SOURCE_SHA=${AUTHORIZED_SHA}"
  echo "BASE_SHA=${BASE_SHA}"
  echo "AUTHORIZED_BRANCH=phase/jp-grp-ui-01"
  echo "RELEASE_SCOPE=jp-grp-ui-01"
  echo "STAGED_RUNTIME_FILES=${COUNT}"
  echo "STAGED_LARAVEL=${LARAVEL_COUNT}"
  echo "STAGED_CONFIG=${CONFIG_COUNT}"
  echo "STAGED_FRONTEND=${FE_COUNT}"
  echo "MIGRATIONS=0"
  echo "RELEASE_STAGED_AT=${RELEASE_DIR}"
} > "${RELEASE_DIR}/.jetpk-release-meta.env"

ARCHIVE="${STAGE_ROOT}/jetpk-jp-grp-ui-01-${AUTHORIZED_SHA:0:12}-${RELEASE_TIMESTAMP}.tar.gz"
( cd "${RELEASE_DIR}" && tar --force-local -czf "${ARCHIVE}" . )

drift=0
while IFS= read -r path; do
  [[ -n "${path}" ]] || continue
  staged_hash="$(sha256sum "${RELEASE_DIR}/${path}" | awk '{print $1}')"
  git_hash="$(git show "${AUTHORIZED_SHA}:${path}" | sha256sum | awk '{print $1}')"
  if [[ "${staged_hash}" != "${git_hash}" ]]; then
    echo "STAGED_SOURCE_DRIFT_PATH=${path}"
    drift=$((drift + 1))
  fi
done < "${RELEASE_DIR}/STAGED_RUNTIME_FILES"
echo "STAGED_SOURCE_DRIFT=${drift}"
[[ "${drift}" -eq 0 ]] || exit 1
echo "STAGED_SOURCE_PARITY=PASS"
echo "STAGED_SOURCE_SHA=${AUTHORIZED_SHA}"
echo "RELEASE_ARCHIVE=${ARCHIVE}"
echo "RELEASE_STAGED_AT=${RELEASE_DIR}"
echo "RELEASE_TIMESTAMP=${RELEASE_TIMESTAMP}"
echo "RELEASE_STAGED_COMPLETE"
