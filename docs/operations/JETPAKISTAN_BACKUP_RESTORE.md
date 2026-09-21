# JetPakistan backup & restore

**No secrets in this document.** Paths are operational; credentials stay on the private host only.

## Identities

| Identity | Value |
|---|---|
| Application release SHA | `78dadc7b330ca24a6183241458dd8d1f6aa0d454` |
| Checkpoint branch | `checkpoint/jetpakistan-app-release-20260921` |
| Metadata backup branch | `backup/jetpakistan-post-recovery-20260921` |
| Immutable tag | `jetpakistan-recovered-final-20260921` |
| Public BUILD_ID | `wmNT0P2lJftqG2n9tM6gp` |
| Dashboard BUILD_ID | `3TyyvdcNScpOGj0oCkQuT` |
| Rollback SHA | `cbd7686feadd35773fd0b597117538b8b99b59fa` |

## Artifacts (private)

| Artifact | Location class |
|---|---|
| Restricted server backup | `/home/pkjetp/backups/jetpakistan-recovered-20260921/` (mode `700`, `NO_PUBLIC_WEB_ACCESS`) |
| Database dump | `$BACKUP/db/jetpk-db-<UTC>.sql.gz` (private; never commit) |
| Git bundle | Offline: `JetPakistan-Recovered-20260921.bundle` + same path under restricted server backup |
| Bundle SHA256 | `364d9b04d4f54e054536dd56124725c65238a69848a82f31f432e96edc33d5ca` |
| PM2 dump | `$BACKUP/pm2/dump.pm2` |
| Lockfiles / stamps | `$BACKUP/locks`, `$BACKUP/stamps` |
| Env copies | `$BACKUP/env/*` (host-private only) |

## Restore procedures (high level)

### A. Git application checkpoint

```bash
git fetch --all --tags
git checkout jetpakistan-recovered-final-20260921
# or: git checkout checkpoint/jetpakistan-app-release-20260921
git rev-parse HEAD   # expect 78dadc7b…
```

### B. From offline bundle

```bash
git bundle verify JetPakistan-Recovered-20260921.bundle
git clone JetPakistan-Recovered-20260921.bundle jetpk-restore
cd jetpk-restore
git checkout jetpakistan-recovered-final-20260921
```

### C. Database

```bash
# On private host only — use credentials from private env backup, never paste into tickets
gunzip -c /home/pkjetp/backups/jetpakistan-recovered-20260921/db/jetpk-db-<TS>.sql.gz \
  | mysql -h127.0.0.1 -u<user> -p <database>
```

Documented restore shape tested via `gzip -t` integrity on dump creation. Full overwrite restore is **not** executed as a live drill.

### D. OLS

1. Prefer privileged restore of vhost conf from a root-readable backup when available.
2. Re-run `scripts/jp-ols-assert-flights-short-url.sh` / host assert until `ROUTE_OWNERSHIP_GUARD=PASS`.
3. Do not leave short-URL ownership on Laravel catch-all.

### E. PM2

```bash
export PATH="$HOME/.npm-global/bin:$PATH"
pm2 resurrect   # or restore from $BACKUP/pm2/dump.pm2
# Ensure exec cwd:
#   jetpk-public-frontend -> /home/pkjetp/jetpk_app/frontend
#   jetpk-dashboard       -> /home/pkjetp/jetpk_app/dashboard
```

**Never** stop/restart Next until `scripts/jetpk/guard-build-before-restart.sh` passes for a valid `.next/BUILD_ID`.

### F. Env / storage

- Copy private env files from `$BACKUP/env/` only over SSH to the corresponding app paths.
- Preserve `storage/` and uploads; restore from app tarball only if storage was lost.

### G. Application rollback (runtime)

Authorized rollback target: `cbd7686feadd35773fd0b597117538b8b99b59fa`  
Use protected stage/deploy wrappers with `AUTHORIZED_SHA=<rollback>` and build-before-restart gates.  
Forward restoration returns to `78dadc7b…` + frozen BUILD_IDs (or a newly authorized main release SHA).

## Deploy guards (in-repo)

- `scripts/jetpk/guard-dirty-worktree.sh` → `WORKTREE_DIRTY`
- `scripts/jetpk/guard-authorized-sha.sh` → `SOURCE_SHA_NOT_AUTHORIZED` / `BUILD_SOURCE_SHA_MISMATCH`
- `scripts/jetpk/guard-build-before-restart.sh` → `BUILD_BEFORE_RESTART_GUARD`
- `scripts/jetpk/guard-disk-space.sh` → `DISK_SPACE_GUARD`
- `scripts/jetpk/release-retention-dry-run.sh` → default **DRY-RUN** retention
