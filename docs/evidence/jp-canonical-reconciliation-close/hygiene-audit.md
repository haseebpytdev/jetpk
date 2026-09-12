# Repository hygiene audit — JP-CANONICAL-RECONCILIATION-AND-REPO-HYGIENE-CLOSE

## Root-level candidates

| PATH | SIZE | TRACKED | REFERENCES | CLASSIFICATION | RATIONALE |
|---|---|---|---|---|---|
| `11k-s2-upload/` | ~152 KB | yes (4 files) | `JetpkMasterTraceAuditService` skip list only; `docs/evidence/jp-deep-closure-01/hygiene-audit.md` | **DELETE** | Legacy SFTP scratch from `436851d5`; no runtime/build/deploy callers |
| `_jetpk-package-temp/` | ~330 KB | yes (50 files) | audit skip list only | **DELETE** | Staged email package extract; superseded by in-repo `resources/views/emails` |
| `tmp/` (untracked scripts/json) | varies | no | deploy wrappers in `tmp/jetpk-*.sh` referenced by DEPLOYMENT-CONTEXT | **KEEP/IGNORE** | Active deploy wrappers; diagnostic JSON is local-only untracked |
| `playwright.config.ts` (root) | small | yes | `./test/e2e` legacy harness | **KEEP** | Distinct testDir from frontend/dashboard |
| `frontend/playwright.config.ts` | small | yes | public smoke specs + webServer | **KEEP** | Public Next smoke |
| `dashboard/playwright.config.ts` | small | yes | admin/staff smoke specs | **KEEP** | Dashboard smoke; different port/baseURL |

## Playwright config hygiene

Three configs are **intentionally separate** (root legacy e2e, public frontend port 3002, dashboard port 3003). No consolidation performed — tests are not semantically identical.

## Architectural debt (document only)

- `BookingService`, `FeaturedDealInventoryResolver`, CMS homepage panel — large but active; recommend future extraction at service boundaries, not under this hygiene closure.
- Historical phase branches retained on remote — archive policy out of scope.

## Cursor rules

Added `.cursor/rules/jetpk-production-safety.mdc` — concise pointer to `DEPLOYMENT-CONTEXT.md` / `AGENTS.md`; does not duplicate full policy.

## Actions taken

- Removed tracked `11k-s2-upload/` and `_jetpk-package-temp/` (proven unreferenced except audit skip fragments; skip list entries retained).

## Retained with reason

- All `docs/evidence/**` trees — audit history
- `tmp/jetpk-*.sh` deploy wrappers — production workflow
- Untracked `tmp/*.py`, `tmp/*.json` diagnostics — local-only; not committed
