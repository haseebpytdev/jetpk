# OPS-DEF-0005 / OPS-DEF-0008 — Security Remediation

**Date:** 2026-10-16  
**PROTECTED_MAIN_SHA:** `27743239842d8f6a5a5a1161c71c961c95a437cb`  
**PRODUCTION_RUNTIME_SHA:** `922f15774ece177d8b3cc23207e98379b8a2755b` (unchanged)  
**Branch:** `security/ops-def-0005-0008-isolation`

```
NO_PRODUCTION_MUTATION=YES
NO_DEPLOYMENT=YES
SABRE_PNR_CREATE_CALLS=0
SUPPLIER_MUTATIONS=0
PAYMENT_MUTATIONS=0
WALLET_MUTATIONS=0
LEDGER_MUTATIONS=0
```

---

## OPS-DEF-0005 — Guest lookup OR → AND

| Field | Value |
|-------|--------|
| SOURCE_CONFIRMED | YES — `GuestBookingAccessService::findBookingForLookup` used `($emailMatch \|\| $phoneMatch)` when both supplied |
| EXPLOITABILITY | YES — public `POST /lookup-booking` (`lookup-booking.submit`); email required, phone optional; Turnstile optional in tests |
| TOKEN_ISSUANCE_AFTER_PARTIAL_CONTACT_MATCH | YES (pre-fix) / NO (post-fix) |
| FIX_IMPLEMENTED | YES — both factors require `$emailMatch && $phoneMatch` |
| REGRESSION_TESTS | YES — `CustomerPortalAndGuestLookupTest` AND cases |
| STATUS | FIX_READY_FOR_DEPLOYMENT (not CLOSED until prod deploy+verify) |

Contract after fix: email-only OK; phone-only (service) OK; both → AND; wrong either factor → deny; no token on deny.

---

## OPS-DEF-0008 — Deposit index agent isolation

| Field | Value |
|-------|--------|
| SOURCE_CONFIRMED | YES — `DashboardDepositsReadService::scopedQuery` agency-only for non-platform |
| DEPOSIT_INDEX_ROUTE | `GET /api/dashboard/deposits` (`api.dashboard.deposits.index`) |
| ROUTE_MIDDLEWARE | `web`, `auth`, `agency.context`, account types, `throttle:120,1`, `dashboard.permission:agents.view` |
| AGENT_PORTAL_ROUTE_REACHABLE | NO today — `agents.view` requires `AgentPolicy::viewAny` → platform_admin only |
| WALLET_VIEW_AGENT_CAN_REACH_INDEX | NO via HTTP middleware; YES if service called with WalletView agent (policy viewAny allows) |
| EXPLOITABILITY | LATENT_SERVICE_IDOR — confirmed via service-layer fixtures; HTTP currently blocked by agents.view |
| FIX_IMPLEMENTED | YES — agent portal adds `agent_id = user.agent.id`; fail-closed `0=1` if agent missing |
| REGRESSION_TESTS | YES — `DashboardDepositsIsolationTest` |
| STATUS | FIX_READY_FOR_DEPLOYMENT |

Pre-fix service: same-agency peer deposits visible to Agent A. Post-fix: own only; detail peer → null; no WalletView → AuthorizationException; HTTP agent → 403.

---

## Tests

```
TARGETED: 16 passed, 42 assertions (guest AND + deposits isolation + baseline lookup)
BROADER: 88/92 passed in CustomerPortal/Guest/DashboardReadOnly/AgentWallet/AdminDeposit filter
BROADER_FAIL: 4 pre-existing UI testid/branding assertions (not caused by this change)
PHP_SYNTAX: PASS
```

Local note: Composer autoload had been generated with `baseDir` under `.recovery/ops-def-0013-payment-idempotency` (stale OR class). Regenerated from workspace root before verification. Do not commit `vendor/`.

---

## Defect / risk status

| ID | Status |
|----|--------|
| OPS-DEF-0005 | FIX_READY_FOR_DEPLOYMENT |
| OPS-DEF-0008 | FIX_READY_FOR_DEPLOYMENT |
| OPS-RISK-0006 | MITIGATION_READY_NOT_DEPLOYED |

Do **not** mark CLOSED until deployed and production-verified.
