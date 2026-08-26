# JP-API-MODULES-CMS-FINAL-CLOSEOUT

## Status

`FINAL_STATUS=PASS`  
`USER_TESTING_READY=YES`  
`ROLLBACK_USED=NO`  
`REDEPLOY_AFTER_RESUME=NO`

## Git

| Field | Value |
| --- | --- |
| Branch | `phase/jp-bo-04g-progressive` |
| START_SHA | `9d5e1d3fac435ee5c1a0d670be2c33692f2e18f5` |
| FINAL_CLOSEOUT_ENGINEERING_SHA | `7fbb1301e5e96eadb0acdc65e0b5fba149eb0a35` |
| Docs/heartbeat progression | `291c4239` → `5c8071b4` → `beda66ff` (+ evidence/docs commits below) |

## Protected deploy (already complete — not redone)

| Field | Value |
| --- | --- |
| DEPLOYED_RUNTIME_SHA | `7fbb1301e5e96eadb0acdc65e0b5fba149eb0a35` |
| BACKUP_ID | `jp-api-cms-final-closeout-20260826T191005Z` |
| RELEASE_TIMESTAMP | `20260826T190911Z` |
| Manifest | 12 runtime files (`tmp/jp-api-cms-final-closeout/runtime-manifest.txt`) |
| NEW_DASHBOARD_BUILD_ID | `vitIGm_eK6CvruW--cbnJ` |
| PUBLIC_BUILD_ID_UNCHANGED | `N2UgmUu_xxKIyYUu2pLRo` |
| LIVE_SOURCE_DRIFT | `0` |
| OLS_HASH | PASS |
| DASHBOARD | online |
| MIGRATIONS | 0 |

## Stall diagnosis

Protected orchestrator finished (`ORCHESTRATE_COMPLETE`). Agent await interrupted after deploy. Resume continued **post-deploy verification only**.

## Official Al-Haider contract

Docs: https://documenter.getpostman.com/view/7529188/2sAYdioVWw

| Item | Official | JetPK after closeout |
| --- | --- | --- |
| Login | `POST /api/login` formdata email/password → `token` | Managed renewal only; **generation disabled this run** |
| Groups | `GET /api/available/groups` | same |
| Airlines | `GET /api/available/airlines` | same |
| Detail | `GET /api/group/detail/{id}` | same |
| Seats | `GET /api/available/seats/{id}` | same |
| Reserve | `POST /api/create/booking` | corrected (was mismatch) |
| Cancel | `PATCH /api/cancel/booking/{id}` | corrected (was mismatch) |

`ALHAIDER_ENDPOINT_MISMATCH_COUNT=2` (fixed in engineering)  
`ALHAIDER_AUTH_FIELD_MISMATCH_COUNT=0` (docs use email/password)

## Managed-token architecture

- Auth mode: `managed_token` on `SupplierConnection` (id=6, JPak Group)
- Authority: DB existing token; ENV username/password present as kill-switch fallback only
- Auto-renew: `0` (disabled)
- Annual issuance guard: max 1 automatic generation / 365d (DB-persistent); concurrent lock; ambiguous fail-closed
- Test Connection: read-only groups probe; **never** `/api/login`
- Live: token preserved; generation calls = 0; test HTTP 200; groups 28; airlines 11; booking gate false

## SMTP safety

- Active DB SMTP connection present but **not usable** → `applyRuntimeConfig()` returns `env_fallback`
- UI shows **Mail: env fallback**
- `SMTP_RUNTIME_UNBROKEN=PASS`

## CMS production truth

Connected fields (honest matrix = presenter-backed):

- Text: **28** supported — save/render/restore all PASS; `CMS_QA_TEXT_RESIDUE=0`
- Media: **3** keys (`hero_background`, `hero_background_mobile`, `support_cta_background`) — assign/render/restore PASS
- Mid-run media restore bug (`mime` non-column) repaired; hero restored to 1,948,204 bytes before successful re-run

## Sidebar

- Suppliers group contains single item **API & Modules**
- `SIDEBAR_SUPPLIERS_DUPLICATE=0`
- Flat nav mirrors the same single entry (dual representation, not duplicate sidebar entries)

## Evidence

`docs/evidence/jp-api-cms-final-closeout/20260826T202942Z/` (12 PNGs + README)

## Rollback

Restore from backup `jp-api-cms-final-closeout-20260826T191005Z` using the protected rollback package under `/home/pkjetp/releases/jetpk-rollback-*-jp-api-cms-final-closeout` (not used).

## Hard stops observed

- No new Al-Haider token generation
- No group reservation/booking/payment/ticket
- No production redeploy after engineering SHA `7fbb1301`

## NEXT

Kick off **Group Ticketing** user/UAT on the live Al-Haider read-only inventory (28 groups) with booking gate still controlled by owner enablement.
