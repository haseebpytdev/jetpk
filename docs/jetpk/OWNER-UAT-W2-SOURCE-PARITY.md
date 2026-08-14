# OWNER UAT W2 — Source Parity

LAST_UPDATED_UTC: 2026-08-14T09:00:00Z
BRANCH: `phase/jetpk-owner-uat-wave-2-admin-staff-business-closure`

## CURRENT STATE (authoritative)

LATEST_ENGINEERING_SHA: `3032c66911aad3fdad0c7cd2912db720430084fe`
PRODUCTION_PHP_SHA: `3032c66911aad3fdad0c7cd2912db720430084fe`

OWNER_UAT_WAVE_2=`PASS_READY_FOR_OWNER_RETEST_V3`
ADMIN_FULL_MANAGEMENT_SYSTEM=`YES`
OWNER_RETEST_V3=`NOT_STARTED`
PRODUCTION_PHP_SOURCE_PARITY=`PASS`
FINAL_SOURCE_PARITY=`PASS`
FINAL_BUILD_RUNTIME=`PASS`

Branch HEAD is resolved externally via Git.

### Production Dashboard BUILD_ID

**Authoritative production Dashboard BUILD_ID:** `gVySYezQbX8a2wfDmjyBM`

Do not treat any other BUILD_ID in this file as current. Older IDs belong only in the history section below.

Laravel app: `/home/pkjetp/jetpk_app`
Dashboard: `/home/pkjetp/jetpk_app/dashboard`
PM2: `jetpk-dashboard` restarted after this BUILD_ID; `jetpk-public-frontend` not restarted for this engineering close.

### OLS (read-only)

| Check | Result |
|---|---|
| Expected SHA256 | `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c` |
| Production `/usr/local/lsws/conf/httpd_config.conf` | **MATCH** |
| OLS modified this pass | **NO** |

## History / evidence (not current BUILD_ID)

These IDs are historical only:

| When | Dashboard BUILD_ID | Engineering SHA | Note |
|---|---|---|---|
| Pre-V3 source reopen | `j_V7qVPpvh6PJvCoKBNLS` | `694b5e1b` | CMS sanitizer / provider metadata; CMS QA + 5-actor still open then |
| Pre-V3 audit `589e7089` | `t2IIp_9kfSUyeR9vl5_f-` | `589e7089` | Directional UX; sanitizer still About Us |
| Earlier RBAC deploy | `7XX2vpVISL5H9S6kjpnqj` | `6d019160` era | Additive RBAC |
| Public Next (unchanged this pass) | `3-0Jl1dsSg7bPAz3klPiz` | — | `jetpk-public-frontend` |
| Older dashboard snapshot | `FqybHrg6rHaOCkMDMpxRp` | — | W2-23 era |

`/groups` → **307** `/groups/search` → **200** (public BUILD_ID `3-0Jl1dsSg7bPAz3klPiz`).

### Engineering close in `694b5e1b` (historical)

- `CmsPageContentSanitizer` for CmsPage persist, draft overlay, admin preview, public render, public content API (About Us sanitizer not used for builder pages).
- API Connections: full safe provider field metadata (options/help/channel/default except secrets), channel-aware UI, structured Advanced, AuditLog history.
- RBAC: selected-role state sync + `agency_id` min 1; dual-read authorization payload + role audit from AuditLog.
- Markup targeting matrix tests retained (no live production markup).

Later engineering: `a221dc3e` booking CTA / CMS media JSON; `3032c669` RBAC name init from selectedRole.

## RBAC additive files (`6d019160`)

Schema/guards remain in force. Dual-read unchanged. Do not treat MariaDB NULL unique as safe; uniqueness is `UNIQUE(scope_key, slug)`.

## Drift

OLS unchanged. No OLS edits performed. Tracked worktree clean after engineering commit `3032c669` except protected untracked `tmp/` QA files.
