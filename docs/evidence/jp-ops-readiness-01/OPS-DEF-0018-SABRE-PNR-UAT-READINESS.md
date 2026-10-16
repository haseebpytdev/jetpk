# OPS-DEF-0018 — Sabre PNR UAT Readiness (READ ONLY)

**Date:** 2026-10-16  
**PROTECTED_MAIN_SHA:** `045c3fe642c5061b0cb4fb46ad7948e9613c5cf1`  
**PRODUCTION_RUNTIME_SHA:** `922f15774ece177d8b3cc23207e98379b8a2755b`  
**BUILD_ID (authoritative task):** `QClrSvr5W0V2GqllIeLKE`

```
TECHNICAL_READINESS=READY
LIVE_SUPPLIER_CERTIFICATION=DEFERRED_UNTIL_GO_LIVE_WINDOW
SABRE_PNR_UAT_READINESS=READY_AWAITING_GO_LIVE_AUTHORIZATION
CONTROLLED_SABRE_PNR_UAT_REQUIRES_EXPLICIT_APPROVAL=YES
LIVE_PNR_REQUIRES_HUMAN_TRAVELER_APPROVAL=YES
LIVE_PNR_EXECUTION_REQUIRED_BEFORE_GO_LIVE=YES
LIVE_PNR_EXECUTION_ALLOWED_NOW=NO
LIVE_PNR_DEFERRAL_REASON=Supplier financial/fine exposure before production launch
GO_LIVE_GATE_SABRE_PNR_001=PENDING_FUTURE_GO_LIVE_WINDOW
OPS_DEF_0018_STATUS=PARTIAL_DEFERRED_LIVE_SABRE_PNR_UAT
FRESH_SEARCH_EXECUTED=NO
LIVE_SUPPLIER_CALLS=0
```

**Governance update (2026-10-16):** Live Sabre PNR creation/booking/cancel is **not authorized now** (supplier fine/ADM exposure before go-live). See `GO-LIVE-GATE-SABRE-PNR-001.md`. Do **not** mark this defect CLOSED or PASS.

This readiness document **did not** create a PNR, cancel, ticket, charge, or invoke any supplier HTTP client.

Do **not** reuse payment fixture `JPQAMPAY9B8E85CF` (OPS-DEF-0018 manual payment UAT) for future PNR UAT.

---

## Phase 0 — Health

| Check | Result |
|-------|--------|
| /up / /login /agent | 200 |
| /admin | 302 |
| FAILED_JOBS | 5 (untouched) |
| PENDING_DEFAULT | 0 |
| DEFAULT_WORKER_RUNNING | NO |
| Main vs runtime | main `5ce2d9ec` (docs) ≠ runtime app `922f1577` — intentional tooling/evidence drift; no redeploy in this loop |

---

## Phase 1 — Sabre config posture (no secrets)

Authoritative read-only source for same runtime SHA: `_raw-ops-def-0016-flags-connections.json` + `OPS-DEF-0016-SUPPLIER-ACTIVATION-READINESS.md`.

| Flag | Value |
|------|--------|
| SABRE_CONFIG_PRESENT | YES |
| SABRE_AUTH_CONFIGURED | YES (SupplierConnection id=1 live active; AUTH/CREDS/BASE_URL present — values not printed) |
| SABRE_BOOKING_ENABLED | true |
| SABRE_BOOKING_LIVE_CALL | true |
| SABRE_PNR_CREATE_ENABLED | false (public create gate; admin path still via admin_manual + booking_live_call) |
| SABRE_ADMIN_MANUAL_ENABLED | true |
| SABRE_CPNR_ENABLED | true (same-carrier connecting); verified_multiseg_auto=false |
| SABRE_RETURN_ENABLED | limited (certified route / same-carrier; live return PNR not re-UAT’d) |
| SABRE_MULTICITY_ENABLED | false / complex_itinerary false |
| SABRE_TICKETING_ENABLED | false |
| SABRE_LNIATA_CONFIGURED | NO |
| SABRE_CHECKOUT_AUTO_TICKETING | false |
| SABRE_PUBLIC_TICKETING | false |
| SABRE_CANCEL_ENABLED | true (+ cancel_live_call / admin_cancel_live — **HIGH CAUTION**) |
| SABRE_VOID_ENABLED | false |
| SABRE_REFUND_ENABLED | false |
| SABRE_NDC / PIA / AirBlue / IATI / Duffel / OneAPI live | out of launch / frozen / false |

---

## Phase 2 — Exact booking paths (source map; not invoked)

### Preferred: ADMIN MANUAL — DIRECT ONE-WAY

| Layer | Detail |
|-------|--------|
| ENTRY_ROUTE | `POST admin.bookings.supplier-booking` → `/admin/bookings/{booking}/supplier-booking` |
| CONTROLLER | `Admin\BookingManagementController::createSupplierBooking` |
| GATE / UI | `AdminBookingSupplierActions::build` → `can_create_pnr` |
| SERVICE / STRATEGY | `SupplierActionStrategyRegistry` → `SabreGdsCreatePnrStrategyAdapter` → `SabreGdsPnrCreateStrategySelector` |
| ADAPTER / CORE | `SabreBookingService::createPnrHold` (Passenger Records / Trip Orders create path) |
| LIVE_HTTP | Sabre REST booking create (gated by `booking_live_call_enabled`) |
| LOCAL_RECORDS | bookings (pnr/refs/status/meta), supplier_booking_attempts, supplier_bookings, passengers/contact, audit/communication/jobs as applicable |
| FAILURE_HANDLING | `SabrePnrFailureClassifier`; `retry_allowed` / blocker reasons surfaced to admin |
| RETRY_BEHAVIOR | Blocked when `has_pnr_or_reference`; otherwise retry may refresh offer; **no blind double submit** |

### PUBLIC / CUSTOMER checkout PNR

Certified-route selector + `booking_enabled` + `booking_live_call`; `pnr_create_enabled=false` narrows public auto-create. Higher surface than admin-manual — **not** preferred for first live UAT.

### RETURN / CONNECTING

Supported limited; connecting same-carrier CPNR flags true but `verified_multiseg_auto_pnr=false`. Mixed-carrier blocked. Prefer **not** first UAT class.

### ADMIN MANUAL PNR

`admin_manual_pnr_enabled=true` — intended controlled path for OPS-DEF-0018.

---

## Phase 3–4 — Itinerary class & search

```
UAT_ITINERARY_CLASS=DIRECT_ONE_WAY
WHY_LOWEST_RISK=Fewest segments; avoids CPNR/multiseg/return pairing; admin-manual gate; matches launch contract PNR_ONE_WAY_DIRECT
FRESH_SEARCH_EXECUTED=NO
```

Reason: prior OPS-DEF-0016 search certification is historical+config; no uncertainty requiring a new live search this loop. Future live UAT will need a **fresh** certified offer immediately before CreatePNR (offer freshness guards exist).

---

## Phase 5–6 — Traveler / contact

| Item | Assessment |
|------|------------|
| QA_TRAVELER_REQUIREMENTS | Legal name, DOB, gender, phone, email typical for Sabre Passenger Records / Trip Orders; passport/document SSR depends on route/carrier |
| REAL_IDENTITY_REQUIRED | **YES** for airline host acceptance (synthetic gibberish often rejected) |
| LIVE_PNR_REQUIRES_HUMAN_TRAVELER_APPROVAL | **YES** — human must provide approved QA traveler identity before live call |
| SUPPLIER_EMAIL_RISK | MEDIUM — airline/supplier may message SSR contact |
| SUPPLIER_SMS_RISK | MEDIUM/UNKNOWN |
| AIRLINE_NOTIFICATION_RISK | YES possible |
| QA_CONTACT_ALLOWED | Only dedicated QA phone/email approved by operator |

Do not invent traveler data in readiness. Do not use real customers.

---

## Phase 7 — Ticketing isolation

```
AUTO_TICKETING_AFTER_PNR=NO
TICKETING_ENABLED=NO
LNIATA_CONFIGURED=NO
CHECKOUT_AUTO_TICKETING=NO
PUBLIC_TICKETING=NO
TICKETING_QUEUE_AUTO_DISPATCH=NO (product ticketing disabled)
```

`SabreGdsMixedCarrierCertificationGate` blocks when `checkout_auto_ticketing_enabled=true` — currently false.  
**Not** `BLOCKED_AUTO_TICKETING_RISK`.

---

## Phase 8 — Payment isolation

```
PNR_REQUIRES_PAYMENT_MUTATION=NO
PNR_REQUIRES_WALLET_MUTATION=NO
PNR_REQUIRES_LEDGER_MUTATION=NO
```

Admin Sabre create eligibility can proceed without paid state for Sabre (`isSabre || paymentPaid` gate). Manual payment / AbhiPay not required for PNR UAT.

---

## Phase 9 — Expected mutation footprint (when later authorized)

| EXPECTED_LOCAL_MUTATION_TABLES | bookings, booking_passengers, booking_contacts, supplier_booking_attempts, supplier_bookings, audit_logs, communication_logs, jobs (possible), booking status/meta sidecars |
| EXPECTED_SUPPLIER_MUTATIONS | ONE Sabre create-booking / PNR hold HTTP |
| EXPECTED_QUEUE_SIDE_EFFECTS | Possible notification/mail jobs |
| NOT expected | booking_tickets, ticketing_attempts, payment_transactions, wallet, AbhiPay, ledger (unless separate payment path) |

---

## Phase 10 — Failure footprint (OPS-RISK-0007)

| Scenario | LOCAL_STATE | SUPPLIER_STATE | RECOVERY |
|----------|-------------|----------------|----------|
| Timeout before accept | attempt failed / no PNR | unknown/none | inspect; no blind retry |
| Timeout after accept | may lack PNR locally | **possible orphan PNR** | MANUAL_RECONCILIATION_REQUIRED=YES — retrieve/cancel playbook |
| Sabre reject | attempt logged; can_create may allow controlled retry | no PNR | follow classifier; refresh offer if required |
| Sabre success / local DB fail | inconsistent | PNR may exist | MANUAL_RECONCILIATION_REQUIRED=YES |
| Duplicate submit | `can_create_pnr=false` if PNR stored | second PNR risk if first succeeded but local missed | treat as reconciliation |
| Browser retry | same as duplicate | same | operator review |

---

## Phase 11 — Idempotency

| Item | Result |
|------|--------|
| PNR_CLIENT_IDEMPOTENCY_KEY | Lifecycle/run id used in controlled scenario tooling; admin UI relies on local PNR presence |
| LOCAL_DUPLICATE_GUARD | `has_pnr_or_reference` ⇒ `can_create_pnr=false`, `retry_allowed=false`, classification `SUPPLIER_PNR_BOOKED` |
| SUPPLIER_DUPLICATE_GUARD | Host-dependent; not fully under OTA control |
| RETRY_SAFE | Only when classifier allows + no local PNR |
| DOUBLE_SUBMIT_SAFE | **PARTIAL** — safe when local PNR persisted; **unsafe** if supplier accepted but local write failed |

Not elevating to `BLOCKED_DUPLICATE_PNR_RISK` for readiness, but live UAT must: single click, wait for response, no refresh-retry, immediate retrieve if ambiguous.

---

## Phase 12–13 — TTL / release

| Item | Value |
|------|--------|
| EXPECTED_PNR_TTL | Carrier/fare dependent; Sabre payload may include TTL remarks — **UNKNOWN exact hours without selected offer** |
| EXPECTED_TICKETING_DEADLINE | Fare rule / ADTK dependent — UNKNOWN until offer selected |
| UNTICKETED_PNR_AUTO_EXPIRES | UNKNOWN (do not assume) |
| MANUAL_CANCEL_REQUIRED | Likely YES for timely release |
| SAFE_RELEASE_PATH | Admin unticketed cancel playbook **after** separate authorization |
| LIVE_CANCEL_REQUIRED_AFTER_UAT | YES (plan) |
| CANCEL_API_AVAILABLE | YES (flags armed) |
| CANCEL_SEPARATE_AUTHORIZATION_REQUIRED | YES |

---

## Phase 14 — Future QA booking strategy

```
STRATEGY=CREATE_NEW_DEDICATED_LOCAL_QA_BOOKING_BEFORE_LIVE_PNR
DO_NOT_REUSE=JPQAMPAY9B8E85CF
```

Future booking meta must include: `qa_fixture=true`, `qa_type=sabre_pnr_uat`, `disposable=true`, `real_customer=false`, `created_for=OPS-DEF-0018`.  
**Not created in this readiness loop** (plan-only).

---

## Phase 15 — Future PASS criteria (live UAT)

SABRE_HTTP_REQUEST_SENT_ONCE · SABRE_ACCEPTED_ONCE · PNR_RETURNED · PNR_FORMAT_VALID · LOCAL_BOOKING_LINKED_ONCE · SUPPLIER_BOOKING_ROW_CREATED_ONCE · BOOKING_STATUS_CORRECT · NO_TICKET_CREATED · NO_TICKETING_ATTEMPT · NO_PAYMENT_TRANSACTION · NO_LEDGER_MUTATION · NO_WALLET_MUTATION · NO_DUPLICATE_PNR · QA_ONLY_CONTACTS · AUDIT_PRESENT · POST_UAT_RELEASE_PLAN_DOCUMENTED

---

## Phase 16–18 — Authorization & decision

```
CONTROLLED_SABRE_PNR_UAT_REQUIRES_EXPLICIT_APPROVAL=YES
LIVE_PNR_REQUIRES_HUMAN_TRAVELER_APPROVAL=YES
SABRE_PNR_UAT_READINESS=READY_AWAITING_LIVE_SUPPLIER_MUTATION_AUTHORIZATION
```

**STOP before live if:** no user auth; no approved traveler/contact; auto-ticket flags flip on; fare requires immediate ticket; duplicate/orphan ambiguity; cancel path refused; excluded supplier appears; payment mutation required; real customer data.

---

## Active blockers (initial launch)

1. **GO_LIVE_GATE_SABRE_PNR_001** — live Sabre PNR **deferred** until go-live window (technical readiness READY; execution NOT allowed now).  
2. AbhiPay remains deferred (not launch-blocking under MANUAL payment).  
3. Browser-depth residual for some OJ roles (non-supplier).  
4. Pricing read-side: `PASS_FOR_INITIAL_LAUNCH_PROFILE` (scorecard AMBER) — see `OPS-DEF-0018-PRICING-READINESS.md`.

```
CURRENT_NON_DEFERRED_P1_BLOCKERS=NONE
```

**NEXT_REQUIRED_ACTION:** Continue non-mutating readiness work. Execute `GO_LIVE_GATE_SABRE_PNR_001` only in the actual go-live window with explicit authorization. Do not spend loops waiting on Sabre live auth.
