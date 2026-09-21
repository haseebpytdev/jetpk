#!/usr/bin/env bash
# Fail closed when the deploy worktree is dirty or has untracked production-risk paths.
# Usage: bash scripts/jetpk/guard-dirty-worktree.sh
# Optional: REPO_ROOT=... ALLOW_UNTRACKED=1
set -euo pipefail

REPO_ROOT="${REPO_ROOT:-$(git rev-parse --show-toplevel 2>/dev/null || true)}"
if [[ -z "${REPO_ROOT}" || ! -d "${REPO_ROOT}/.git" ]]; then
  echo "REPO_ROOT_INVALID"
  echo "WORKTREE_DIRTY=FAIL"
  exit 1
fi
cd "${REPO_ROOT}"

PORCELAIN="$(git status --porcelain 2>/dev/null || true)"
if [[ -n "${PORCELAIN}" ]]; then
  if [[ "${ALLOW_UNTRACKED:-0}" == "1" ]]; then
    # Still fail on tracked modifications; allow untracked only.
    TRACKED_DIRTY="$(echo "${PORCELAIN}" | grep -v '^??' || true)"
    if [[ -n "${TRACKED_DIRTY}" ]]; then
      echo "WORKTREE_TRACKED_CHANGES=YES"
      echo "WORKTREE_DIRTY=FAIL"
      exit 1
    fi
    echo "WORKTREE_UNTRACKED_ALLOWED=YES"
    echo "WORKTREE_DIRTY=PASS"
    exit 0
  fi
  echo "WORKTREE_HAS_CHANGES=YES"
  echo "WORKTREE_DIRTY=FAIL"
  exit 1
fi

echo "WORKTREE_HAS_CHANGES=NO"
echo "WORKTREE_DIRTY=PASS"
