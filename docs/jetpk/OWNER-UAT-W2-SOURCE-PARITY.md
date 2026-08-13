# OWNER UAT W2 — Source Parity

LAST_UPDATED_UTC: 2026-08-13T19:15:00Z  
BRANCH: `phase/jetpk-owner-uat-wave-2-admin-staff-business-closure`  
LOCAL_HEAD_AT_CHECK: `589e70897eb801ef69a38643ffbf48d20f818562`  
REMOTE: `jetpk` engineering SHA `589e7089` (docs commit follows)

## OLS

| Check | Result |
|---|---|
| Expected SHA256 | `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c` |
| Production | **MATCH** |

Dashboard BUILD_ID production: `t2IIp_9kfSUyeR9vl5_f-`  
Distinguish: REMOTE_HEAD and LATEST_ENGINEERING_SHA are `589e7089` until the docs pin commit. LATEST_DOCS_CONTENT_SHA is the following docs commit, not the engineering parent.

## OLS

| Check | Result |
|---|---|
| Expected SHA256 | `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c` |
| Production | **MATCH** |

## RBAC additive files (`6d019160`)

Local SHA256 equals production (lowercase):

- `app/Models/Role.php` `8c677595…d54759` MATCH
- `app/Models/RolePermission.php` `050a72b8…d6e78a` MATCH
- `app/Services/Rbac/RbacWriteService.php` `47311175…643c286` MATCH
- `app/Services/Rbac/RbacInstallService.php` `f206fb55…b0d32a` MATCH
- `app/Services/Rbac/RbacRolePresenter.php` `1d56dd40…72f735` MATCH
- `database/migrations/2026_08_13_220000_create_rbac_roles_tables.php` `16975b5b…c2634b` MATCH
- `dashboard/features/roles/rbac-management-panel.tsx` `0a1934ea…cfdc5` MATCH
- `dashboard/features/roles/rbac-write-api.ts` `83a52e02…cefddc` MATCH
- `dashboard/features/roles/roles-workspace.tsx` `2c94c931…7648f8` MATCH

Dashboard BUILD_ID production: `7XX2vpVISL5H9S6kjpnqj`  
PM2: `jetpk-dashboard` online; `jetpk-public-frontend` online (not restarted for RBAC).

## Laravel intended files (W2-23)

| File | Local SHA256 | Production | Result |
|---|---|---|---|
| `app/Http/Controllers/Frontend/GuestBookingLookupController.php` | `04117f9d2d80eb954fcc045492d01617f6f3bc96128cfab09e588407f61b490a` | same | **MATCH** |

Behavior proof (stronger than hash): private redirects return public `https://jetpakistan.pk/lookup-booking`.

## Frontend / Dashboard builds (production PM2)

| Surface | BUILD_ID | PM2 |
|---|---|---|
| Public Next | `3-0Jl1dsSg7bPAz3klPiz` | `jetpk-public-frontend` (restarted after groups hub) |
| Dashboard Next | `FqybHrg6rHaOCkMDMpxRp` | `jetpk-dashboard` |

W2-23 frontend presentation retirement verified in production browser (Manage Booking form present; Blade CTAs absent; `/laravel/lookup-booking` lands modern).

`/groups` → **307** `/groups/search` → **200** (BUILD_ID `3-0Jl1dsSg7bPAz3klPiz`).

## Prior batch parity (still valid)

Settings/Reports/CMS transformer files matched at W2-20 capture — see `OWNER-UAT-W2-20-REGRESSION-EVIDENCE.md`.

## Drift

No unexplained Laravel controller drift for W2-23. OLS unchanged. No OLS edits performed.
