# JP-BO-04 — Master OTA Parity (Stage A predeploy)

## Phase identity
- Phase: **JP-BO-04**
- Branch: `phase/jp-bo-04`
- Production base SHA: `cbe445e35da33468834dcbf95aaf19b2eb3123ff`
- Starting docs SHA: `c7c2b3e2152ac94b3e7bbe11f344a611a09606ca`
- FINAL_ENGINEERING_SHA: `f70e56b30705e32613dbe6316c2a5fb97d6f17bd`
- Master OTA reference: `C:/Users/khadi/ota` (**READ-ONLY**)
- `MAIN_OTA_READ_ONLY=YES`
- `OWNER_RETEST_V3_STATE=BLOCKED_PENDING_JP_BO04_LIVE_PROOF`
- Deploy: **NOT EXECUTED**
- Stage B live proof: **REQUIRED** (not run)

## Objective
Evidence-backed reconciliation of master OTA admin workflows against JetPakistan Laravel + Next Dashboard. Stage A ends at engineering readiness with reviewable SHAs.

## Matrix counts (Stage A close)
| Metric | Value |
| --- | --- |
| MASTER_OPERATION_COUNT | 179 |
| PARITY_OPERATION_COUNT | 124 (direct/operational parity surfaces) |
| MISSING_UI_COUNT | 0 |
| MISSING_BACKEND_COUNT | 0 |
| UNEXPLAINED_PARITY_GAPS | 0 |
| UNEXPLAINED_FINANCE_GAPS | 0 |
| INTENTIONALLY_EXCLUDED_COUNT | (group ticketing + approved finance exclusions) |
| DEAD_PRIMARY_ACTIONS | 0 |
| NOOP_ACTIONS | 0 |

## Payment Review authority
| Item | Value |
| --- | --- |
| Badge source | `OperationalInboxAuthority` / `AgencyDashboardService` bookings awaiting payment |
| Page source | `/bookings?queue=payment_review` (`payment_status IN unpaid,partial`) |
| Shared authority | PASS |
| Badge/result parity | PASS |
| Deep link | PASS |
| Proof queue (separate) | `/payments?reconciliation=pending_review` |

## Sabre void
- `SABRE_VOID_SUPPORT=DEFERRED_PROVIDER_CAPABILITY`
- Service class exists; `void_live_call_enabled` default false
- UI: Void disabled; reason: "Void is not supported by the current Sabre servicing adapter."
- `VOID_TICKET_FALSE_CAPABILITY=0`

## Finance parity
`FINANCE_PARITY=PASS_WITH_APPROVED_EXCLUSIONS`

Remaining non-PASS items (all explained):
1. Booking-level payment status depth polish — INTENTIONALLY_DEFERRED_BY_OWNER
2. Mark refund paid settlement — INTENTIONALLY_DEFERRED_BY_OWNER (Stage B)
3. Deposit proof download depth — INTENTIONALLY_DEFERRED_BY_OWNER
4. Legacy admin ledger — LEGACY_NOT_REQUIRED
5. Accounting ledger table polish / reconciliation workspace depth — INTENTIONALLY_DEFERRED_BY_OWNER
6. Finance KPI/FX presentation polish — INTENTIONALLY_DEFERRED_BY_OWNER
7. Wallet audit archive depth — INTENTIONALLY_DEFERRED_BY_OWNER

## Integrations
- `MULTIPLE_CONNECTIONS_PER_PROVIDER=PASS`
- `MULTI_CONNECTION_ROUTING_MODEL=PER_CONNECTION_FANOUT_WITH_OFFER_STICKINESS`
- AbhiPay Save Configuration visible + masked secret UX

## Playwright
- Config: `dashboard/playwright.jp-bo-04.config.ts`
- Runner: `dashboard/scripts/run-jp-bo-04-playwright.mjs`
- Result: **39 passed** (inbox, booking, payment override, integrations/AbhiPay, finance, SMTP, RBAC, CMS, sidebar desktop/mobile)
- Evidence: `tmp/jp-bo-04/playwright/`

## Runtime
- `EXACT_RUNTIME_FILE_COUNT=80` (JP-BO-04 delta vs starting docs SHA)
- `RUNTIME_LARAVEL_FILES=31`
- `RUNTIME_DASHBOARD_FILES=49`
- `RUNTIME_FRONTEND_FILES=0`
- `MIGRATIONS=0`
- `UNRELATED_RUNTIME_FILES=0`
- `CMS_PLATFORM_SCOPE_LEAKS=0`
- Manifest: `tmp/jp-bo-04/runtime-manifest.txt`

## Engineering commits
| Slice | Message | SHA |
| --- | --- | --- |
| 04A | feat(backoffice): JP-BO-04A operational parity and payment queue authority | c93d9a53… |
| 04B | feat(backoffice): JP-BO-04B booking lifecycle authority | 125b34ad… |
| 04C | feat(backoffice): JP-BO-04C multi-connection integrations administration | 0e51f4a4… |
| 04D | feat(backoffice): JP-BO-04D finance operational parity | 3154f7e0… |
| 04E | feat(backoffice): JP-BO-04E admin communications and operational parity | f22c87bd… |
| 04F | test(backoffice): JP-BO-04F operational coverage closure | f70e56b3… |

## Stage B proof plan (NOT EXECUTED)
Ready to prove under separate owner prompt:
1. Payment Review live parity (6→5 style authority on live data)
2. Booking actions live (fake adapters where required)
3. Synthetic inactive API connection CRUD + audit
4. AbhiPay Save UI without owner credential mutation
5. Reversible QA ledger adjustment → exact reversal → zero delta
6. SMTP/settings safe read/save/restore where allowed
7. Inbox deep links + audit/log references + live server logs
8. Owner-gated: real Sabre booking cancellation (separate authorization)

## Hard stop
NO production deployment. NO production mutations. Wait for ChatGPT/owner review of FINAL_ENGINEERING_SHA.
