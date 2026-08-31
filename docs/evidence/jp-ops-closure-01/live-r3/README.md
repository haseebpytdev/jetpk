# JP-OPS-CLOSURE-01-R3 — Live evidence (sanitized)

## Authority

| Field | Value |
|---|---|
| Branch | `phase/jp-flight-perf-01` |
| START_LOCAL_HEAD | `7562736ab985c6f83c55be5a6406963b21703c47` |
| ENGINEERING (Google + tours) | `53ff7ba3`, `07a9c38f` |
| ENGINEERING (checkout resume) | `d95680d06c1cb7f0c25d541df53c960c2a16318d` |
| DEPLOYED_RUNTIME_SHA | `d95680d06c1cb7f0c25d541df53c960c2a16318d` |
| PUBLIC_BUILD_ID | `54EJE07vqRlgexjmoCRzE` |
| DASHBOARD_BUILD_ID | `vusuf0T5POTkwsLY8oUyO` (unchanged at r3b; rebuilt at r3 `07a9c38f`) |
| REMOTE_HEAD (frozen) | `1f12edef052da278f02b7ffeaf4e7a881c663ef9` |
| NO_PUSH | YES |

## Confirm boundary (do not invoke)

| Field | Value |
|---|---|
| FINAL_SUPPLIER_MUTATION_CTA | Review **Confirm booking** (`data-testid=review-continue-button`) |
| FINAL_MUTATION_ROUTE | `POST /booking/review` → `BookingController::processReviewSubmit` |
| FINAL_MUTATION_METHOD | POST |
| LIVE_SUPPLIER_CONFIRMATION | NOT_EXECUTED_BY_POLICY |

## Safe pre-confirm OPS

| Scenario | Result | Notes |
|---|---|---|
| OPS-02 Continue as Guest | PASS | Prompt + Continue as guest; Login link retained; draft flight/fare retained; no auto-login |
| OPS-03 Sign in & Continue | PASS | After `d95680d0` deploy, login with `redirect`/`checkout_return` resumes `/booking/passengers`; saved travelers available when session live |
| OPS-04 Default saved traveler | PASS | Alpha default autofill (title/name/DOB/nationality/doc); manual edit allowed; saved list labels unchanged after edit |
| OPS-05 Multi traveler picker | PASS | Alpha+Bravo listed; docs masked `QA****1111`/`QA****2222`; switch autofill works |
| OPS-20 A UI→Review | PASS | Authenticated Book Now → travelers → Review GET; fare PKR 100,515 / ECO SAVER / EK; **Confirm not clicked** |
| OPS-20 B local_qa | PASS | Booking `JPQAREJ9356` (`local_qa_inert`) payment rejection exercised; visible on Customer bookings |
| OPS-20 C live confirm | NOT_EXECUTED_BY_POLICY | |

## Payment rejection (local)

| Field | Value |
|---|---|
| PAYMENT_REJECTION | PASS |
| STATE | submitted → rejected |
| BOOKING_REQUIRES_PAYMENT_AFTER_REJECT | YES |
| TICKET_ISSUED | NO |
| PAYMENT_EXECUTED | NO |
| SUPPLIER_MUTATION_CALLS | 0 |

## Mail hygiene

| Field | Value |
|---|---|
| REAL_NON_QA_TARGETS_TOTAL | 3 (R2 classification) |
| REAL_NON_QA_SENT_COUNT | 0 (ops targets failed/skipped in R2 transport) |
| REAL_NON_QA_FAILED_COUNT | ≥1 |
| INCORRECT_RECIPIENT_ROUTING | NO |
| QA_RECIPIENT_ISOLATION_GAP | YES |
| R3_REAL_NON_QA_EMAILS_TARGETED | 4 (`payment_rejected` fan-out; transport failed; no further uncontrolled sends) |
| EMAIL_EXTERNAL_RECEIPT | EXTERNAL_OWNER_RECEIPT_PENDING |

### R2 non-QA recipient classification (masked)

| # | EVENT | RECIPIENT_ROLE | RECIPIENT_MASKED | WHY | TRANSPORT | CONTENT_QA_ID |
|---|---|---|---|---|---|---|
| 1 | ops lifecycle | internal ops | `a***@ota.local` | assigned/platform resolver | FAILED | NO |
| 2 | ops lifecycle | internal ops | `m***@gmail.com` | agency/support resolver | FAILED/UNKNOWN | NO |
| 3 | ops lifecycle | internal ops | `a***@yoursdomain.com` | legacy support resolver | FAILED/UNKNOWN | NO |

## Google Admin API Settings

| Gate | Result |
|---|---|
| GOOGLE_ADMIN_CONFIGURATION_UI | PASS (Integrations → Authentication → Google Sign-In / OAuth) |
| GOOGLE_SETUP_GUIDE | PASS |
| GOOGLE_CONFIG_STORAGE_SECURITY | PASS (blank secret preserves; no plaintext return) |
| GOOGLE_CONFIG_PERMISSION_SECURITY | PASS (Customer → `/access-denied`) |
| GOOGLE_CALLBACK_DISPLAY | PASS (`/auth/google/callback`) |
| GOOGLE_TEST_CONFIGURATION | PASS (documented completeness-only; no token exchange) |
| GOOGLE_PRODUCTION_CONFIG | NOT_CONFIGURED_BY_OWNER |
| GOOGLE_EXISTING/NEW_CUSTOMER | BLOCKED_EXTERNAL |
| GOOGLE_CONFIG_AUTHORITY | `SupplierConnection` google_oauth when active+complete else `config/services.google` / env |
| GOOGLE_CONFIG_PRECEDENCE | DB active complete → env fallback; empty DB does not wipe env |

## Dashboard wizards

| Role | Auto/manual | Permission-aware | Public leak |
|---|---|---|---|
| Customer | Manual restart PASS; skip persisted | N/A | NO |
| Agent | First-use / restart PASS; no Admin targets | YES | NO |
| Staff | “only highlights areas you can access”; 1 of 11 | PASS | NO |
| Admin | API Settings step present (5 of 7) | YES | NO |

Public home / groups / flight results / booking passengers / login: **VISIBLE_DASHBOARD_GUIDE=0**.

## Deployments

1. R3 activate `07a9c38f` — Public build `7CpMnF0Nvu9UgB60QIrCo`, Dashboard rebuilt, `ACTIVATE=PASS`, drift 0.
2. R3b activate `d95680d0` (login resume) — Public-only build `54EJE07vqRlgexjmoCRzE`, Dashboard skipped, `ACTIVATE=PASS`, drift 0, rollback packs=2.

## Safety counters

All live supplier create/cancel/ticket/void/refund/payment: **0 / NO**.

## Untracked sensitivity

| Item | Classification |
|---|---|
| `agent-wallet-full.yml` | Playwright a11y dump; potential secret material=NO; tracked=NO; staged=NO; keep local-only |
| `.pnpm-store/`, `dashboard/tmp/`, evidence raw | local artifacts; not staged |

## Owner dirty (never staged)

- `JetpkEmailPreviewCommand.php`
- `GoogleCustomerWelcomeMail.php`
- `group-reservation.blade.php`
