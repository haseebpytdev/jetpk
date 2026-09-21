# Server cleanup candidates

Classifications: `KEEP` | `ROLLBACK_KEEP` | `ARCHIVE` | `DELETE` | `UNKNOWN`  
Rule: **never delete UNKNOWN**.

## KEEP (mandatory)

| Path / asset | Reason |
|---|---|
| `/home/pkjetp/jetpk_app` | Active production release |
| Runtime SHA `78dadc7b…` stamps | Application release authority |
| Public BUILD `wmNT0P2lJftqG2n9tM6gp` | Live Next public |
| Dashboard BUILD `3TyyvdcNScpOGj0oCkQuT` | Live Next dashboard |
| All `.env*` under `jetpk_app` | Secrets / runtime config |
| `jetpk_app/storage/` | User/runtime data |
| Production MySQL database | Live data |
| Current PM2 dump / processes | Process ownership |
| `/etc/logrotate.d/jetpakistan` | Log retention |
| Canonical deploy scripts under home + in-repo `scripts/jetpk/` | Deploy path |
| `/home/pkjetp/backups/jetpakistan-recovered-20260921/` | Restricted post-recovery backup |
| `/home/pkjetp/jetpk_git` | Server git checkout for protected deploys |
| `/home/pkjetp/jetpk_secrets` | Secrets root (**UNKNOWN content; KEEP**) |

## ROLLBACK_KEEP

| Path / asset | Reason |
|---|---|
| Rollback SHA stamp `cbd7686f…` | Documented rollback |
| `/home/pkjetp/jp-perf-cbd7686f`, `jp-softnav-cbd7686f`, `jp-func-cbd7686f` | Rollback harness evidence |
| Latest single `backups/jetpk_app-*.tar.gz` retained | Known-good prior full app archive |
| OLS assert logs / short-URL rollback notes | Config rollback evidence |
| One prior config ops tarball inside recovered backup | Config restore |

## ARCHIVE

| Path | Notes |
|---|---|
| Historical evidence already in Git `docs/closure/**` | Prefer Git over host binaries |
| Offline git bundle (server + offline copy) | DR artifact, not GitHub |

## DELETE (executed after zero-ref proof)

| Class | Result |
|---|---|
| Stale `/home/pkjetp/releases/*` not referencing active/rollback | Deleted (vast majority of 666 entries) |
| Duplicate old `backups/jetpk_app-*.tar.gz` (kept newest) | Deleted older copies |
| Duplicate old `backups/public_html-*.tar.gz` (kept newest) | Deleted older copies |
| Disposable home harness dirs (`jetpk-dash-03-*`, acceptance, profile-dropdown, `jp-*-5f78a5e1`, `ai-lab-staging`, …) | Deleted when unreferenced |

**Counts:** `FILES_REMOVED_COUNT=726`, `DELETE_FAILED_COUNT=6` (3 root-owned release dirs + 2 root-owned profile-dropdown backups + 1 root-owned `public_html.before-cutover` — retained as UNKNOWN / elevated cleanup), `DISK_BEFORE_AVAIL=33G` → `DISK_AFTER_AVAIL=42G`.

## UNKNOWN / blocked (not deleted)

| Path | Why |
|---|---|
| `/home/pkjetp/releases/jetpk-20260908T182533Z` | Root/foreign uid files; permission denied |
| `/home/pkjetp/releases/jetpk-20260918T075940Z` | Root-owned |
| `/home/pkjetp/releases/jetpk-ai-runtime-e393d336…` | Root-owned |
| `/home/pkjetp/public_html.before-cutover-…` | Root-owned acme-challenge |
| `/home/pkjetp/jetpk-profile-dropdown-backup-*` | Root-owned |
| Full OLS live `vhconf` tree | Not readable without elevated sudo — treat as KEEP until privileged backup |

These remain classified **UNKNOWN** or **KEEP-until-root-cleanup** and must not be force-deleted by the app user.
