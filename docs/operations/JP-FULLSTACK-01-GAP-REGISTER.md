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

The public Next.js frontend (`frontend/`, **82** production `page.tsx` routes excluding `dev/`) is **largely connected** to authoritative Laravel session/CSRF/RBAC and booking JSON contracts established in JP-OPS-01–07. No production `page.tsx` is **MOCK_ONLY** for business data. **JP-FULLSTACK-01G** closed CMS fixture hardening, dynamic CMS verification, route inventory parity, branding leakage audits and final representative regression.

OTP demo patch (`OTP_DEMO_*`, `DemoFixedLoginOtpGate`) — **unchanged**.

## BLOCKER gaps

None identified at audit baseline.

## HIGH gaps

| ID | Area | Summary | Iteration | 01A status |
|----|------|---------|-----------|------------|
| JP-FS01-GAP-001 | Auth | Next `/password/force-change` + Laravel JSON | 01A | **CLOSED** |
| JP-FS01-GAP-002 | Guest lookup | Post-lookup Next guest detail + additive Laravel JSON | 01E | CONNECTED_AND_VERIFIED |
| JP-FS01-GAP-003 | CMS | Content fixture misconfiguration risk | 01G | **CONNECTED_AND_VERIFIED** |
| JP-FS01-GAP-004 | Card payment | AbhiPay return → Next confirmation handoff | 01D | **CLOSED** |

## MEDIUM gaps

| ID | Area | Summary | Iteration | Status |
|----|------|---------|-----------|--------|
| JP-FS01-GAP-005 | Agent | `/agent/travelers` missing — Laravel CRUD exists | 01F | **CONNECTED_AND_VERIFIED** |
| JP-FS01-GAP-006 | Agent | Finance statement / accounting ledger — no Next consumer | 01F | **CONNECTED_AND_VERIFIED** |
| JP-FS01-GAP-007 | Search | Nearby-date strip on results (`fetchNearbyDates`) | 01B | **CLOSED** |
| JP-FS01-GAP-008 | Search | Multicity inquiry form POST on Next | 01B | **CLOSED** |
| JP-FS01-GAP-009 | Notifications | Stub `available:false` — deferred backend | DEFERRED | Open |
| JP-FS01-GAP-010 | Flights | Return-options spec + handoff verification | 01B | **CLOSED** |
| JP-FS01-GAP-011 | Agent | Payments/invoices connected but not verified in Playwright | 01F | **CONNECTED_AND_VERIFIED** |
| JP-FS01-GAP-020 | Checkout | Manual `pay_later` path verified with fixture JSON + Playwright | 01C | **CLOSED** |

## LOW / DOCUMENTATION

| ID | Severity | Summary | Iteration | Status |
|----|----------|---------|-----------|--------|
| JP-FS01-GAP-012 | LOW | Customer payments — thin test coverage | 01E | CONNECTED_AND_VERIFIED |
| JP-FS01-GAP-013 | LOW | Sitemap / dynamic CMS slugs — CNV | 01G | **CONNECTED_AND_VERIFIED** |
| JP-FS01-GAP-014 | LOW | Return-combo Blade handoff — documented + allowlist tests | 01B | **CLOSED** |
| JP-FS01-GAP-015 | LOW | Customer profile/security — visual tests only | 01E | CONNECTED_AND_VERIFIED |
| JP-FS01-GAP-016 | LOW | Agent staff RBAC — re-verify on 01F | 01F | **CONNECTED_AND_VERIFIED** |
| JP-FS01-GAP-017 | DOCUMENTATION | Route inventory parity (82 production routes) | 01G | **CONNECTED_AND_VERIFIED** |
| JP-FS01-GAP-018 | DOCUMENTATION | JP-OPS-01 inventory supersession notice | 01G | **CONNECTED_AND_VERIFIED** |
| JP-FS01-GAP-019 | LOW | Brand leakage audit — expanded Blade + Playwright | 01G | **CONNECTED_AND_VERIFIED** |

## Connectivity classification totals (82 production routes)

| Status | Count |
|--------|------:|
| CONNECTED_AND_VERIFIED | 67 |
| CONNECTED_NOT_VERIFIED | 9 |
| STATIC_CONTENT | 4 |
| INTENTIONAL_BLADE_FALLBACK | 2 |
| DEFERRED_WITH_REASON | 2 |
| PLACEHOLDER | 1 |
| NOT_FOUND_OR_REDIRECT | 5 |
| MOCK_ONLY | 0 |
| BACKEND_EXISTS_FRONTEND_DISCONNECTED | 0 |
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
| AGENT | 26 |
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

---

## JP-FULLSTACK-01E implementation boundary (locked)

**Scope-lock branch:** `phase/jetpk-fullstack-01e-scope-lock`
**Baseline:** `749ae0ae7d70734f53794c79c7f54e4d1f2b8305`
**Implementation branch (after scope-lock commit):** `phase/jetpk-fullstack-01e-guest-lookup-customer-portal-verification`

### Exact 01E gaps

| Gap ID | Severity | Scope |
|--------|----------|-------|
| JP-FS01-GAP-002 | HIGH | Authoritative Next guest-detail page + additive Laravel JSON |
| JP-FS01-GAP-012 | LOW | Customer payments verification (no new feature work) |
| JP-FS01-GAP-015 | LOW | Customer profile/security verification (no new feature work) |

Not in 01E: booking history, support, invoices, Agent, CMS, notifications, checkout, force-password, AbhiPay return (01D closed).

### GAP-002 — locked implementation decision

**Primary closure:** canonical Next guest-detail page at `/guest/bookings/{booking}/access/{token}` loading authoritative Laravel JSON. Blade HTML remains an intentional fallback; it is not the primary closure strategy.

#### A. Laravel authoritative guest-detail JSON

- Extend `GuestBookingLookupController@showGuestBooking` with additive `?format=json` on route `guest.bookings.show`.
- Preserve existing Blade HTML response when JSON is not requested.
- Reuse `GuestBookingAccessService` for token validation (booking ID + access token server-side).
- Return only customer-safe booking data; never trust browser-supplied booking, payment, PNR or ticket state.
- Generic denial on invalid/expired token; no booking-existence leak.
- Do not expose internal database metadata, supplier payloads, callback secrets or raw provider data.
- JSON and Blade must present the same authoritative booking for the same token.

**Guest JSON contract (additive):**

| Item | Value |
|------|--------|
| URI | `GET /guest/bookings/{booking}/access/{token}?format=json` |
| Route name | `guest.bookings.show` |
| Controller | `GuestBookingLookupController@showGuestBooking` |
| Auth | Token-scoped guest access via `GuestBookingAccessService::validateToken` |
| CSRF | GET only for detail JSON |
| Safe fields | `ok`, `booking_reference`, `booking_status`, `payment_status`, `ticketing_status`, masked contact, passengers (safe fields), itinerary overview, `payment_summary` (amounts/status labels only), `capabilities` (mutation URLs + booleans), `documents` (title/status/download_url when ready), `pnr_details` and `tickets` only when Laravel has real data, `presentation` labels |
| Forbidden in JSON | Supplier payloads, gateway secrets, internal IDs beyond booking reference, fabricated PNR/ticket/paid state |

#### B. Canonical Next guest-detail route

| Public route | App Router path |
|--------------|-----------------|
| `/guest/bookings/{booking}/access/{token}` | `frontend/app/(public)/guest/bookings/[booking]/access/[token]/page.tsx` |

Next page requirements:

- Load authoritative Laravel JSON via `/laravel/guest/bookings/{booking}/access/{token}?format=json`.
- Keep token in path only (existing contract); never persist token in `localStorage` or `sessionStorage`.
- Display booking, passenger, itinerary, payment, PNR and ticket data only when Laravel returns it.
- Distinguish unavailable, pending, failed, unpaid, paid, confirmed, cancelled and ticketed states where supported.
- Safe error states for invalid or expired access.
- Never fabricate payment, PNR, ticket or invoice data.

#### C. Lookup redirect

- After successful lookup, `resolveSafeGuestLookupRedirect` must permit the canonical internal Next route `/guest/bookings/{id}/access/{token}`.
- External redirects remain forbidden.
- Laravel-origin paths may still be rewritten to `/laravel/...`.
- Blade URL `/laravel/guest/bookings/{id}/access/{token}` remains intentional fallback (link on lookup page retained).

#### D. Guest mutation handoff (locked)

| Mutation | Route | 01E disposition |
|----------|-------|-----------------|
| Guest detail read | `GET guest.bookings.show` JSON | **Consumed by Next guest page** |
| Cancellation request | `POST guest.bookings.cancellations.store` | **Consumed by Next guest page** when JSON `capabilities.can_request_cancellation` |
| Payment proof upload | `POST guest.bookings.payment-proof` | **Consumed by Next guest page** when JSON `capabilities.can_upload_payment_proof` |
| Document download | `GET guest.documents.download?token=` | **Consumed by Next guest page** via Laravel download URL in JSON only |
| Guest AbhiPay card start | `POST guest.bookings.abhipay.start` | **Blade fallback handoff** — link to Blade payment section / Laravel start; no guest AbhiPay redirect logic in 01E Next page |
| Promo apply/remove | `POST guest.bookings.promo.apply`, `guest.bookings.promo.remove` | **Blade fallback handoff** — promo UI remains Blade; Next links to Blade fallback |
| Full guest Blade surface | `GET guest.bookings.show` HTML | **Blade fallback handoff** — retained; not primary closure |
| Payment callback processing | `payments.abhipay.callback` | **Outside GAP-002** |
| Supplier booking / ticketing engines | internal services | **Outside GAP-002** |
| Cancellation approval workflow | staff/admin | **Outside GAP-002** |

Do not modify payment-provider verification, callback processing, transaction state, supplier booking or cancellation engines.

### GAP-012 — locked verification boundary

- Existing `/customer/payments` Next page and `customer.payments.index` Laravel JSON only.
- Add dedicated Laravel customer-ownership contract test if not already adequate.
- Add Playwright list, empty and error-state verification in `jp-ops-03-customer-operational.spec.ts`.
- No production implementation change unless tests prove a real defect.
- Out of scope: payment-provider, checkout, invoice implementation.

### GAP-015 — locked verification boundary

- `/customer/profile` and `/customer/security` only.
- Authoritative `GET customer.profile.show`, `PATCH profile.update`, `PUT password.update`.
- CSRF and authenticated-customer enforcement.
- Playwright: profile load, profile PATCH, profile 422, password PUT, password 422, CSRF, one bounded 419 retry, expired session.
- No password policy, force-password, Admin/Staff auth or OTP changes.
- No production implementation change unless tests prove a real defect.

### Permitted path families (implementation branch)

- `app/Http/Controllers/Frontend/GuestBookingLookupController.php` (additive JSON only)
- `app/Support/` or presenter used for guest-detail JSON (additive)
- `app/Services/Customer/GuestBookingAccessService.php` (read-only reuse; tests only if needed)
- `tests/Feature/Guest/GuestBookingLookupRedesignTest.php` and new guest-detail JSON contract tests
- `frontend/app/(public)/guest/bookings/[booking]/access/[token]/`
- `frontend/features/standard-booking/lookup/` (redirect allowlist update)
- `frontend/features/customer-dashboard/payments/`, `profile/`, `security/` (defect fixes only)
- `frontend/features/customer-dashboard/services/customer-dashboard-api.ts` (defect fixes only)
- `frontend/tests/booking-lookup-turnstile.spec.ts`, guest-detail Playwright spec
- `frontend/tests/jp-ops-03-customer-operational.spec.ts`, `jp-ui-05a-profile-logout.spec.ts`
- `tests/Feature/Customer/` payments/profile contract tests (additive)
- `docs/operations/JP-FULLSTACK-01-GAP-REGISTER.md`, `.json`, `JP-FULLSTACK-01-AUDIT-REPORT.md`
- `docs/phases/JP-FULLSTACK-01E-*-CLOSURE.md` (on implementation close)

### Prohibited

- `dashboard/`; Agent and Agent Staff portal; CMS and branding; notification backend
- Search, fare, passengers, review checkout paths
- Payment-provider and callback logic changes
- Supplier booking, ticketing and cancellation engines
- OTP demo files; `.env` and credential configuration
- Blade removal

### Test matrix (locked)

**GAP-002 Laravel:** valid token → authoritative JSON; invalid token → generic denial; mismatched booking/token rejected; guest cannot access another booking; Blade HTML still available; JSON and Blade same booking; no fabricated PNR/ticket/paid/invoice; safe response-field contract.

**GAP-002 Playwright:** lookup POST redirects only to internal guest route; guest detail loads Laravel JSON; invalid/expired access; authoritative itinerary/payment rendering; conditional PNR/ticket; no external redirect; no client-inferred status; Blade fallback link valid.

**GAP-012:** Laravel customer ownership + payments JSON; Playwright list, empty, server-error, unauthenticated/expired session.

**GAP-015:** profile load; profile PATCH; profile 422; password PUT; password 422; CSRF; one bounded 419 retry; expired session; no credential persistence.

**Regression only:** customer bookings/detail/support/invoices; force-password; manual payment; AbhiPay return confirmation.

---

## JP-FULLSTACK-01E implementation closure

| Gap | Original | Final | Production changes |
|-----|----------|-------|-------------------|
| JP-FS01-GAP-002 | HIGH | CONNECTED_AND_VERIFIED | Guest detail JSON, lookup JSON redirect, Next guest page, mutation JSON |
| JP-FS01-GAP-012 | LOW | CONNECTED_AND_VERIFIED | Verification only — no production defect |
| JP-FS01-GAP-015 | LOW | CONNECTED_AND_VERIFIED | Verification only — no production defect |

Closure record: [`docs/phases/JP-FULLSTACK-01E-GUEST-LOOKUP-CUSTOMER-PORTAL-CLOSURE.md`](../../phases/JP-FULLSTACK-01E-GUEST-LOOKUP-CUSTOMER-PORTAL-CLOSURE.md)

---

## JP-FULLSTACK-01F implementation closure

| Gap | Original | Final | Production changes |
|-----|----------|-------|-------------------|
| JP-FS01-GAP-005 | MEDIUM — BACKEND_EXISTS_FRONTEND_DISCONNECTED | CONNECTED_AND_VERIFIED | Agent travelers additive JSON + Next CRUD |
| JP-FS01-GAP-006 | MEDIUM — BACKEND_EXISTS_FRONTEND_DISCONNECTED | CONNECTED_AND_VERIFIED | Finance statement + accounting ledger read-only JSON and Next surfaces |
| JP-FS01-GAP-011 | MEDIUM — CONNECTED_NOT_VERIFIED | CONNECTED_AND_VERIFIED | Verification only — no production defect |
| JP-FS01-GAP-016 | LOW — CONNECTED_NOT_VERIFIED | CONNECTED_AND_VERIFIED | RBAC regression re-verified — no production defect |

Closure record: [`docs/phases/JP-FULLSTACK-01F-AGENT-AGENT-STAFF-RBAC-TRAVELERS-CLOSURE.md`](../../phases/JP-FULLSTACK-01F-AGENT-AGENT-STAFF-RBAC-TRAVELERS-CLOSURE.md)
