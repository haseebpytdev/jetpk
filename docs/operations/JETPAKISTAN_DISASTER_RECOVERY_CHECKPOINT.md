# JetPakistan disaster recovery checkpoint

**Status:** recorded 2026-09-21 (post-recovery hardening)  
**Work branch for ops evidence:** `ops/post-recovery-cleanup-20260921`

## Canonical identities

```
APPLICATION_RELEASE_SHA=78dadc7b330ca24a6183241458dd8d1f6aa0d454
CHECKPOINT_BRANCH=checkpoint/jetpakistan-app-release-20260921
METADATA_BACKUP_BRANCH=backup/jetpakistan-post-recovery-20260921
RELEASE_TAG=jetpakistan-recovered-final-20260921
PUBLIC_BUILD_ID=wmNT0P2lJftqG2n9tM6gp
DASHBOARD_BUILD_ID=3TyyvdcNScpOGj0oCkQuT
ROLLBACK_SHA=cbd7686feadd35773fd0b597117538b8b99b59fa
METADATA_TIP_SHA=8c5d79da42126fdbd49681938583492dbc5422cf
```

## Active production paths

```
ACTIVE_LARAVEL_PATH=/home/pkjetp/jetpk_app
ACTIVE_PUBLIC_NEXT_PATH=/home/pkjetp/jetpk_app/frontend
ACTIVE_DASHBOARD_PATH=/home/pkjetp/jetpk_app/dashboard
CANONICAL_PUBLIC_HOST=https://jetpakistan.pk
```

## Protection posture

| Control | State |
|---|---|
| GitHub `main` classic branch protection | Active: no force-push, no deletion, PR required (1 review), linear history, strict status `release-guards`, enforce_admins |
| Checkpoint + backup ruleset | Active (`JetPakistan recovery checkpoints`) |
| Tag ruleset `jetpakistan-recovered-final-*` | Active (no update / no delete) |
| Offline git bundle SHA256 | `364d9b04d4f54e054536dd56124725c65238a69848a82f31f432e96edc33d5ca` |
| Restricted host backup | `/home/pkjetp/backups/jetpakistan-recovered-20260921/` |
| DB dump | Created under backup `db/` (private) |

## RPO / RTO intent

- **RPO:** last successful DB dump + Git tag/bundle + env/PM2 stamps in restricted backup.
- **RTO:** restore Git tag → verify BUILD/SHA gates → PM2/OLS health → non-mutating smoke on canonical host.
- Commercial mutations (book/ticket/pay/refund) are **out of scope** for DR drills.

## Related docs

- [`JETPAKISTAN_BACKUP_RESTORE.md`](./JETPAKISTAN_BACKUP_RESTORE.md)
- [`../closure/server-cleanup/PRE-CLEANUP-INVENTORY.md`](../closure/server-cleanup/PRE-CLEANUP-INVENTORY.md)
- [`../JETPAKISTAN_RELEASE_GUARD_POLICY.md`](../JETPAKISTAN_RELEASE_GUARD_POLICY.md)
