# JP-OPS-READINESS-01 — Wave 1B Safe Remediation Plan

**Status:** PLAN ONLY — **not executed**  
**Approval required before any mutation**

```
WAVE_1B_MUTATIONS_PROPOSED=YES
WAVE_1B_APPROVAL_REQUIRED=YES
STOP_BEFORE_MUTATION=YES
```

## A. Disk cleanup sequence (proposed)

| ITEM | COMMAND (illustrative) | EXPECTED_RECLAIM | RISK | BACKUP_REQUIRED | ROLLBACK | VALIDATION |
|------|------------------------|------------------|------|-----------------|----------|------------|
| Inventory freeze | `df -h; du -sh backups` | 0 | none | n/a | n/a | record baseline |
| Off-host copy oldest `jetpk_app-202609*.tar.gz` | `scp/rsync` to archive host | 0 until delete | transfer fail | YES offhost | restore from offhost | checksum list |
| Delete archived Sep full tarballs | `rm` only listed archived paths | 18–22G | medium if wrong file | offhost must exist | restore tarball | `df -h` → ≤75% |
| Archive old phase dirs (seo/perf/frontend restore) | tar+offhost then rm | ~3.8G | low | YES | restore archive | path gone + df |
| Confirm unused git clones | human confirm then `rm -rf` listed clones | ~1.5G | medium if scripts use them | optional | re-clone | df |
| Optional npm/cache prune | `npm cache clean --force` (user home) | ~1G | low | no | regenerates | df |
| **Retain** | `jetpk_app-20261012T192408Z.tar.gz` + ≥1 Oct milestone | 0 | — | — | — | still present |
| **Never** | delete `jetpk_app`, `.env`, DB, `storage/app/public` | — | — | — | — | — |

**Do not** delete all backups. Prefer retention: newest + one pre-cert milestone + off-host archive.

## B. Failed jobs (proposed)

| GROUP | ROOT_CAUSE | FIX_REQUIRED_FIRST | RETRY_OR_ARCHIVE_OR_DELETE | BUSINESS_RISK | VALIDATION |
|-------|------------|--------------------|----------------------------|---------------|------------|
| FJ-G001 (79× SMTP 550) | permanent recipient reject | none for retry | **ARCHIVE/DELETE** — do not retry | low (historical missing ops email) | failed_jobs count ↓; no new mail storm |
| FJ-G002 (68× SMTP 450) | recipient reject (temp/stale) | validate any still-needed recipients | **ARCHIVE** default; selective re-queue only if business requests | medium if blind retry | no mass SMTP errors |

**Forbidden without per-group approval:**

- `php artisan queue:retry all`
- `php artisan queue:flush`

## C. Queue coverage (config — separate approval)

| Item | Proposal | Risk |
|------|----------|------|
| Non-notification queues | Add cron/supervisor worker for `default` (and any used queues) **or** prove unused | medium — only after inventory of `jobs` table + code queue names |
| Persistent worker vs stop-when-empty | optional later | higher change surface |

## D. Storage symlink (investigate before mutate)

`public/storage` is a **directory**, not symlink. Wave 1B should **diff** vs `storage/app/public` before any `storage:link` rewrite.

## E. Post-remediation validation

1. `df -h` target ≤75% used (stretch ≤65%)  
2. `failed_jobs` count documented after archive  
3. `/up` 200; Next listeners up  
4. No `queue:retry all` side effects  
5. Update OPS-DEF-0014/0015 statuses  
