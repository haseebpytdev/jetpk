# JP-OPS-01 Test Coverage Matrix

**Phase:** JP-OPS-01 | **SHA:** `cfd65a76b448ec7fb77fddfb4995f290b5d841b3`

**Method:** File inventory + `php artisan test --list-tests` (listing only). No mutation suites executed.

## Coverage by domain

| Domain | Laravel Feature/Unit | Frontend Playwright | Dashboard Smoke | Gap |
|--------|---------------------:|--------------------:|----------------:|-----|
| Public routes | partial | **jp-full-next-frontend-routes.spec.ts** | — | CMS edge cases |
| Customer ownership | **CustomerBookingOwnershipTest**, **CustomerPortalJsonContractTest** | **customer-dashboard.spec.ts**, **jp-ui-05a-customer-ownership** | read-only-customers | Cancellation UI |
| Agent agency isolation | **AgentAgencyIsolationTest** | **agent-dashboard.spec.ts**, **jp-ui-05a-agent-rbac** | read-only-agents | Staff UI |
| Agent Staff RBAC | **AgentStaffPermissionTest**, **AgentPortalPermissionMatrixTest** | jp-ui-05a-agent-rbac | — | Reports/commissions |
| Admin/Staff permissions | Admin* tests, staff permission tests | — | jp-ui-05a-rbac | Dashboard mutations |
| Search | Sabre*, flight tests | **flight-results.spec.ts**, **search-laravel-handoff** | — | Per-supplier matrix |
| Revalidation | **SabreBookingRevalidatePhaseB13Test** | fare-selection specs | — | IATI |
| Booking checkout | **StandardBookingReviewJsonTest** | standard-booking-*, jp-ui-04a-* | — | Agent create |
| Payment/callbacks | payment feature tests | payment-portal specs | — | AbhiPay integration (non-live) |
| Wallet/deposits | **AgentWalletDepositTest**, **AgentLedgerTest** | agent-dashboard | — | Admin approve |
| Documents | document policy tests | invoice specs | — | — |
| CMS | **PublicContentApiTest**, JetpkCms* | **public-content.spec.ts**, cms-bridge | cms smoke | — |
| Supplier adapters | 50+ Sabre tests, IATI audit commands | — | — | One API live |
| Queues/jobs | scheduler unit tests | — | — | Runtime verification |
| Emails | **CustomerFacingMailableModernLayoutTest** | — | — | Production delivery |
| Cancellations/refunds | Sabre cancel gate tests | — | — | Customer cancel UI |
| Auth/OTP | **CustomerEmailVerificationTest**, auth tests | login/otp specs | — | Production OTP channel |
| Group ticketing | group feature tests | **group-ticketing.spec.ts** | — | Payment reconciliation |

## Frontend test files (representative)

| Path | Scope |
|------|-------|
| `frontend/tests/jp-full-next-frontend/route-matrix.spec.ts` | Public route smoke |
| `frontend/tests/jp-full-next-frontend/leakage.spec.ts` | Branding leakage |
| `frontend/tests/customer-dashboard.spec.ts` | Customer portal |
| `frontend/tests/agent-dashboard.spec.ts` | Agent portal |
| `frontend/tests/flight-results.spec.ts` | Results/filters |
| `frontend/tests/group-ticketing.spec.ts` | Group flow |
| `frontend/tests/jp-ui-05a-customer-ownership.spec.ts` | Ownership |
| `frontend/tests/jp-ui-05a-agent-rbac.spec.ts` | Agent RBAC |
| `frontend/tests/standard-booking-passengers.spec.ts` | Passengers + no PII in storage |

## Dashboard test files

| Path | Scope |
|------|-------|
| `dashboard/tests/jp-ui-05a-rbac.spec.ts` | RBAC fixture preview |
| `dashboard/tests/jp-ui-05a-hydration.spec.ts` | Hydration |
| `dashboard/tests/read-only-*.smoke.spec.ts` | Module smoke |
| `dashboard/tests/*.smoke.spec.ts` | Per-module |

## Laravel test volume (approximate)

- `tests/Feature/`: 200+ files
- `tests/Unit/`: 100+ files
- Agent cluster: 30+ dedicated tests
- Sabre cluster: 50+ tests

## Missing-test register

| ID | Missing test | Priority | Phase |
|----|-------------|----------|-------|
| T-01 | Next.js dashboard session identity (non-mock header) | P3 | JP-OPS-05 |
| T-02 | Customer cancellation request E2E | P1 | JP-OPS-03 |
| T-03 | Agent staff management Next.js route | P1 | JP-OPS-04 |
| T-04 | Agent booking create from Next.js | P1 | JP-OPS-04 |
| T-05 | Dashboard mutation API contract tests | P1 | JP-OPS-05 |
| T-06 | IATI auth resolution (non-live) | P1 | JP-OPS-06 |
| T-07 | Production scheduler/worker smoke (staging) | P2 | JP-OPS-06 |
| T-08 | Per-supplier return pairing contract | P2 | JP-OPS-06 |
| T-09 | Admin deposit approval via API (when built) | P2 | JP-OPS-05 |
| T-10 | Group payment card path (if applicable) | P3 | JP-OPS-06 |

## Permitted checks executed

Listed in phase summary — typecheck/lint/build/route:list to be run in final step.
