#!/usr/bin/env bash
# Assert (and optionally normalize) JetPakistan Laravel runtime ownership.
# Idempotent. No chmod 777. Scoped to writable runtime trees only.
#
# Usage:
#   APP_ROOT=/home/pkjetp/jetpk_app bash scripts/jetpk/assert-runtime-ownership.sh
#
# Optional env:
#   RUNTIME_USER=pkjetp
#   RUNTIME_GROUP=pkjetp
#   MODE=assert|normalize          (default: assert)
#   RUNTIME_WRITE_TEST=1           (pkjetp write probe; safe temp file)
#   TARGET_ROOTS=...               (space-separated relative paths under APP_ROOT)
set -euo pipefail

APP_ROOT="${APP_ROOT:?APP_ROOT is required}"
RUNTIME_USER="${RUNTIME_USER:-pkjetp}"
RUNTIME_GROUP="${RUNTIME_GROUP:-pkjetp}"
MODE="${MODE:-assert}"
RUNTIME_WRITE_TEST="${RUNTIME_WRITE_TEST:-0}"

if [[ ! -d "${APP_ROOT}" ]]; then
  echo "APP_ROOT_MISSING=${APP_ROOT}"
  exit 1
fi

case "${MODE}" in
  assert|normalize) ;;
  *)
    echo "MODE_INVALID=${MODE}"
    exit 1
    ;;
esac

DEFAULT_TARGET_ROOTS=(
  storage
  storage/framework
  storage/framework/cache
  storage/framework/cache/data
  storage/framework/views
  storage/framework/sessions
  storage/logs
  bootstrap/cache
)

if [[ -n "${TARGET_ROOTS:-}" ]]; then
  # shellcheck disable=SC2206
  TARGETS=(${TARGET_ROOTS})
else
  TARGETS=("${DEFAULT_TARGET_ROOTS[@]}")
fi

declare -A SEEN=()
UNIQUE_TARGETS=()
for rel in "${TARGETS[@]}"; do
  [[ -z "${rel}" ]] && continue
  case "${rel}" in
    /*|~*|../*|*/../*|*/..|..)
      echo "TARGET_ESCAPE_FORBIDDEN=${rel}"
      exit 1
      ;;
  esac
  if [[ -z "${SEEN[${rel}]:-}" ]]; then
    SEEN["${rel}"]=1
    UNIQUE_TARGETS+=("${rel}")
  fi
done

TOTAL_FILES=0
ROOT_OWNED_RUNTIME_FILES=0
NON_PKJETP_RUNTIME_FILES=0
NON_WRITABLE_DIRS=0
BAD_SAMPLES=()

scan_ownership() {
  TOTAL_FILES=0
  ROOT_OWNED_RUNTIME_FILES=0
  NON_PKJETP_RUNTIME_FILES=0
  NON_WRITABLE_DIRS=0
  BAD_SAMPLES=()

  for rel in "${UNIQUE_TARGETS[@]}"; do
    target="${APP_ROOT}/${rel}"
    if [[ ! -e "${target}" ]]; then
      continue
    fi

    while IFS= read -r path; do
      TOTAL_FILES=$((TOTAL_FILES + 1))
      owner="$(stat -c '%U' "${path}" 2>/dev/null || echo unknown)"
      group="$(stat -c '%G' "${path}" 2>/dev/null || echo unknown)"
      if [[ "${owner}" == "root" ]]; then
        ROOT_OWNED_RUNTIME_FILES=$((ROOT_OWNED_RUNTIME_FILES + 1))
      fi
      if [[ "${owner}" != "${RUNTIME_USER}" || "${group}" != "${RUNTIME_GROUP}" ]]; then
        NON_PKJETP_RUNTIME_FILES=$((NON_PKJETP_RUNTIME_FILES + 1))
        if [[ "${#BAD_SAMPLES[@]}" -lt 20 ]]; then
          BAD_SAMPLES+=("${path} owner=${owner}:${group}")
        fi
      fi
      if [[ -d "${path}" && ! -w "${path}" ]]; then
        NON_WRITABLE_DIRS=$((NON_WRITABLE_DIRS + 1))
      fi
    done < <(find "${target}" -xdev 2>/dev/null || true)
  done
}

normalize_ownership() {
  for rel in "${UNIQUE_TARGETS[@]}"; do
    target="${APP_ROOT}/${rel}"
    if [[ -e "${target}" ]]; then
      chown -R "${RUNTIME_USER}:${RUNTIME_GROUP}" "${target}"
      echo "NORMALIZED=${rel}"
    fi
  done
}

runtime_write_probe() {
  RUNTIME_WRITABLE_AS_PKJETP="FAIL"
  PKJETP_RUNTIME_WRITE="FAIL"
  TEMP_FIXTURE_CLEANUP="FAIL"

  probe_dir="${APP_ROOT}/storage/framework/cache/data"
  mkdir -p "${probe_dir}"
  probe_file="${probe_dir}/.jetpk-runtime-write-probe-$$-$(date +%s)"
  probe_payload="jetpk-runtime-write-probe"

  if command -v runuser >/dev/null 2>&1; then
    runuser -u "${RUNTIME_USER}" -- bash -c "
      set -euo pipefail
      printf '%s' '${probe_payload}' > '${probe_file}'
      test \"\$(cat '${probe_file}')\" = '${probe_payload}'
      rm -f '${probe_file}'
    "
  elif command -v sudo >/dev/null 2>&1; then
    sudo -u "${RUNTIME_USER}" bash -c "
      set -euo pipefail
      printf '%s' '${probe_payload}' > '${probe_file}'
      test \"\$(cat '${probe_file}')\" = '${probe_payload}'
      rm -f '${probe_file}'
    "
  else
    echo "RUNTIME_WRITE_PROBE_UNSUPPORTED=1"
    return 1
  fi

  RUNTIME_WRITABLE_AS_PKJETP="PASS"
  PKJETP_RUNTIME_WRITE="PASS"
  TEMP_FIXTURE_CLEANUP="PASS"
  return 0
}

if [[ "${MODE}" == "normalize" ]]; then
  normalize_ownership
fi

scan_ownership

if [[ "${RUNTIME_WRITE_TEST}" == "1" ]]; then
  if runtime_write_probe; then
    :
  else
    RUNTIME_WRITABLE_AS_PKJETP="FAIL"
    PKJETP_RUNTIME_WRITE="FAIL"
    TEMP_FIXTURE_CLEANUP="FAIL"
  fi
else
  if [[ "${ROOT_OWNED_RUNTIME_FILES}" -eq 0 && "${NON_PKJETP_RUNTIME_FILES}" -eq 0 && "${NON_WRITABLE_DIRS}" -eq 0 ]]; then
    RUNTIME_WRITABLE_AS_PKJETP="PASS"
  else
    RUNTIME_WRITABLE_AS_PKJETP="FAIL"
  fi
fi

if [[ "${ROOT_OWNED_RUNTIME_FILES}" -eq 0 && "${NON_PKJETP_RUNTIME_FILES}" -eq 0 && "${RUNTIME_WRITABLE_AS_PKJETP}" == "PASS" ]]; then
  RUNTIME_OWNERSHIP_GATE="PASS"
else
  RUNTIME_OWNERSHIP_GATE="FAIL"
fi

echo "RUNTIME_OWNER_EXPECTED=${RUNTIME_USER}:${RUNTIME_GROUP}"
echo "APP_ROOT=${APP_ROOT}"
echo "MODE=${MODE}"
echo "TOTAL_FILES=${TOTAL_FILES}"
echo "ROOT_OWNED_RUNTIME_FILES=${ROOT_OWNED_RUNTIME_FILES}"
echo "NON_PKJETP_RUNTIME_FILES=${NON_PKJETP_RUNTIME_FILES}"
echo "NON_WRITABLE_DIRS=${NON_WRITABLE_DIRS}"
echo "RUNTIME_WRITABLE_AS_PKJETP=${RUNTIME_WRITABLE_AS_PKJETP}"
echo "RUNTIME_OWNERSHIP_GATE=${RUNTIME_OWNERSHIP_GATE}"

if [[ "${RUNTIME_WRITE_TEST}" == "1" ]]; then
  echo "PKJETP_RUNTIME_WRITE=${PKJETP_RUNTIME_WRITE}"
  echo "TEMP_FIXTURE_CLEANUP=${TEMP_FIXTURE_CLEANUP}"
fi

if [[ "${#BAD_SAMPLES[@]}" -gt 0 ]]; then
  printf '%s\n' "${BAD_SAMPLES[@]}"
fi

if [[ "${RUNTIME_OWNERSHIP_GATE}" != "PASS" ]]; then
  exit 1
fi

echo "RUNTIME_OWNERSHIP_ASSERT_COMPLETE"
