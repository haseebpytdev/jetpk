#!/usr/bin/env bash
# Permanent rule: never stop/restart Next until a valid replacement .next build exists.
# Usage:
#   APP_DIR=/home/pkjetp/jetpk_app/frontend \
#   EXPECTED_BUILD_ID=<id> \
#   bash scripts/jetpk/guard-build-before-restart.sh
#
# Optional: REQUIRE_SOURCE_SHA=<sha> SOURCE_SHA_FILE=<path>
set -euo pipefail

APP_DIR="${APP_DIR:?APP_DIR is required}"
BUILD_ID_FILE="${APP_DIR}/.next/BUILD_ID"

if [[ ! -f "${BUILD_ID_FILE}" ]]; then
  echo "NEXT_BUILD_MISSING=${BUILD_ID_FILE}"
  echo "BUILD_BEFORE_RESTART_GUARD=FAIL"
  exit 1
fi

build_id="$(tr -d '[:space:]' < "${BUILD_ID_FILE}")"
if [[ -z "${build_id}" ]]; then
  echo "NEXT_BUILD_ID_EMPTY"
  echo "BUILD_BEFORE_RESTART_GUARD=FAIL"
  exit 1
fi
echo "NEXT_BUILD_ID=${build_id}"

if [[ -n "${EXPECTED_BUILD_ID:-}" && "${build_id}" != "${EXPECTED_BUILD_ID}" ]]; then
  echo "EXPECTED_BUILD_ID=${EXPECTED_BUILD_ID}"
  echo "BUILD_ID_MISMATCH"
  echo "BUILD_BEFORE_RESTART_GUARD=FAIL"
  exit 1
fi

# Require a non-empty .next/server tree as a weak existence check
if [[ ! -d "${APP_DIR}/.next/server" ]]; then
  echo "NEXT_SERVER_DIR_MISSING"
  echo "BUILD_BEFORE_RESTART_GUARD=FAIL"
  exit 1
fi

if [[ -n "${REQUIRE_SOURCE_SHA:-}" ]]; then
  SOURCE_SHA_FILE="${SOURCE_SHA_FILE:-${APP_DIR}/.jetpk-next-build-source-sha}"
  if [[ ! -f "${SOURCE_SHA_FILE}" ]]; then
    echo "SOURCE_SHA_FILE_MISSING=${SOURCE_SHA_FILE}"
    echo "BUILD_SOURCE_SHA_MISMATCH=FAIL"
    echo "BUILD_BEFORE_RESTART_GUARD=FAIL"
    exit 1
  fi
  got="$(tr -d '[:space:]' < "${SOURCE_SHA_FILE}")"
  echo "BUILD_SOURCE_SHA=${got}"
  if [[ "${got}" != "${REQUIRE_SOURCE_SHA}" ]]; then
    echo "REQUIRE_SOURCE_SHA=${REQUIRE_SOURCE_SHA}"
    echo "BUILD_SOURCE_SHA_MISMATCH=FAIL"
    echo "BUILD_BEFORE_RESTART_GUARD=FAIL"
    exit 1
  fi
  echo "BUILD_SOURCE_SHA_MISMATCH=PASS"
fi

echo "BUILD_BEFORE_RESTART_GUARD=PASS"
