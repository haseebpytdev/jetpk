#!/usr/bin/env bash
# Apply an exact allowlisted JetPakistan runtime deletion manifest.
# Safe deletes only: no wildcards, no recursive directory wipes, no path escape.
#
# Usage:
#   APP_ROOT=/home/pkjetp/jetpk_app \
#   DELETE_MANIFEST=/path/to/DELETE_RUNTIME_FILES \
#   bash scripts/jetpk/apply-delete-manifest.sh
set -euo pipefail

APP_ROOT="${APP_ROOT:?APP_ROOT is required}"
DELETE_MANIFEST="${DELETE_MANIFEST:?DELETE_MANIFEST is required}"

if [[ ! -d "${APP_ROOT}" ]]; then
  echo "APP_ROOT_MISSING=${APP_ROOT}"
  exit 1
fi
if [[ ! -f "${DELETE_MANIFEST}" ]]; then
  echo "DELETE_MANIFEST_MISSING=${DELETE_MANIFEST}"
  exit 1
fi

DELETED=0
SKIPPED_MISSING=0

while IFS= read -r rel || [[ -n "${rel}" ]]; do
  [[ -z "${rel}" ]] && continue
  case "${rel}" in
    *'*'*|*'?'*|*'['*|*$'\n'*)
      echo "WILDCARD_FORBIDDEN=${rel}"
      exit 1
      ;;
    /*|~*|../*|*/../*|*/..|..)
      echo "PATH_ESCAPE_FORBIDDEN=${rel}"
      exit 1
      ;;
    frontend/*)
      ;;
    *)
      echo "SCOPE_FORBIDDEN=${rel}"
      exit 1
      ;;
  esac

  target="${APP_ROOT}/${rel}"
  case "${target}" in
    "${APP_ROOT}/frontend/"*)
      ;;
    *)
      echo "RESOLVED_PATH_OUT_OF_SCOPE=${target}"
      exit 1
      ;;
  esac

  if [[ -d "${target}" ]]; then
    echo "DIRECTORY_DELETE_FORBIDDEN=${rel}"
    exit 1
  fi

  if [[ -e "${target}" || -L "${target}" ]]; then
    rm -f -- "${target}"
    echo "DELETED=${rel}"
    DELETED=$((DELETED + 1))
  else
    echo "ALREADY_ABSENT=${rel}"
    SKIPPED_MISSING=$((SKIPPED_MISSING + 1))
  fi
done < "${DELETE_MANIFEST}"

echo "DELETE_APPLIED=${DELETED}"
echo "DELETE_ALREADY_ABSENT=${SKIPPED_MISSING}"
echo "DELETE_MANIFEST_COMPLETE"
