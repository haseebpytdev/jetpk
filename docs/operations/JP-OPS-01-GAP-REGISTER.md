# JP-OPS-01 Gap Register (JP-OPS-01A reconciled)

**Gap records:** 15 | **SHA:** `cfd65a76b448ec7fb77fddfb4995f290b5d841b3`
JSON: [`JP-OPS-01-GAP-REGISTER.json`](JP-OPS-01-GAP-REGISTER.json)

## Severity counts

| P0 | P1 | P2 | P3 | P4 |
|---:|---:|---:|---:|---:|
| 0 | 7 | 6 | 1 | 1 |

## P0 gaps

None after JP-OPS-01A reassessment.

### P0 reassessment notes

| Gap ID | Original | Revised | Rationale |
|--------|----------|---------|-----------|
| GAP-002 | P0 | **P3** | `mockUser` is display fallback only (`header.tsx:14-19`); `layout.tsx` loads session via `getDashboardSession()`. No auth/ownership/mutation impact. |
| GAP-010 | P0 | **P1** | Fixture KPIs require `NEXT_PUBLIC_DASHBOARD_MODE` defaulting to `preview` (`preview.ts:3-6`). Production misconfiguration risk, not inherent authority bypass. |

## P1 gaps (with evidence)

| ID | Surface | Evidence |
|----|---------|----------|
| GAP-001 | dashboard | `routes/api-dashboard.php` GET-only; `read-only-service.ts`; Blade `admin/*`/`staff/*` mutations |
| GAP-003 | agent | `routes/agent.php:44-57`, `AgentStaffPolicy`, `AgentStaffTest.php` |
| GAP-006 | customer | `routes/customer.php:39`, `BookingCancellationController`, `CustomerBookingOwnershipTest.php` |
| GAP-009 | supplier | `IatiFlightSupplierAdapter.php`, `IatiAuditDocsCommand` |
| GAP-010 | dashboard | `data-source.ts:5-19`, `preview.ts:3-6`, env gates |
| GAP-012 | agent | `routes/agent.php:59-63`, `AgentBookingCreationTest.php` |
| GAP-013 | background | `routes/console.php`, `config/queue.php` |

## P2 gaps

GAP-004, GAP-005, GAP-007, GAP-011, GAP-014 — see JSON for full evidence blocks.

### GAP-015 (JP-OPS-02 partial closure)

| Field | Value |
|-------|-------|
| ID | GAP-015 |
| Severity | P2 |
| Status | **PARTIALLY_CLOSED** (JP-OPS-02) |
| Provider contract | `App\Contracts\Auth\LoginOtpChannelProvider` prepared |
| Live production channel | **EXTERNAL_DEPENDENCY_BLOCKED** / runtime pending |
| Demo OTP patch | **Preserved** — `OTP_DEMO_*` flags unchanged; removal not part of JP-OPS-02 |
| Evidence | `config/ota_otp_demo.php`, `DemoFixedLoginOtpGate.php`, `login.otp.*` routes |

## P3 / P4

| ID | Severity | Summary |
|----|----------|---------|
| GAP-002 | P3 | Dashboard chrome mock fallback when session null |
| GAP-008 | P4 | Seat selection intentionally unavailable |

## JP-OPS-03 closures (2026-08-02)

| ID | Status | Notes |
|----|--------|-------|
| GAP-006 | **CLOSED** | JSON cancellation POST + `BookingCancellationPanel`; request ≠ completed cancellation; live supplier cancel deferred |
| GAP-007 | **CLOSED** | `CustomerPortalTravelersPresenter` JSON + `/customer/travelers` Next CRUD; ownership enforced; list masks document numbers |

## JP-OPS-04 closures (2026-08-02)

| ID | Status | Notes |
|----|--------|-------|
| GAP-003 | **CLOSED** | `/agent/staff` Next + additive JSON; owner/staff RBAC; duplicate staff 409 |
| GAP-004 | **CLOSED** | `/agent/reports` Next + agency-scoped JSON summary |
| GAP-005 | **CLOSED** | `/agent/commissions` Next + owner-only JSON read |
| GAP-012 | **CLOSED** | `/agent/bookings/create` Next entry activates `AgentBookingContext`; search handoff only |

## JP-OPS-05 closures (2026-08-02)

| ID | Status | Notes |
|----|--------|-------|
| GAP-001 | **PARTIALLY_CLOSED** | Session/capabilities JSON + **6** fully Next-connected review mutations + **8** backend-only cancel/refund JSON; **145** deferred; denominator **159** |
| GAP-002 | **CLOSED** | Live mode uses unavailable session chrome; no `mockUser` fallback |
| GAP-010 | **CLOSED** | Live mode disables fixture KPI/RBAC authority; preview retains fixtures |

## JP-OPS-06 closures (2026-08-04)

| ID | Status | Notes |
|----|--------|-------|
| GAP-001 | **PARTIALLY_CLOSED** | **12** CONNECTED mutations (payment/deposit + execution); **8** backend-only review; **139** deferred; denominator **159** |
| Commission ticketing (JP-OPS-04 deferral) | **CLOSED** | Four `AgentCommissionLedgerTest` ticketing methods green |

## JP-OPS-07 closures (2026-08-04)

| ID | Status | Notes |
|----|--------|-------|
| GAP-001 | **PARTIALLY_CLOSED** | **50** CONNECTED; **0** BACKEND_WITHOUT_NEXT_BINDING; **109** named deferrals/intentional Blade; denominator **159** |

**Markdown/JSON consistency:** GAP-001/002/010 carry matching `jp_ops_05_status` in [`JP-OPS-01-GAP-REGISTER.json`](JP-OPS-01-GAP-REGISTER.json).
