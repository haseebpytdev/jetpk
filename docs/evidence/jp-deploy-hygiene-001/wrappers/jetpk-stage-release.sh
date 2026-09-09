#!/bin/bash
# JetPakistan protected staging wrapper.
# Stages runtime files from an explicit AUTHORIZED_SHA via tracked tooling.
# Never uses a hard-coded historical archive SHA.
#
# Required:
#   AUTHORIZED_SHA=<sha>
#
# Optional:
#   BASE_SHA=0ebb2278a436f9367266cfe11e50100c7369704b
#   AUTHORIZED_BRANCH=<current branch>
#   LOCAL_ONLY=1          # stage locally only (no remote extract)
#   REMOTE_HOST=...       # production host for protected remote stage
#   REMOTE_USER=root
#   SSH_KEY=...
#   SSH_PORT=22
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
TRACKED_STAGE="${REPO_ROOT}/scripts/jetpk/stage-release-from-sha.sh"

AUTHORIZED_SHA="${AUTHORIZED_SHA:?AUTHORIZED_SHA is required}"
BASE_SHA="${BASE_SHA:-0ebb2278a436f9367266cfe11e50100c7369704b}"
AUTHORIZED_BRANCH="${AUTHORIZED_BRANCH:-$(git -C "${REPO_ROOT}" branch --show-current)}"
LOCAL_ONLY="${LOCAL_ONLY:-0}"
RELEASE_SCOPE="${RELEASE_SCOPE:-frontend}"

if [[ ! -f "${TRACKED_STAGE}" ]]; then
  echo "TRACKED_STAGE_MISSING=${TRACKED_STAGE}"
  exit 1
fi

export AUTHORIZED_SHA BASE_SHA AUTHORIZED_BRANCH RELEASE_SCOPE REPO_ROOT
STAGE_OUTPUT="$(bash "${TRACKED_STAGE}")"
echo "${STAGE_OUTPUT}"

RELEASE_STAGED_AT="$(echo "${STAGE_OUTPUT}" | sed -n 's/^RELEASE_STAGED_AT=//p' | tail -n1)"
RELEASE_TIMESTAMP="$(echo "${STAGE_OUTPUT}" | sed -n 's/^RELEASE_TIMESTAMP=//p' | tail -n1)"
RELEASE_ARCHIVE="$(echo "${STAGE_OUTPUT}" | sed -n 's/^RELEASE_ARCHIVE=//p' | tail -n1)"
STAGED_SOURCE_SHA="$(echo "${STAGE_OUTPUT}" | sed -n 's/^STAGED_SOURCE_SHA=//p' | tail -n1)"

if [[ -z "${RELEASE_STAGED_AT}" || -z "${RELEASE_ARCHIVE}" ]]; then
  echo "STAGE_OUTPUT_PARSE_FAIL"
  exit 1
fi

if [[ "${STAGED_SOURCE_SHA}" != "$(git -C "${REPO_ROOT}" rev-parse "${AUTHORIZED_SHA}")" ]]; then
  echo "STAGED_SOURCE_SHA_MISMATCH"
  exit 1
fi

# Git is authoritative: include scoped Laravel runtime files from the same SHA.
# Tracked stager is frontend-only; these paths are taken from Git objects, never the worktree.
# Wave-7+ may also change config/ and routes/ (never migrations).
mapfile -t LARAVEL_RUNTIME < <(git -C "${REPO_ROOT}" diff --name-only "${BASE_SHA}".."${STAGED_SOURCE_SHA}" | grep -E '^(app/|config/|routes/|resources/views/|database/migrations/|ai-assistant/knowledge/)' || true)
if git -C "${REPO_ROOT}" diff --name-only "${BASE_SHA}".."${STAGED_SOURCE_SHA}" | grep -E '^database/migrations/' >/dev/null; then
  echo "MIGRATION_IN_RUNTIME_DIFF_ALLOWED=1"
fi
if [[ "${#LARAVEL_RUNTIME[@]}" -gt 0 ]]; then
  git -C "${REPO_ROOT}" archive --format=tar "${STAGED_SOURCE_SHA}" -- "${LARAVEL_RUNTIME[@]}" | tar -x -C "${RELEASE_STAGED_AT}"
  printf '%s\n' "${LARAVEL_RUNTIME[@]}" >> "${RELEASE_STAGED_AT}/STAGED_RUNTIME_FILES"
  FE_COUNT="$(grep -c . "${RELEASE_STAGED_AT}/STAGED_RUNTIME_FILES" || true)"
  {
    echo "RELEASE_TIMESTAMP=${RELEASE_TIMESTAMP}"
    echo "STAGED_SOURCE_SHA=${STAGED_SOURCE_SHA}"
    echo "BASE_SHA=$(git -C "${REPO_ROOT}" rev-parse "${BASE_SHA}")"
    echo "AUTHORIZED_BRANCH=${AUTHORIZED_BRANCH}"
    echo "RELEASE_SCOPE=${RELEASE_SCOPE}"
    echo "LARAVEL_RUNTIME_FILES=${#LARAVEL_RUNTIME[@]}"
    echo "STAGED_RUNTIME_FILES=${FE_COUNT}"
    echo "RELEASE_STAGED_AT=${RELEASE_STAGED_AT}"
  } > "${RELEASE_STAGED_AT}/.jetpk-release-meta.env"
  tar -czf "${RELEASE_ARCHIVE}" -C "${RELEASE_STAGED_AT}" .
  echo "LARAVEL_RUNTIME_MERGED=${#LARAVEL_RUNTIME[@]}"
  printf '%s\n' "${LARAVEL_RUNTIME[@]}"
fi

mapfile -t DASHBOARD_RUNTIME < <(git -C "${REPO_ROOT}" diff --name-only "${BASE_SHA}".."${STAGED_SOURCE_SHA}" | grep -E '^dashboard/' | grep -Ev '\.(md|spec\.ts|spec\.tsx|test\.ts|test\.tsx)$' || true)
if [[ "${#DASHBOARD_RUNTIME[@]}" -gt 0 ]]; then
  git -C "${REPO_ROOT}" archive --format=tar "${STAGED_SOURCE_SHA}" -- "${DASHBOARD_RUNTIME[@]}" | tar -x -C "${RELEASE_STAGED_AT}"
  printf '%s\n' "${DASHBOARD_RUNTIME[@]}" >> "${RELEASE_STAGED_AT}/STAGED_RUNTIME_FILES"
  {
    echo "DASHBOARD_RUNTIME_FILES=${#DASHBOARD_RUNTIME[@]}"
  } >> "${RELEASE_STAGED_AT}/.jetpk-release-meta.env"
  tar -czf "${RELEASE_ARCHIVE}" -C "${RELEASE_STAGED_AT}" .
  echo "DASHBOARD_RUNTIME_MERGED=${#DASHBOARD_RUNTIME[@]}"
fi

# Unexpected if outside frontend/app/config/routes (tests/docs/tmp excluded from runtime payload).
UNEXPECTED_RUNTIME="$(git -C "${REPO_ROOT}" diff --name-only "${BASE_SHA}".."${STAGED_SOURCE_SHA}" \
  | grep -Ev '^(frontend/|app/|config/|routes/|resources/views/|database/migrations/|dashboard/|ai-assistant/knowledge/|scripts/jetpk/)' \
  | grep -Ev '^(frontend/tests/|tests/|docs/|tmp/|deploy/)' \
  | grep -Ev '^\.env\.example$' \
  | grep -Ev '\.(md|spec\.ts|spec\.tsx|test\.ts|test\.tsx|test\.mjs)$' || true)"
if [[ -n "${UNEXPECTED_RUNTIME}" ]]; then
  echo "UNEXPECTED_RUNTIME_SUBSYSTEM"
  echo "${UNEXPECTED_RUNTIME}"
  exit 1
fi
echo "UNEXPECTED_RUNTIME_FILES=0"

# Prove the obsolete hard-coded archive path is not used.
if echo "${STAGE_OUTPUT}${RELEASE_ARCHIVE}" | grep -q 'b95efd4'; then
  echo "STALE_HARDCODED_ARCHIVE_STILL_REFERENCED"
  exit 1
fi

if [[ "${LOCAL_ONLY}" = "1" ]]; then
  echo "LOCAL_STAGE_ONLY=1"
  echo "RELEASE_STAGED_AT=${RELEASE_STAGED_AT}"
  echo "RELEASE_TIMESTAMP=${RELEASE_TIMESTAMP}"
  echo "STAGED_SOURCE_SHA=${STAGED_SOURCE_SHA}"
  echo "RELEASE_STAGED_COMPLETE"
  exit 0
fi

REMOTE_HOST="${REMOTE_HOST:-185.215.166.176}"
REMOTE_USER="${REMOTE_USER:-root}"
SSH_PORT="${SSH_PORT:-22}"
# Prefer Windows user profile key when running under Git Bash.
if [[ -z "${SSH_KEY:-}" ]]; then
  if [[ -f "${USERPROFILE:-}/.ssh/jetpk_contabo_2026_v2" ]]; then
    SSH_KEY="${USERPROFILE}/.ssh/jetpk_contabo_2026_v2"
  else
    SSH_KEY="${HOME}/.ssh/jetpk_contabo_2026_v2"
  fi
fi
REMOTE_RELEASE_ROOT="/home/pkjetp/releases/jetpk-${RELEASE_TIMESTAMP}"
REMOTE_ARCHIVE="/home/pkjetp/releases/$(basename "${RELEASE_ARCHIVE}")"

SSH_CFG_NULL=/dev/null
# Git Bash on Windows cannot open the Windows NUL device via OpenSSH -F.
case "$(uname -s 2>/dev/null || true)" in
  MINGW*|MSYS*|CYGWIN*)
    WINDOWS_SSH="/c/Windows/System32/OpenSSH/ssh.exe"
    WINDOWS_SCP="/c/Windows/System32/OpenSSH/scp.exe"
    if [[ ! -x "${WINDOWS_SSH}" || ! -x "${WINDOWS_SCP}" ]]; then
      echo "WINDOWS_OPENSSH_MISSING"
      exit 1
    fi
    SSH_KEY_FOR_CLIENT="$(cygpath -w "${SSH_KEY}")"
    RELEASE_ARCHIVE_FOR_SCP="$(cygpath -w "${RELEASE_ARCHIVE}")"
    DELETE_MANIFEST_FOR_SCP="$(cygpath -w "${RELEASE_STAGED_AT}/DELETE_RUNTIME_FILES")"
    SSH=("${WINDOWS_SSH}" -i "${SSH_KEY_FOR_CLIENT}" -p "${SSH_PORT}" -o IdentitiesOnly=yes -o BatchMode=yes -o ConnectTimeout=15)
    SCP=("${WINDOWS_SCP}" -i "${SSH_KEY_FOR_CLIENT}" -P "${SSH_PORT}" -o IdentitiesOnly=yes -o BatchMode=yes -o ConnectTimeout=15)
    echo "SSH_CLIENT=${WINDOWS_SSH}"
    echo "SCP_CLIENT=${WINDOWS_SCP}"
    ;;
  *)
    SSH=(ssh -F "${SSH_CFG_NULL}" -i "${SSH_KEY}" -p "${SSH_PORT}" -o IdentitiesOnly=yes -o BatchMode=yes -o ConnectTimeout=15)
    SCP=(scp -F "${SSH_CFG_NULL}" -i "${SSH_KEY}" -P "${SSH_PORT}" -o IdentitiesOnly=yes -o BatchMode=yes -o ConnectTimeout=15)
    RELEASE_ARCHIVE_FOR_SCP="${RELEASE_ARCHIVE}"
    DELETE_MANIFEST_FOR_SCP="${RELEASE_STAGED_AT}/DELETE_RUNTIME_FILES"
    ;;
esac

"${SSH[@]}" "${REMOTE_USER}@${REMOTE_HOST}" "mkdir -p /home/pkjetp/releases"
"${SCP[@]}" "${RELEASE_ARCHIVE_FOR_SCP}" "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_ARCHIVE}"
"${SCP[@]}" "${DELETE_MANIFEST_FOR_SCP}" "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_RELEASE_ROOT}.DELETE_RUNTIME_FILES.tmp"
"${SSH[@]}" "${REMOTE_USER}@${REMOTE_HOST}" "mkdir -p '${REMOTE_RELEASE_ROOT}' && tar xzf '${REMOTE_ARCHIVE}' -C '${REMOTE_RELEASE_ROOT}' && mv -f '${REMOTE_RELEASE_ROOT}.DELETE_RUNTIME_FILES.tmp' '${REMOTE_RELEASE_ROOT}/DELETE_RUNTIME_FILES' && chown -R pkjetp:pkjetp '${REMOTE_RELEASE_ROOT}' && test -f '${REMOTE_RELEASE_ROOT}/.jetpk-release-meta.env' && echo REMOTE_EXTRACT_OK"

echo "RELEASE_STAGED_AT=${REMOTE_RELEASE_ROOT}"
echo "RELEASE_TIMESTAMP=${RELEASE_TIMESTAMP}"
echo "STAGED_SOURCE_SHA=${STAGED_SOURCE_SHA}"
echo "REMOTE_ARCHIVE=${REMOTE_ARCHIVE}"
echo "RELEASE_STAGED_COMPLETE"
