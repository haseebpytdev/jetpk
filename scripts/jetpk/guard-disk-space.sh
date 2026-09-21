#!/usr/bin/env bash
# Pre-deploy disk space gate. Fail before build if free space is below reserve.
# Usage:
#   bash scripts/jetpk/guard-disk-space.sh
# Optional:
#   DISK_PATH=/home/pkjetp
#   MIN_FREE_GB=8
#   MIN_FREE_PCT=10
set -euo pipefail

DISK_PATH="${DISK_PATH:-/home/pkjetp}"
MIN_FREE_GB="${MIN_FREE_GB:-8}"
MIN_FREE_PCT="${MIN_FREE_PCT:-10}"

if [[ ! -d "${DISK_PATH}" ]]; then
  echo "DISK_PATH_MISSING=${DISK_PATH}"
  echo "DISK_SPACE_GUARD=FAIL"
  exit 1
fi

# Portable: prefer df -PB1, fallback to df -k
avail_b="$(df -PB1 "${DISK_PATH}" 2>/dev/null | awk 'NR==2{print $4}')"
total_b="$(df -PB1 "${DISK_PATH}" 2>/dev/null | awk 'NR==2{print $2}')"
if [[ -z "${avail_b}" || -z "${total_b}" ]]; then
  avail_k="$(df -Pk "${DISK_PATH}" | awk 'NR==2{print $4}')"
  total_k="$(df -Pk "${DISK_PATH}" | awk 'NR==2{print $2}')"
  avail_b=$((avail_k * 1024))
  total_b=$((total_k * 1024))
fi

min_b=$((MIN_FREE_GB * 1024 * 1024 * 1024))
pct=0
if [[ "${total_b}" -gt 0 ]]; then
  pct=$((avail_b * 100 / total_b))
fi

echo "DISK_PATH=${DISK_PATH}"
echo "DISK_TOTAL_BYTES=${total_b}"
echo "DISK_AVAIL_BYTES=${avail_b}"
echo "DISK_AVAIL_PCT=${pct}"
echo "DISK_MIN_FREE_GB=${MIN_FREE_GB}"
echo "DISK_MIN_FREE_PCT=${MIN_FREE_PCT}"

if [[ "${avail_b}" -lt "${min_b}" ]]; then
  echo "DISK_BELOW_MIN_FREE_GB"
  echo "DISK_SPACE_GUARD=FAIL"
  exit 1
fi
if [[ "${pct}" -lt "${MIN_FREE_PCT}" ]]; then
  echo "DISK_BELOW_MIN_FREE_PCT"
  echo "DISK_SPACE_GUARD=FAIL"
  exit 1
fi

echo "DISK_SPACE_GUARD=PASS"
