# Phase 6 — Route and command security audit

**Supersedes for review:** use this document with Phase 6 manifest; Phase 4 audit remains historical reference only.

## HTTP routes (One API checkout)

| Check | Status |
|-------|--------|
| Mutations require authentication | **Pass** — `routes/web.php` group uses `auth` |
| CSRF on POST mutations | **Pass** — web middleware stack |
| No GET booking mutation | **Pass** — catalog GET read-only; book via existing booking POST flows |
| No `fixture_path` / transport query params on checkout | **Pass** — controller rejects on final-price |
| No public TID/RPH/cookie in responses | **Pass** — readiness + checkout presenters mask session evidence |
| Cross-user / cross-agency workflow | **Pass** — `OneApiWorkflowContextGuard` (see phase-6-security-verification.md) |

## Artisan commands

| Command | Live gate | Secret redaction |
|---------|-----------|------------------|
| `ota:one-api-test-matrix` | `--mode=fixture` default; live requires explicit flags | Matrix CSV masks JSESSION evidence |
| `ota:one-api-fixture-test` | Fixture scope only | Uses allowlisted catalog |
| `ota:one-api-search-probe` | Env + confirmation patterns (see command) | Auth logs redacted in `OneApiAuthService` |
| `ota:one-api-price-probe` | SOAP URL required | No fixture path in production binding |
| `ota:one-api-read-reservation` | PNR + connection scoped | Admin/policy gated |
| `ota:one-api-reconcile-booking` | Booking-scoped | No public exposure |
| `ota:one-api-connection-audit` | Readiness only | Passwords never echoed |
| `ota:one-api-phase-6-inventory` | Local manifest generation only | No credentials |

## Gaps

- Automated route list diff vs `php artisan route:list` not attached in this pass.
- Per-route policy matrix for admin SupplierConnection (platform admin) covered by `OneApiSupplierConnectionFeatureTest` only partially.

## Secret scan

See `storage/app/one-api-phase-6-secret-scan.txt` — **0** high-confidence credential patterns in One API code/fixtures/docs scan (2026-07-23).
