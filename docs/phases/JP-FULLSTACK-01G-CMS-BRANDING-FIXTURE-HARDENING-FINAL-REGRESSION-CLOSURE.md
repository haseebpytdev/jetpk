# JP-FULLSTACK-01G — CMS, Branding, Fixture Hardening and Final Regression Closure

**Phase:** JP-FULLSTACK-01G  
**Branch:** `phase/jetpk-fullstack-01g-cms-branding-fixture-hardening-final-regression`  
**Baseline SHA:** `47f7f90c410d88980830eb0e41b5e319f4be0995`  
**Status:** READY FOR COMMIT

## Gaps closed

| Gap ID | Original classification | Final classification |
|--------|----------------------|----------------------|
| JP-FS01-GAP-003 | IMPLEMENTATION_REQUIRED | CONNECTED_AND_VERIFIED |
| JP-FS01-GAP-013 | VERIFICATION_FIRST | CONNECTED_AND_VERIFIED |
| JP-FS01-GAP-017 | DOCUMENTATION_ONLY | CONNECTED_AND_VERIFIED |
| JP-FS01-GAP-018 | DOCUMENTATION_ONLY | CONNECTED_AND_VERIFIED |
| JP-FS01-GAP-019 | VERIFICATION_FIRST | CONNECTED_AND_VERIFIED |

**Deferred unchanged:** JP-FS01-GAP-009 (notifications backend).

## GAP-003 — CMS fixture policy

### Before

- `allowContentFixtures()` returned true when `NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES=true`, `OTA_ALLOW_SESSION_FIXTURE=true`, or `NODE_ENV=development`
- Session fixture flag unintentionally granted CMS fixture authority

### After

- `NODE_ENV=production` → **always false** (no flag override)
- `OTA_ALLOW_SESSION_FIXTURE` → **no CMS role** (session/auth fixtures only)
- Non-production: explicit `NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES=true` or `NODE_ENV=development`
- CMS present → `cms` source; CMS absent in production → honest `empty` / `notFound()`

### Production files changed

| File | Change |
|------|--------|
| `frontend/features/public-content/utils/content-policy-core.mjs` | Pure policy evaluator (new) |
| `frontend/features/public-content/utils/content-policy.ts` | Production gate + decoupling |

## GAP-013 — CMS route matrix

| Route | Coverage |
|-------|----------|
| `/`, `/about-us`, `/faq`, `/contact`, `/support`, `/terms`, `/privacy`, `/sitemap` | Static CMS shells + populated/empty mocks |
| `/[slug]`, `/legal/[slug]`, `/pages/[slug]` | Dynamic valid + unknown slug 404 |
| Reserved slugs (`/login`, `/verify-email`) | Not captured by CMS catch-all |
| Failure states | Laravel 500, empty payloads, malformed HTML rejection |

**Playwright:** `frontend/tests/jp-full-next-frontend/cms-bridge.spec.ts` (extended)

## GAP-017 — Route inventory

| Metric | Value |
|--------|------:|
| Production `page.tsx` count | **82** |
| Dev-only excluded | 1 (`/dev/jetpk-theme-lab`) |
| Redirect-only | 3 |

**Methodology:** Count every `frontend/app/**/page.tsx` once; exclude `dev/`; dynamic segments once; route groups omitted from URLs; `dashboard/` excluded; CMS DB slugs not expanded.

**Artifacts:** `docs/frontend/JP-FULL-NEXT-FRONTEND-FINAL-ROUTE-MAP.md`, `JP-FULL-NEXT-FRONTEND-FINAL-ROUTE-MAP.json`

**Regression:** `node frontend/tests/regression/jp-fullstack-01g-route-inventory.test.mjs`

## GAP-018 — JP-OPS-01 supersession

`docs/operations/JP-OPS-01-FULL-STACK-ROUTE-INVENTORY.md` preserved as historical baseline with supersession notice linking current route map and gap register.

## GAP-019 — Brand leakage

| Classification | Finding |
|----------------|---------|
| Visible production leakage | **None** |
| Dashboard-only comment (`Parwaaz` in `dashboard/travelers/*.blade.php`) | Out of scope |
| Production corrections | **None required** |

**Tests extended:**

- `frontend/tests/jp-full-next-frontend/leakage.spec.ts`
- `tests/Feature/Jetpk/PublicBladeBrandingLeakageAuditTest.php` (new)
- `tests/Feature/Agent/AgentPortalBrandingDropdownAuditTest.php` (updated for current jp-portal Blade shell)

## Tests executed

### Node regressions

```bash
node frontend/tests/regression/jp-fullstack-01g-content-policy.test.mjs
node frontend/tests/regression/jp-fullstack-01g-route-inventory.test.mjs
```

| Suite | Result |
|-------|--------|
| content-policy matrix | pass |
| route inventory parity | pass |

### Laravel (representative matrix)

```bash
php artisan test tests/Feature/Auth/PublicSessionBootstrapTest.php tests/Feature/Auth/ForcePasswordChangeJsonTest.php tests/Feature/Jetpk/PublicContentApiTest.php tests/Feature/Client/HomepageCmsContentNeutralityTest.php tests/Feature/NearbyDateFareStripTest.php tests/Feature/StandardBookingReviewJsonTest.php tests/Feature/Payments/AbhiPayReturnHandoffTest.php tests/Feature/Guest/GuestBookingDetailJsonTest.php tests/Feature/Customer/CustomerPaymentsJsonTest.php tests/Feature/Agent/AgentTravelersJsonTest.php tests/Feature/Agent/AgentFinanceStatementJsonTest.php tests/Feature/Agent/AgentAccountingLedgerJsonTest.php tests/Feature/Agent/AgentPaymentsInvoicesJsonTest.php tests/Feature/Agent/AgentStaffPermissionTest.php tests/Feature/Agent/AgentPortalPermissionMatrixFinalTest.php tests/Feature/Agent/AgentPortalDataScopingTest.php tests/Feature/SavedTravelerTest.php tests/Feature/Agent/AgentPortalBrandingDropdownAuditTest.php tests/Unit/Support/Emails/JetpkEmailBrandingLeakageAuditorTest.php tests/Feature/Jetpk/PublicBladeBrandingLeakageAuditTest.php
```

| Result | Value |
|--------|------:|
| Passed | 156 |
| Assertions | 630 |
| Exit code | 0 |

**Note:** `ReturnSplitSelectFlowTest` excluded — pre-existing errors (`Call to a member function all() on array`); return journey covered by Playwright `flight-return-options.spec.ts`.

### Playwright (representative matrix)

```bash
cd frontend && npx playwright test tests/jp-fullstack-01a-force-password.spec.ts tests/jp-ops-02-portal-guards.spec.ts tests/jp-ops-03-customer-operational.spec.ts tests/jp-ops-04-agent-operational.spec.ts tests/jp-ui-05b-logout-session-closure.spec.ts tests/jp-fullstack-01f-agent-travelers-finance.spec.ts tests/flight-return-options.spec.ts tests/standard-booking-review-payment.spec.ts tests/abhipay-return-confirmation.spec.ts tests/guest-booking-detail.spec.ts tests/jp-full-next-frontend/cms-bridge.spec.ts tests/jp-full-next-frontend/leakage.spec.ts tests/jp-full-next-frontend/route-matrix.spec.ts -c playwright.config.ts --project=chromium --workers=1 --retries=0
```

| Result | Value |
|--------|------:|
| Passed | **206** |
| Failed | 0 |
| Skipped | 0 |
| Exit code | 0 |

| Spec | Passed |
|------|-------:|
| `jp-full-next-frontend/cms-bridge.spec.ts` | 22 |
| `jp-full-next-frontend/route-matrix.spec.ts` | 44 |
| `jp-full-next-frontend/leakage.spec.ts` | 17 |
| `jp-fullstack-01a-force-password.spec.ts` | 13 |
| `jp-ops-02-portal-guards.spec.ts` | 13 |
| `jp-ops-03-customer-operational.spec.ts` | 11 |
| `jp-ops-04-agent-operational.spec.ts` | 39 |
| `jp-ui-05b-logout-session-closure.spec.ts` | 4 |
| `jp-fullstack-01f-agent-travelers-finance.spec.ts` | 25 |
| `flight-return-options.spec.ts` | 3 |
| `standard-booking-review-payment.spec.ts` | 8 |
| `abhipay-return-confirmation.spec.ts` | 4 |
| `guest-booking-detail.spec.ts` | 3 |

### Frontend quality gates

| Command | Exit |
|---------|-----:|
| `npm run typecheck` | 0 |
| `npm run lint` | 0 |
| `npm run build` | 0 |

Build ran without `NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES` or `OTA_ALLOW_SESSION_FIXTURE` overrides.

## Intentional fallbacks retained

- Blade agent/customer/guest/checkout fallbacks unchanged
- OTP demo patch unchanged
- GAP-009 notifications stub unchanged

## Safety

- No live supplier, payment, booking, ticketing, cancellation or email calls
- No deployment, commit, push or merge in this phase execution
- `dashboard/` unchanged
- RBAC / AgentPermission unchanged
