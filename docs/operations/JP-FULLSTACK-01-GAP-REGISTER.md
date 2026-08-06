# JP-FULLSTACK-01 Gap Register

**Phase:** JP-FULLSTACK-01 — Public / Customer / Agent checkout connectivity audit
**Branch:** `phase/jetpk-fullstack-01-public-customer-agent-checkout-connectivity`
**Baseline SHA:** `846add82e0aea36e84e877b067bc2210ef2af467`
**Audit date:** 2026-08-06
**JSON:** [`JP-FULLSTACK-01-GAP-REGISTER.json`](JP-FULLSTACK-01-GAP-REGISTER.json)

## Severity totals

| BLOCKER | HIGH | MEDIUM | LOW | DOCUMENTATION |
|--------:|-----:|-------:|----:|--------------:|
| 0 | 4 | 9 | 5 | 2 |

**Gap records:** 20

## Executive summary

The public Next.js frontend (`frontend/`, 76 `page.tsx` routes) is **largely connected** to authoritative Laravel session/CSRF/RBAC and booking JSON contracts established in JP-OPS-01–07. No production `page.tsx` is **MOCK_ONLY** for business data. Remaining work concentrates on **Blade handoff surfaces** (force-password, guest booking detail, AbhiPay return), **agent travelers/finance gaps**, **verification coverage** for connected-but-untested routes, and **production fixture hardening**.

OTP demo patch (`OTP_DEMO_*`, `DemoFixedLoginOtpGate`) — **unchanged**.

## BLOCKER gaps

None identified at audit baseline.

## HIGH gaps

| ID | Area | Summary | Iteration | 01A status |
|----|------|---------|-----------|------------|
| JP-FS01-GAP-001 | Auth | Next `/password/force-change` + Laravel JSON | 01A | **CLOSED** |
| JP-FS01-GAP-002 | Guest lookup | Post-lookup guest booking Blade | 01E | Open |
| JP-FS01-GAP-003 | CMS | Content fixture misconfiguration risk | 01G | Open |
| JP-FS01-GAP-004 | Card payment | AbhiPay return → Next confirmation handoff | 01D | **CLOSED** |

## MEDIUM gaps

| ID | Area | Summary | Iteration | Status |
|----|------|---------|-----------|--------|
| JP-FS01-GAP-005 | Agent | `/agent/travelers` missing — Laravel CRUD exists | 01F | Open |
| JP-FS01-GAP-006 | Agent | Finance statement / accounting ledger — no Next consumer | 01F | Open |
| JP-FS01-GAP-007 | Search | Nearby-date strip on results (`fetchNearbyDates`) | 01B | **CLOSED** |
| JP-FS01-GAP-008 | Search | Multicity inquiry form POST on Next | 01B | **CLOSED** |
| JP-FS01-GAP-009 | Notifications | Stub `available:false` — deferred backend | DEFERRED | Open |
| JP-FS01-GAP-010 | Flights | Return-options spec + handoff verification | 01B | **CLOSED** |
| JP-FS01-GAP-011 | Agent | Payments/invoices connected but not verified in Playwright | 01F | Open |
| JP-FS01-GAP-020 | Checkout | Manual `pay_later` path verified with fixture JSON + Playwright | 01C | **CLOSED** |

## LOW / DOCUMENTATION

| ID | Severity | Summary |
|----|----------|---------|
| JP-FS01-GAP-012 | LOW | Customer payments — thin test coverage |
| JP-FS01-GAP-013 | LOW | Sitemap / dynamic CMS slugs — CNV |
| JP-FS01-GAP-014 | LOW | Return-combo Blade handoff — documented + allowlist tests | 01B **CLOSED** |
| JP-FS01-GAP-015 | LOW | Customer profile/security — visual tests only |
| JP-FS01-GAP-016 | LOW | Agent staff RBAC — re-verify on 01F |
| JP-FS01-GAP-017 | DOCUMENTATION | Stale route count in FINAL-ROUTE-MAP (67 vs 76) |
| JP-FS01-GAP-018 | DOCUMENTATION | JP-OPS-01 inventory predates new portal pages |
| JP-FS01-GAP-019 | LOW | Brand leakage audit — expand to checkout Blade views |

## Connectivity classification totals (76 routes)

| Status | Count |
|--------|------:|
| CONNECTED_AND_VERIFIED | 55 |
| CONNECTED_NOT_VERIFIED | 11 |
| STATIC_CONTENT | 4 |
| INTENTIONAL_BLADE_FALLBACK | 2 |
| DEFERRED_WITH_REASON | 2 |
| PLACEHOLDER | 1 |
| NOT_FOUND_OR_REDIRECT | 5 |
| MOCK_ONLY | 0 |
| BACKEND_EXISTS_FRONTEND_DISCONNECTED | 1 |
| FRONTEND_EXISTS_BACKEND_MISSING | 0 |

## Route classification totals

| Class | Count |
|-------|------:|
| PUBLIC | 8 |
| CMS | 7 |
| SUPPORT | 5 |
| SHARED_AUTH | 9 |
| CHECKOUT | 16 |
| CUSTOMER | 12 |
| AGENT | 21 |
| AGENT_STAFF | 0 (shared `/agent` tree) |
| PLACEHOLDER | 1 |
| NOT_FOUND_OR_REDIRECT | 5 |

## Auth / session / CSRF (audit findings)

- Session: `GET /api/public/auth/session` via `/laravel/*` proxy; `credentials: include`
- CSRF: `XSRF-TOKEN` + `X-XSRF-TOKEN`; booking/payment mutations do not auto-replay on 419
- Login failure: generic `auth.failed` — no email-existence leak (`LoginRequest.php`)
- Customer guard: `account.type:customer` + email verified (Laravel) + Next SSR layout
- Agent guard: `account.type:agent,agent_staff` + `agent.permission:*` (Laravel authoritative)
- OTP demo: preserved — no diff against baseline

## Payment rules (observed, not changed)

| UI | Canonical | Path |
|----|-----------|------|
| Manual Payment | `pay_later` | `POST /booking/review?format=json` |
| Pay by Card | `online_card` | AbhiPay `payments.abhipay.start` / guest variant |

No Master OTA / Parwaaz checkout URLs in frontend production paths. No raw card storage in Next.

## Proposed iterations

See JSON `proposed_iterations` — bounded **01A–01G**; do not combine into single change set.

## Files created by this audit

- `docs/operations/JP-FULLSTACK-01-GAP-REGISTER.md` (this file)
- `docs/operations/JP-FULLSTACK-01-GAP-REGISTER.json`
- `docs/operations/JP-FULLSTACK-01-AUDIT-REPORT.md`

No Laravel or `frontend/` implementation files modified.
