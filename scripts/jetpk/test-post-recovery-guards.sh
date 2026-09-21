#!/usr/bin/env bash
# Local self-tests for post-recovery deploy guards (no production mutation).
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
FIX="$(mktemp -d /tmp/jetpk-post-recovery-guards.XXXXXX)"
trap 'rm -rf "${FIX}"' EXIT

# dirty worktree: clean fixture repo
mkdir -p "${FIX}/repo"
git -C "${FIX}/repo" init -q
git -C "${FIX}/repo" config user.email "guard@test.local"
git -C "${FIX}/repo" config user.name "guard"
echo ok > "${FIX}/repo/a.txt"
git -C "${FIX}/repo" add a.txt
git -C "${FIX}/repo" commit -qm "init"
REPO_ROOT="${FIX}/repo" bash "${SCRIPT_DIR}/guard-dirty-worktree.sh" | grep -q 'WORKTREE_DIRTY=PASS'
echo dirty > "${FIX}/repo/a.txt"
if REPO_ROOT="${FIX}/repo" bash "${SCRIPT_DIR}/guard-dirty-worktree.sh"; then
  echo "expected dirty fail"; exit 1
fi | true
REPO_ROOT="${FIX}/repo" bash "${SCRIPT_DIR}/guard-dirty-worktree.sh" >/tmp/dirty.out 2>&1 || true
grep -q 'WORKTREE_DIRTY=FAIL' /tmp/dirty.out

# authorized sha
SHA="$(git -C "${FIX}/repo" rev-parse HEAD)"
REPO_ROOT="${FIX}/repo" AUTHORIZED_SHA="${SHA}" bash "${SCRIPT_DIR}/guard-authorized-sha.sh" | grep -q 'SOURCE_SHA_NOT_AUTHORIZED=PASS'
if REPO_ROOT="${FIX}/repo" AUTHORIZED_SHA=deadbeef bash "${SCRIPT_DIR}/guard-authorized-sha.sh"; then
  echo "expected missing sha fail"; exit 1
fi >/tmp/auth.out 2>&1 || true
grep -q 'SOURCE_SHA_NOT_AUTHORIZED=FAIL' /tmp/auth.out || REPO_ROOT="${FIX}/repo" AUTHORIZED_SHA=deadbeef bash "${SCRIPT_DIR}/guard-authorized-sha.sh" >/tmp/auth.out 2>&1 || true
grep -q 'SOURCE_SHA_NOT_AUTHORIZED=FAIL' /tmp/auth.out

# disk space against FIX (should pass on normal systems)
DISK_PATH="${FIX}" MIN_FREE_GB=0 MIN_FREE_PCT=0 bash "${SCRIPT_DIR}/guard-disk-space.sh" | grep -q 'DISK_SPACE_GUARD=PASS'

# build before restart
mkdir -p "${FIX}/fe/.next/server"
echo "testid" > "${FIX}/fe/.next/BUILD_ID"
echo "${SHA}" > "${FIX}/fe/.jetpk-next-build-source-sha"
APP_DIR="${FIX}/fe" EXPECTED_BUILD_ID=testid REQUIRE_SOURCE_SHA="${SHA}" \
  bash "${SCRIPT_DIR}/guard-build-before-restart.sh" | grep -q 'BUILD_BEFORE_RESTART_GUARD=PASS'
rm -f "${FIX}/fe/.next/BUILD_ID"
if APP_DIR="${FIX}/fe" bash "${SCRIPT_DIR}/guard-build-before-restart.sh"; then
  echo "expected missing build fail"; exit 1
fi >/tmp/bbr.out 2>&1 || true
APP_DIR="${FIX}/fe" bash "${SCRIPT_DIR}/guard-build-before-restart.sh" >/tmp/bbr.out 2>&1 || true
grep -q 'BUILD_BEFORE_RESTART_GUARD=FAIL' /tmp/bbr.out

# retention dry-run
mkdir -p "${FIX}/releases/keep-${SHA:0:8}" "${FIX}/releases/old-junk-20260101"
ACTIVE_SHA="${SHA}" ROLLBACK_SHA="${SHA}" RELEASES_ROOT="${FIX}/releases" \
  bash "${SCRIPT_DIR}/release-retention-dry-run.sh" | tee /tmp/ret.out | grep -q 'RELEASE_RETENTION_GUARD=PASS'
grep -q 'DELETE_CANDIDATE=' /tmp/ret.out

echo "POST_RECOVERY_GUARD_SELFTEST=PASS"
