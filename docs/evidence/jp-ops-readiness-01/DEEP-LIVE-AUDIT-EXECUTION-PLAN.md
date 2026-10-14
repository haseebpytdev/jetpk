# JP-OPS-READINESS-01 — Deep Live Audit Execution Plan

```
BASELINE_DISCOVERY_COMPLETE=YES
DEEP_OPERATIONAL_EXECUTION_COMPLETE=NO
```

Governance preserved: **no real supplier bookings/tickets/cancels/refunds; no real payment charges; PIA frozen; no unauthorized AirBlue calls.**

## LIVE-AUDIT-WAVE-A — Critical auth / account / access

| Priority | Scope | Method |
|----------|-------|--------|
| P0 | Login/logout/session expiry per role | QA identities; no secret logging |
| P0 | Unauthorized deep-link negatives | platform_admin, staff, agent_owner A/B, each staff template, customer A/B |
| P1 | Viewer mutation block (cancel) | prove OPS-DEF-0004 live |
| P1 | Guest lookup OR-match | prove OPS-DEF-0005 without PII exposure |

## LIVE-AUDIT-WAVE-B — Search → booking → checkout (safe)

| Priority | Scope | Method |
|----------|-------|--------|
| P0 | Public search/results UI | read-only / certified fixtures; stop before supplier book |
| P0 | Fare/pax/checkout forms | validation + duplicate click; no live ticket |
| P1 | Payment UI paths | AbhiPay start **blocked** unless sandbox authorized; prefer status pages + code path |
| P1 | Agent booking create allowed journey | fixture/mock only |

## LIVE-AUDIT-WAVE-C — Wallet / ledger / finance

| Priority | Scope | Method |
|----------|-------|--------|
| P0 | Agency wallet summary vs wallets rows | read-only SQL + UI (OPS-DEF-0007) |
| P0 | Deposit list/detail ownership | read-only |
| P1 | Ledger idempotency evidence | existing tests + read-only ledger rows |
| P1 | Accountant/sales capability use | role QA |

## LIVE-AUDIT-WAVE-D — Admin / staff operations

| Priority | Scope | Method |
|----------|-------|--------|
| P0 | Ops inbox / bookings / payments verify UI | reversible QA only where framework exists |
| P1 | API connections / go-live pages | read-only config visibility |
| P1 | CMS/SEO smoke | reversible draft if required |

## LIVE-AUDIT-WAVE-E — Failure / recovery / idempotency

| Priority | Scope | Method |
|----------|-------|--------|
| P0 | AbhiPay duplicate callback (sandbox/staging if available) | never production charge |
| P0 | Double-submit booking/payment forms | UI + server |
| P1 | Queue failure visibility after Wave 1B archive | staff can diagnose |

## LIVE-AUDIT-WAVE-F — Remaining page/control coverage

| Priority | Scope | Method |
|----------|-------|--------|
| P2 | Remaining ~760 inventoried surfaces | batch control matrix; reuse FINAL-11 as UI ref only |
| P3 | Usability/a11y depth | after critical waves |

## Exit of deep execution

Deep execution may set `DEEP_OPERATIONAL_EXECUTION_COMPLETE=YES` only when critical journeys in A–E have evidence rows (not inventory-only) and open P0/P1 product defects are zero **or** explicitly waived.
