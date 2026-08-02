# JP-OPS-02 Test Matrix

## Laravel (targeted)

| Suite | Tests | Status |
|-------|-------|--------|
| `PublicSessionBootstrapTest` | 16 | PASS |
| `AuthPostLoginRedirectResolverTest` | 5 | PASS |
| `AuthenticationTest` | 23 | PASS |
| `JetPkLoginOtpTest` | 12 | PASS |
| **Total** | **55** | **PASS** |

### JP-OPS-02A triage corrections

- Added `ConfiguresAuthTestEnvironment` trait to isolate OTP gate in `AuthenticationTest` (credential/role tests) vs `JetPkLoginOtpTest` (OTP contract tests).
- Updated `JetPkLoginOtpTest` redirect expectations from deprecated `/jetpk/login/otp` parity paths to canonical `/login/otp` (JetPK standalone + Next.js frontend contract).
- Removed assertion for non-existent `client.parity.login.otp` route; replaced with `login.otp` route contract assertion.

### JP-OPS-02B regression additions

- `tests/Support/Auth/ConfiguresAuthTestEnvironment.php` — Laravel **test support** (not runtime).
- OTP gate isolation assertions in `AuthenticationTest` and `JetPkLoginOtpTest`.
- Durable client-security regression tests under `frontend/tests/regression/`.

### JP-OPS-02C runtime linkage

- `laravel-action-client.ts` imports `csrf-retry-policy.mjs` and `response-payload-policy.mjs` (not duplicated inline logic).
- Colocated `.d.ts` files provide TypeScript types for the shared `.mjs` policy modules.
- `frontend/tests/regression/jp-ops-02-runtime-linkage.test.mjs` asserts production imports remain bound.

### Coverage

- Anonymous session contract
- Customer/Agent/Admin/Staff session shapes
- Agent owner vs staff `agency_role`
- Disabled `session_usable`
- Cache headers (session + CSRF)
- OTP non-disclosure in session bootstrap
- Redirect resolver precedence
- Logout JSON, forgot-password generic, OTP verify JSON
- Authentication credential validation, role access, inactive/suspended denial

## Frontend

| Command | Status |
|---------|--------|
| `npm run typecheck` | PASS |
| `npm run lint` | PASS |
| `npm run build` | PASS |
| `npm run test:jp-ops-02-client-security` | PASS |

### Permanent client-security regression files

| File | Purpose |
|------|---------|
| `frontend/tests/regression/jp-ops-02-api-errors.test.mjs` | API error normalization (401/403/419/422/429/5xx/HTML/malformed JSON) |
| `frontend/tests/regression/jp-ops-02-csrf-replay.test.mjs` | CSRF replay safety (default no-replay, retryCsrfOnce, booking/payment paths) |
| `frontend/tests/regression/jp-ops-02-runtime-linkage.test.mjs` | Production policy import binding assertion |
| `frontend/tests/regression/run-jp-ops-02-client-security.mjs` | Runner for all regression suites |
| `frontend/lib/api/csrf-retry-policy.mjs` | Shared CSRF retry policy module (imported by production client) |
| `frontend/lib/api/csrf-retry-policy.d.ts` | TypeScript declarations for CSRF policy |
| `frontend/lib/api/response-payload-policy.mjs` | Shared non-JSON/HTML payload policy module (imported by production client) |
| `frontend/lib/api/response-payload-policy.d.ts` | TypeScript declarations for payload policy |

### Playwright (auth-related)

| Spec | Tests | Coverage |
|------|-------|----------|
| `tests/auth.spec.ts` | 11 | Login shell, generic errors, OTP transition, session-expired notice |
| `tests/jp-ops-02-portal-guards.spec.ts` | 13 | Layout guards: anonymous, expired, disabled, customer, agent, agent_staff, wrong role, no loop |

**Playwright command:**

```bash
npx playwright test -c playwright.config.ts tests/auth.spec.ts tests/jp-ops-02-portal-guards.spec.ts
```

## Dashboard

Not run (no dashboard files changed).
