# OWNER UAT W2 — Source Parity

LAST_UPDATED_UTC: 2026-08-13T20:00:00Z  
BRANCH: `phase/jetpk-owner-uat-wave-2-admin-staff-business-closure`

## CURRENT STATE (authoritative)

REMOTE_BRANCH_HEAD: pending docs pin after this file (engineering already on remote as `694b5e1b21a86ffd4f861647090408c7288828a8`)  
LATEST_ENGINEERING_SHA: `694b5e1b21a86ffd4f861647090408c7288828a8`  
LATEST_DOCS_CONTENT_SHA: this docs commit (do not report REMOTE_HEAD as the engineering parent once the docs pin lands)

OWNER_UAT_WAVE_2=`REOPENED_PRE_OWNER_RETEST_V3_SOURCE_INTEGRITY`  
ADMIN_FULL_MANAGEMENT_SYSTEM=`NO` until production CMS QA draft proof + 5-actor cross-portal re-run after `694b5e1b`.

### Production Dashboard BUILD_ID

**Authoritative production Dashboard BUILD_ID:** `j_V7qVPpvh6PJvCoKBNLS`

Do not treat any other BUILD_ID in this file as current. Older IDs belong only in the history section below.

Laravel app: `/home/pkjetp/jetpk_app`  
Dashboard: `/home/pkjetp/jetpk_app/dashboard`  
PM2: `jetpk-dashboard` restarted after this BUILD_ID; `jetpk-public-frontend` not restarted.

### OLS (read-only)

| Check | Result |
|---|---|
| Expected SHA256 | `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c` |
| Production `/usr/local/lsws/conf/httpd_config.conf` | **MATCH** |
| OLS modified this pass | **NO** |

### Engineering close in `694b5e1b`

- `CmsPageContentSanitizer` for CmsPage persist, draft overlay, admin preview, public render, public content API (About Us sanitizer not used for builder pages).
- API Connections: full safe provider field metadata (options/help/channel/default except secrets), channel-aware UI, structured Advanced, AuditLog history.
- RBAC: selected-role state sync + `agency_id` min 1; dual-read authorization payload + role audit from AuditLog.
- Markup targeting matrix tests retained (no live production markup).

## History / evidence (not current BUILD_ID)

These IDs are historical only:

| When | Dashboard BUILD_ID | Engineering SHA | Note |
|---|---|---|---|
| Pre-V3 audit `589e7089` | `t2IIp_9kfSUyeR9vl5_f-` | `589e7089` | Directional UX; sanitizer still About Us |
| Earlier RBAC deploy | `7XX2vpVISL5H9S6kjpnqj` | `6d019160` era | Additive RBAC |
| Public Next (unchanged this pass) | `3-0Jl1dsSg7bPAz3klPiz` | — | `jetpk-public-frontend` |
| Older dashboard snapshot | `FqybHrg6rHaOCkMDMpxRp` | — | W2-23 era |

`/groups` → **307** `/groups/search` → **200** (public BUILD_ID `3-0Jl1dsSg7bPAz3klPiz`).

## RBAC additive files (`6d019160`)

Schema/guards remain in force. Dual-read unchanged. Do not treat MariaDB NULL unique as safe; uniqueness is `UNIQUE(scope_key, slug)`.

## Drift

OLS unchanged. No OLS edits performed. Tracked worktree clean after engineering commit `694b5e1b` except protected untracked `tmp/` QA files.
