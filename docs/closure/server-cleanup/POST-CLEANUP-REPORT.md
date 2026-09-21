# Post-cleanup report

**Date:** 2026-09-21  
**Branch:** `ops/post-recovery-cleanup-20260921`

## Disk

| Metric | Value |
|---|---|
| DISK_BEFORE | 33G avail (66% used) / `34996514816` bytes avail |
| DISK_AFTER | 42G avail (58% used) / `44160446464` bytes avail |
| SPACE_RECLAIMED | ~9G (`9163931648` bytes avail delta) |
| FILES_REMOVED_COUNT | 726 |
| DELETE_FAILED_COUNT | 6 (3 root-owned releases + 2 profile-dropdown backups + 1 public_html.before-cutover; **UNKNOWN / not force-deleted**) |
| RELEASES_REMOVED | ~663 of 666 staging entries (3 root-owned UNKNOWN remain) |
| OLD_BUILDS_REMOVED | stale release `.next` trees under deleted releases |
| OLD_LOGS_REMOVED | 0 active logs deleted; logrotate verified present |
| GIT_BUNDLE_ON_SERVER | `/home/pkjetp/backups/jetpakistan-recovered-20260921/JetPakistan-Recovered-20260921.bundle` (305M) SHA256 `364d9b04…` matches local |

## Server backups

```
SERVER_BACKUP_CREATED=YES
DATABASE_BACKUP_CREATED=YES
DB_BACKUP_PATH=/home/pkjetp/backups/jetpakistan-recovered-20260921/db/jetpk-db-20260921T095424Z.sql.gz
DB_BACKUP_SIZE=5242760
DB_BACKUP_TIMESTAMP=20260921T095424Z
DB_RESTORE_COMMAND_TESTED/DOCUMENTED=YES (gzip -t + restore doc; no live overwrite)
GIT_BUNDLE_CREATED=YES
GIT_BUNDLE_VERIFIED=YES
GIT_BUNDLE_SHA256=364d9b04d4f54e054536dd56124725c65238a69848a82f31f432e96edc33d5ca
```

## Active / rollback

```
ACTIVE_RELEASE=78dadc7b330ca24a6183241458dd8d1f6aa0d454
ROLLBACK_RELEASE=cbd7686feadd35773fd0b597117538b8b99b59fa
PUBLIC_BUILD=wmNT0P2lJftqG2n9tM6gp
DASHBOARD_BUILD=3TyyvdcNScpOGj0oCkQuT
SERVER_CLEANUP_FUNCTIONAL=PASS
```

## Git hygiene

```
REMOTE_BRANCHES_BEFORE=117
REMOTE_BRANCHES_AFTER=49
BRANCHES_DELETED=68
BRANCHES_ARCHIVED=3 (archive/* retained)
BRANCHES_RETAINED=protected set + unmerged review set + PR#3 head
UNMERGED_BRANCHES_REQUIRING_REVIEW=see BRANCH-INVENTORY.md (~41 before delete pass; remaining unmerged still listed)
MAIN_PROTECTION=PASS
CHECKPOINT_PROTECTION=PASS
TAG_PROTECTION=PASS
OPEN_PRS_REMAINING=1 (#3 KEEP_OPEN)
```

## Permanent safety

```
APPLICATION_CHECKPOINT=checkpoint/jetpakistan-app-release-20260921
METADATA_BACKUP=backup/jetpakistan-post-recovery-20260921
IMMUTABLE_TAG=jetpakistan-recovered-final-20260921
BRANCH_PROTECTION=PASS
TAG_PROTECTION=PASS
DEPLOY_GUARD=scripts/jetpk/run-post-recovery-deploy-guards.sh
DIRTY_WORKTREE_GUARD=PASS (script + selftest)
BUILD_BEFORE_RESTART_GUARD=PASS (script + selftest)
DISK_SPACE_GUARD=PASS (script + selftest)
RELEASE_RETENTION_GUARD=PASS (dry-run default)
BACKUP_RESTORE_DOC=docs/operations/JETPAKISTAN_BACKUP_RESTORE.md
DISASTER_RECOVERY_DOC=docs/operations/JETPAKISTAN_DISASTER_RECOVERY_CHECKPOINT.md
DISASTER_RECOVERY_DRY_RUN=PASS
```

## Product code

No flight/booking/homepage/Groups/auth/Ask/CMS/SEO/short-URL/performance product code was modified in this phase.
