# JP-OPS-02 — Auth, Session, and API-Bridge Closure

## Phase summary

| Field | Value |
|-------|-------|
| Phase | JP-OPS-02 |
| Branch | `phase/jetpk-ops-02-auth-session-api-bridge` |
| Baseline SHA | `d216a71604830bc4aaf639be7f3df5fac70efc24` |
| Status | **READY FOR JP-OPS-02 COMMIT** |

## Objective

Close cross-cutting authentication, session, CSRF, role-context, and Laravel-to-Next API-bridge gaps.

## Root causes found

1. Frontend `PublicSession` dropped permissions, status, agency, and gate flags from Laravel bootstrap
2. Session/CSRF JSON lacked `no-store` cache headers
3. API client mapped 419 to `unknown` with no CSRF refresh policy
4. Portal guards were per-page only; layouts did not enforce auth
5. Agent owner vs staff not distinguished in public session
6. Role redirect logic scattered; no documented precedence
7. Preview/fixture identity could act as authority if misconfigured in production
8. No centralized stale-session recovery from API layer

## Laravel files changed

- `app/Support/Auth/PublicSessionBootstrapService.php`
- `app/Support/Auth/AuthPostLoginRedirectResolver.php` (new)
- `app/Contracts/Auth/LoginOtpChannelProvider.php` (new)
- `app/Http/Controllers/Api/PublicSessionController.php`
- `app/Http/Controllers/Api/PublicContentApiController.php`
- `app/Http/Controllers/Auth/LoginOtpController.php`
- `tests/Feature/Auth/PublicSessionBootstrapTest.php`
- `tests/Feature/Auth/AuthPostLoginRedirectResolverTest.php` (new)
- `tests/Feature/Auth/AuthenticationTest.php`
- `tests/Feature/Auth/JetPkLoginOtpTest.php`
- `tests/Support/Auth/ConfiguresAuthTestEnvironment.php` (new, test support)

## Frontend files changed (17 runtime paths)

- `frontend/features/auth/types/index.ts`
- `frontend/types/session.ts`
- `frontend/features/auth/services/session-service.ts`
- `frontend/features/auth/server/portal-access-shared.ts` (new)
- `frontend/features/auth/server/customer-portal-access.ts`
- `frontend/features/auth/server/agent-portal-access.ts`
- `frontend/features/auth/server/session-fixture.ts`
- `frontend/lib/api/errors.ts`
- `frontend/lib/api/types.ts`
- `frontend/lib/api/laravel-action-client.ts`
- `frontend/lib/api/session-recovery.ts` (new)
- `frontend/lib/api/index.ts`
- `frontend/services/session.ts`
- `frontend/app/customer/layout.tsx`
- `frontend/app/agent/layout.tsx`
- `frontend/app/(auth)/login/page.tsx`
- `frontend/app/access-denied/page.tsx`

## Frontend test/support files changed

- `frontend/tests/auth.spec.ts`
- `frontend/tests/jp-ops-02-portal-guards.spec.ts` (new)
- `frontend/tests/regression/jp-ops-02-api-errors.test.mjs` (new)
- `frontend/tests/regression/jp-ops-02-csrf-replay.test.mjs` (new)
- `frontend/tests/regression/run-jp-ops-02-client-security.mjs` (new)
- `frontend/lib/api/csrf-retry-policy.mjs` (new, shared policy module)
- `frontend/lib/api/response-payload-policy.mjs` (new, shared policy module)
- `frontend/package.json` (client-security npm scripts)

## Dashboard files changed

None.

## Routes changed

No new routes. Session endpoint `GET /api/public/auth/session` response schema enriched.

## Canonical session schema

See `docs/operations/JP-OPS-02-AUTH-SESSION-CONTRACT.md`.

## Results

| Area | Result |
|------|--------|
| CSRF | 419 → `csrf_expired`; conservative refresh; no-store headers |
| Login | Generic errors preserved; server-validated redirects |
| OTP | Demo patch preserved; provider contract added; no OTP in JSON |
| Email verification | Existing signed flow unchanged |
| Password reset | Generic enumeration-safe responses preserved |
| Logout | POST + CSRF; session invalidation |
| Customer private routes | Layout-level Laravel guards |
| Agent private routes | Layout-level Laravel guards |
| Agent Staff distinction | `agency_role: staff` in session |
| Admin/Staff compatibility | `portal_type` fields in session contract |
| API bridge | Typed errors, malformed JSON handling |
| Session expiry | `recoverFromUnauthorized()` utility |
| Cache/headers | `no-store, private` on session + CSRF |
| Audit logging | Existing SecurityEventLogger unchanged |

## Tests

- Laravel targeted: 55/55 PASS (`AuthenticationTest` + `JetPkLoginOtpTest` OTP gate isolation assertions)
- Frontend typecheck: PASS
- Frontend lint: PASS
- Frontend build: PASS
- `npm run test:jp-ops-02-client-security`: PASS
- Playwright auth/guards: 24/24 PASS

## Gaps closed

- OPS02-R1 through OPS02-R8 (see implementation register)
- GAP-015 partially closed (provider contract; live channel external)

## Gaps deferred

- GAP-002 (dashboard mock chrome) → JP-OPS-05
- GAP-015 live production OTP channel → external runtime

## Permanent client-security regression commands

```bash
cd frontend
npm run test:jp-ops-02-client-security
# or individually:
npm run test:jp-ops-02-api-errors
npm run test:jp-ops-02-csrf-replay
```

## OTP demo patch preservation

Confirmed — `config/ota_otp_demo.php` and `app/Support/Auth/DemoFixedLoginOtpGate.php` have **no diff** against baseline `d216a71`.

## Documents created

- `docs/operations/JP-OPS-02-IMPLEMENTATION-REGISTER.md`
- `docs/operations/JP-OPS-02-AUTH-SESSION-CONTRACT.md`
- `docs/operations/JP-OPS-02-CSRF-API-BRIDGE-CONTRACT.md`
- `docs/operations/JP-OPS-02-ROLE-REDIRECT-MATRIX.md`
- `docs/operations/JP-OPS-02-OTP-EMAIL-VERIFICATION-CONTRACT.md`
- `docs/operations/JP-OPS-02-SESSION-EXPIRY-RECOVERY-MATRIX.md`
- `docs/operations/JP-OPS-02-API-ERROR-MATRIX.md`
- `docs/operations/JP-OPS-02-TEST-MATRIX.md`
- `docs/operations/JP-OPS-02-PRODUCTION-RUNTIME-REQUIREMENTS.md`
- `docs/phases/JP-OPS-02-AUTH-SESSION-API-BRIDGE-CLOSURE.md` (this file)

## Git hygiene

- `git diff --check`: PASS (no conflict markers)
- No generated artifacts committed
- No commit, push, merge, or deploy performed

## Production untouched

Confirmed — no production server configuration or deployment actions.

## Recommendation

**READY FOR JP-OPS-02 COMMIT**
