# JP-FULLSTACK-01A-R1 — Force-Password Regression Repair Closure

## Phase

| Field | Value |
|--------|--------|
| Phase | JP-FULLSTACK-01A-R1 |
| Branch | `phase/jetpk-fullstack-01a-r1-force-password-regression-repair` |
| Authoritative baseline | `6d9c047729722076eaa9dae5edd8f8eb3ea28c83` |
| Status | **READY FOR COMMIT REVIEW** (not committed) |

## Objective

Repair the verified pre-existing Playwright regression in `jp-fullstack-01a-force-password.spec.ts` and harden the fixture clearance mechanism so it cannot affect normal production authentication.

## Prior baseline reproduction (pre-fix)

```bash
npx playwright test tests/jp-fullstack-01a-force-password.spec.ts -c playwright.config.ts --project=chromium --workers=1 --retries=0
```

| Passed | Failed | Skipped | Exit |
|--------|--------|---------|------|
| 6 | 3 | 0 | 1 |

Failed titles remained on `/password/force-change` after mocked JSON success.

## Root cause

After successful JSON force-password mutation, `ForcePasswordChangeForm` navigated via `window.location.assign(redirect)`. SSR portal guards loaded Playwright session fixtures that remained static with `requires_password_change: true`, bouncing the browser back to `/password/force-change`.

Laravel production path was already correct (Feature tests prove flag clearing and redirect JSON).

## Fixture clearance mechanism (security gate)

### Cookie

| Attribute | Value |
|-----------|--------|
| Name | `ota_force_password_cleared` |
| Value | Literal `1` only (no user, role, password, CSRF token or session id) |
| Path | `/` |
| SameSite | `Lax` |
| Secure | Not set (local smoke); production relies on Laravel session, not this cookie |
| Max-Age / Expires | Session cookie (no Max-Age) |

### Write gate (client)

`markForcePasswordRequirementCleared()` in `ForcePasswordChangeForm` runs only after Laravel JSON success **and** only when `document.cookie` contains `ota_session_fixture=customer_force_password` or `agent_force_password`.

Normal production users have no `ota_session_fixture` cookie → **write is a no-op**.

### Read gate (SSR fixture resolver)

`resolveSessionBootstrapFixture()` returns `null` unless `OTA_ALLOW_SESSION_FIXTURE=true` (Playwright / capture scripts only).

`applyForcePasswordFixtureClearancePolicy()` honors `ota_force_password_cleared=1` only when:

1. fixture mode is enabled;
2. fixture value is `customer_force_password` or `agent_force_password`;
3. clearance cookie value is exactly `1`.

Outside fixture mode the resolver is not used; Laravel `PublicSessionBootstrapService` remains authoritative.

### Manual cookie bypass

| Scenario | Result |
|----------|--------|
| Clearance cookie alone, no session | Redirect to `/login` (Playwright proven) |
| Clearance cookie outside fixture mode | Resolver returns `null`; Laravel `must_change_password` governs |
| Clearance cookie with non-force fixture | Ignored by policy |
| Laravel middleware | Never reads clearance cookie |

Misconfigured production with `OTA_ALLOW_SESSION_FIXTURE=true` could allow fixture-only SSR bypass; this env is test/capture-only and must not be enabled in production.

## Production correction summary

1. Fixture clearance cookie written only for active force-password session fixtures.
2. Shared policy module (`force-password-clearance-policy.mjs`) gates write/read symmetrically.
3. Laravel force-password controller, middleware and JSON contract unchanged.

## Files changed

### Production (4)

| File | Change |
|------|--------|
| `frontend/features/auth/components/ForcePasswordChangeForm.tsx` | Gated clearance write after JSON success |
| `frontend/features/auth/server/session-fixture.ts` | Gated clearance read in fixture resolver |
| `frontend/features/auth/utils/force-password-clearance.ts` | Client helpers + policy import |
| `frontend/features/auth/utils/force-password-clearance-policy.mjs` | **New** — shared write/read policy |

### Tests (2)

| File | Change |
|------|--------|
| `frontend/tests/jp-fullstack-01a-force-password.spec.ts` | Security + redirect tests; fixture helper mirrors cookie for client reads |
| `frontend/tests/regression/jp-fullstack-01a-r1-force-password-clearance.test.mjs` | **New** — node policy regression |

### Documentation (2)

| File | Change |
|------|--------|
| `docs/phases/JP-FULLSTACK-01A-R1-FORCE-PASSWORD-REGRESSION-REPAIR-CLOSURE.md` | This document |
| `docs/operations/JP-FULLSTACK-01-AUDIT-REPORT.md` | Baseline exception status |

## Authoritative Laravel contract (unchanged)

| Item | Value |
|------|--------|
| Route | `password.force` / `password.force.store` |
| Controller | `ForcePasswordChangeController` |
| Customer destination | `/customer/bookings` |
| Agent destination | `/agent` → Next `/agent/dashboard` |
| Flag clearing | `must_change_password = false` on successful store |

## Security tests

### Node regression

```bash
node tests/regression/jp-fullstack-01a-r1-force-password-clearance.test.mjs
```

Covers write gate, read gate, policy outside fixture mode, non-credential cookie value, failed-mutation paths.

### Playwright (in `jp-fullstack-01a-force-password.spec.ts`)

- Clearance cookie alone cannot authorize customer portal without session
- 422 does not write clearance
- Failed 419 retry does not write clearance
- Successful fixture mutation writes clearance before navigation

## Test execution

### Laravel

```bash
php artisan test tests/Feature/Auth/ForcePasswordChangeJsonTest.php tests/Feature/Auth/PasswordUpdateTest.php tests/Feature/Auth/EnsurePasswordChangedMiddlewareTest.php
```

| Passed | 15 |
| Assertions | 38 |
| Exit | 0 |

### Force-password Playwright

```bash
npx playwright test tests/jp-fullstack-01a-force-password.spec.ts -c playwright.config.ts --project=chromium --workers=1 --retries=0
```

| Passed | 13 |
| Failed | 0 |
| Exit | 0 |

### Profile/security Playwright

```bash
npx playwright test tests/jp-fullstack-01e-profile-security.spec.ts -c playwright.config.ts --project=chromium --workers=1 --retries=0
```

| Passed | 6 |
| Exit | 0 |

### Portal-guard regression

Spec: `frontend/tests/jp-ops-02-portal-guards.spec.ts`

```bash
npx playwright test tests/jp-ops-02-portal-guards.spec.ts -c playwright.config.ts --project=chromium --workers=1 --retries=0
```

| Passed | 13 |
| Exit | 0 |

Included portal-guard titles: anonymous customer/agent → login; expired customer/agent → login; disabled customer/agent → access-denied; customer/agent/agent_staff dashboard access; customer cannot access agent; pathname query cannot promote role; no redirect loop for expired session.

### Frontend quality gates

| Command | Exit |
|---------|------|
| `npm run typecheck` | 0 |
| `npm run lint` | 0 |
| `npm run build` | 0 |

## Preservation verified

`.env`, `.env.example`, `config/`, OTP demo, `dashboard/`, Agent RBAC, payment-provider and supplier paths unchanged. No live calls.

## Remaining limitations

- Clearance cookie is fixture-test infrastructure only; production depends on Laravel session bootstrap after password update.
- `OTA_ALLOW_SESSION_FIXTURE=true` must never be enabled in production deployments.
- Agent Laravel redirect remains `/agent`; Next canonical portal entry is `/agent/dashboard`.

## Recommendation

**READY FOR JP-FULLSTACK-01A-R1 COMMIT**
