# OWNER UAT W2 — Source Parity

LAST_UPDATED_UTC: 2026-08-12T21:25:00Z  
BRANCH: `phase/jetpk-owner-uat-wave-2-admin-staff-business-closure`  
LOCAL_HEAD_AT_CHECK: `7dc9d2ae0597820d669e2b6fa18d38a50633dd9d`  
REMOTE: `jetpk` same SHA before this docs/groups commit

## OLS

| Check | Result |
|---|---|
| Expected SHA256 | `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c` |
| Production | **MATCH** |

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
