#!/usr/bin/env bash
# Release retention helper — DEFAULT MODE IS DRY-RUN.
# Lists old release candidates under RELEASES_ROOT while keeping active + rollback.
#
# Usage:
#   ACTIVE_SHA=78dadc7b... ROLLBACK_SHA=cbd7686f... \
#   bash scripts/jetpk/release-retention-dry-run.sh
#
# Optional:
#   RELEASES_ROOT=/home/pkjetp/releases
#   APPLY=1   # only deletes paths listed in exact DELETE_MANIFEST after zero-ref checks
#   DELETE_MANIFEST=/path/to/exact-paths.txt
set -euo pipefail

ACTIVE_SHA="${ACTIVE_SHA:?ACTIVE_SHA required}"
ROLLBACK_SHA="${ROLLBACK_SHA:?ROLLBACK_SHA required}"
RELEASES_ROOT="${RELEASES_ROOT:-/home/pkjetp/releases}"
APPLY="${APPLY:-0}"
MODE=DRY-RUN

if [[ ! -d "${RELEASES_ROOT}" ]]; then
  echo "RELEASES_ROOT_MISSING=${RELEASES_ROOT}"
  echo "RELEASE_RETENTION_GUARD=FAIL"
  exit 1
fi

echo "RELEASE_RETENTION_MODE=${MODE}"
echo "ACTIVE_SHA=${ACTIVE_SHA}"
echo "ROLLBACK_SHA=${ROLLBACK_SHA}"
echo "RELEASES_ROOT=${RELEASES_ROOT}"

keep_match() {
  local name="$1"
  [[ "${name}" == *"${ACTIVE_SHA:0:8}"* ]] && return 0
  [[ "${name}" == *"${ACTIVE_SHA:0:12}"* ]] && return 0
  [[ "${name}" == *"${ROLLBACK_SHA:0:8}"* ]] && return 0
  [[ "${name}" == *"${ROLLBACK_SHA:0:12}"* ]] && return 0
  [[ "${name}" == *jp-final-78dadc* ]] && return 0
  return 1
}

candidates=0
kept=0
while IFS= read -r -d '' entry; do
  base="$(basename "${entry}")"
  if keep_match "${base}"; then
    echo "KEEP_CANDIDATE=${entry}"
    kept=$((kept + 1))
  else
    echo "DELETE_CANDIDATE=${entry}"
    candidates=$((candidates + 1))
  fi
done < <(find "${RELEASES_ROOT}" -mindepth 1 -maxdepth 1 -print0 2>/dev/null)

echo "KEEP_COUNT=${kept}"
echo "DELETE_CANDIDATE_COUNT=${candidates}"

if [[ "${APPLY}" == "1" ]]; then
  MODE=APPLY
  echo "RELEASE_RETENTION_MODE=${MODE}"
  if [[ -z "${DELETE_MANIFEST:-}" || ! -f "${DELETE_MANIFEST}" ]]; then
    echo "DELETE_MANIFEST_REQUIRED_FOR_APPLY"
    echo "RELEASE_RETENTION_GUARD=FAIL"
    exit 1
  fi
  # Exact paths only — no wildcards; refuse anything outside RELEASES_ROOT
  while IFS= read -r path || [[ -n "${path}" ]]; do
    [[ -z "${path}" || "${path}" =~ ^# ]] && continue
    case "${path}" in
      "${RELEASES_ROOT}"/*) ;;
      *)
        echo "PATH_OUTSIDE_RELEASES_ROOT=${path}"
        echo "RELEASE_RETENTION_GUARD=FAIL"
        exit 1
        ;;
    esac
    if keep_match "$(basename "${path}")"; then
      echo "REFUSING_KEEP_PATH=${path}"
      echo "RELEASE_RETENTION_GUARD=FAIL"
      exit 1
    fi
    if [[ -e "${path}" ]]; then
      rm -rf --one-file-system "${path}"
      echo "DELETED=${path}"
    else
      echo "ALREADY_GONE=${path}"
    fi
  done < "${DELETE_MANIFEST}"
fi

echo "RELEASE_RETENTION_GUARD=PASS"
echo "NOTE=Default is dry-run; APPLY=1 requires exact DELETE_MANIFEST with zero-ref proof performed by operator"
