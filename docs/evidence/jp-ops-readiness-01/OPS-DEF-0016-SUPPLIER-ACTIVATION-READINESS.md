# OPS-DEF-0016 — Supplier Activation Readiness / Launch Contract

```
PROTECTED_MAIN_SHA=953f4ede2ec206aa6e01a1bae17dbcef48ea57aa
PRODUCTION_RUNTIME_SHA=922f15774ece177d8b3cc23207e98379b8a2755b
BUILD_ID=QClrSvr5W0V2GqllIeLKE
PROBED_AT_UTC=2026-10-15T19:46:41Z
LIVE_SUPPLIER_CALLS=0
PNR_MUTATIONS=0
TICKET_MUTATIONS=0
ENV_CHANGES=0
DB_MUTATIONS=0
OPS_DEF_0016_STATUS=CLOSED_LAUNCH_SCOPE_DEFINED
```

## Decision (Phase 23)

**CLOSED_LAUNCH_SCOPE_DEFINED** — not “all suppliers certified.”

Launch is intentionally limited to **Sabre GDS** with:

| Mode | Value |
|------|--------|
| CURRENT_LAUNCH_SUPPLIERS | Sabre GDS only (search + limited PNR); staff unticketed cancel armed |
| CURRENT_LAUNCH_BOOKING_MODE | AUTO limited certified paths + ADMIN_MANUAL PNR; live booking HTTP gated ON |
| CURRENT_LAUNCH_TICKETING_MODE | EXTERNAL_MANUAL / DISABLED in product (flags off; no LNIATA) |
| CURRENT_LAUNCH_CANCELLATION_MODE | ADMIN_MANUAL for unticketed (live cancel flags ON — high caution) |
| CURRENT_LAUNCH_REFUND_MODE | DISABLED |

Excluded from launch: Sabre NDC, PIA NDC (FROZEN), AirBlue, One API, IATI, Duffel, Al-Haider/Ameer group live mutation.

Remaining work is **activation/UAT** (explicit follow-ups), not undefined posture:

1. Controlled Sabre PNR live UAT (blocks full OJ-B2C/B2B book certification → OPS-DEF-0018)
2. Sabre ticketing LNIATA + flag enable + staff UAT (if auto/staff ticket desired)
3. AbhiPay → OPS-DEF-0017 (separate)
4. Optional: disable or document admin cancel-live risk until playbook exists

## Phase 0 — Health

| Gate | Value |
|------|--------|
| RUNTIME_SHA | 922f1577… |
| BUILD_ID | QClrSvr5W0V2GqllIeLKE |
| FAILED_JOBS | 1 (id=148 notifications-transactional QA SMTP) |
| PENDING_DEFAULT | 0 |
| DEFAULT_WORKER_RUNNING | NO |
| DISK | 48% |
| /up / /login /admin /agent | 200 / 200 / 200 / 302 / 200 |

## Phase 1 — Effective Sabre / supplier flags (no secrets)

See `_raw-ops-def-0016-flags-connections.json`.

Critical Sabre:

| Flag | Value |
|------|--------|
| SABRE_BOOKING_ENABLED | true |
| SABRE_BOOKING_LIVE_CALL_ENABLED | true |
| SABRE_PNR_CREATE_ENABLED (raw env) | unset/null → config null (service may treat unset as derived) |
| SABRE_ADMIN_MANUAL_PNR_ENABLED | true |
| SABRE_PNR_RETRIEVE_ENABLED | true |
| SABRE_UNTICKETED_CANCEL_ENABLED | true |
| SABRE_TICKETING_ENABLED | false |
| SABRE_TICKETING_LIVE_CALL_ENABLED | true (inert while ticketing_enabled false) |
| SABRE_PUBLIC_TICKETING_ENABLED | false |
| SABRE_CHECKOUT_AUTO_TICKETING_ENABLED | false |
| SABRE_VOID / REFUND (+ live) | all false |
| SABRE_GDS_GLOBAL_KILL_SWITCH | false |
| SABRE_NDC_ENABLED | false |
| CPNR connecting same-carrier (+ public) | true |
| SABRE_VERIFIED_MULTISEG_AUTO_PNR_ENABLED | false |
| AIRBLUE_OTA_V3_LIVE_ENABLED | false |
| ONE_API_LIVE_* | false |
| ALHAIDER_* | false |
| PIA / IATI / DUFFEL live | false |
| Group require-live public/reservation | true |
| SABRE_PRINTER_LNIATA_PRESENT | NO |

Also armed (from nested booleans): `cancel_enabled=true`, `cancel_live_call_enabled=true`, `admin_cancel_live_call_enabled=true`.

Drift vs Wave A 2026-10-14: same core pattern (book+live ON, ticketing OFF); this probe adds cancel-live / CPNR / NDC / LNIATA detail.

## Phase 2 — SupplierConnection inventory (metadata)

| ID | PROVIDER | ENV | STATUS | ACTIVE | CREDS | BASE_URL | Notes |
|----|----------|-----|--------|--------|-------|----------|-------|
| 1 | sabre | live | active | YES | YES | YES | Primary launch connection |
| 2 | pia_ndc | live | active | YES | YES | YES | **FROZEN — do not invoke** |
| 4 | sabre | sandbox | inactive | NO | YES | YES | CERT sandbox |
| 5 | smtp | live | active | YES | YES | NO | Not a flight supplier |
| 6 | al_haider | live | inactive | YES* | YES | YES | Group; booking flags off |
| 22 | airblue | sandbox | active | YES | YES | YES | Zapways TEST — not prod live |

No One API / IATI / Duffel / Ameer-e-Millat connection rows.

## Phase 3 — Platform modules

`platform_modules` table absent / empty probe result. Go-live checklist config: all `items.*.done=false` (9 items) — consistent with incomplete activation UI.

## Phases 4–8 — Sabre matrices (source + flags; no live calls)

### Search / certification

```
SABRE_GDS_SEARCH_ALLOWED=YES (kill switch off; branded fares search on)
CERTIFIED_ROUTE_SELECTOR_ENABLED=true
SEARCH_CERTIFICATION=ACTIVE_AND_CERTIFIED (historical + config; no fresh call)
SABRE_NDC_SEARCH=NOT_INTENDED (ndc.enabled=false)
```

### PNR by itinerary class (effective posture)

| Class | PUBLIC_CREATE_PNR | ADMIN_CREATE_PNR | LIVE_CALL_GATE | Notes |
|-------|-------------------|------------------|----------------|-------|
| ONE_WAY_DIRECT | LIMITED (certified selector) | YES (admin_manual) | ON | Active risk |
| ONE_WAY_CONNECTING_SAME_CARRIER | LIMITED (cpnr public flag) | YES | ON | auto multiseg OFF |
| ONE_WAY_CONNECTING_MIXED_CARRIER | NO | NO | n/a | blocked_mixed_carrier |
| RETURN_DIRECT / RETURN_CONNECTING | LIMITED | YES | ON | Needs class-specific UAT for 0018 |
| MULTI_CITY | NO | NO | OFF | complex_itinerary false |

### Ticketing

```
TICKETING_CODE_PRESENT=YES (SabreGdsTicketing*)
TICKETING_ENABLED=false
TICKETING_LIVE_CALL_ENABLED=true (blocked by ticketing_enabled)
PUBLIC / CHECKOUT_AUTO=false
PRINTER_LNIATA_PRESENT=NO
SABRE_TICKETING=CERTIFIED_BUT_DISABLED / BLOCKED_CONFIGURATION
```

### Cancel / void / refund

| Op | CODE | ENV | LIVE | Classification |
|----|------|-----|------|----------------|
| Unticketed cancel | YES | true | true | ACTIVE_LIMITED (admin) — do not call |
| Void | YES | false | false | CONFIGURED_BUT_DISABLED |
| Refund | YES | false | false | CONFIGURED_BUT_DISABLED |

### NDC vs GDS

Keep separate. NDC not intended for current launch.

## Phases 9–14 — Non-Sabre

| Supplier | Status |
|----------|--------|
| PIA NDC | **FROZEN** (governance; connection must not be used) |
| AirBlue | CONFIGURED_BUT_DISABLED (sandbox Zapways; live flag false) |
| One API | NOT_CONFIGURED |
| IATI | NOT_CONFIGURED / BLOCKED_EXTERNAL_OR_CONFIGURATION |
| Duffel | NOT_INTENDED_FOR_CURRENT_LAUNCH |
| Al-Haider | CONFIGURED_BUT_DISABLED |
| Ameer-e-Millat | NOT_CONFIGURED |

## Phase 16 — Manual vs automated (launch profile)

| Op | Mode |
|----|------|
| SEARCH | AUTO (Sabre GDS) |
| BOOK / PNR | AUTO limited certified + ADMIN_MANUAL; live call ON |
| TICKET | EXTERNAL_MANUAL / DISABLED in product |
| CANCEL | ADMIN_MANUAL (live armed) |
| VOID / REFUND | DISABLED |

## Phase 17 — UI claim consistency

No frontend/Blade matches for “Ticket issued automatically”, “Instant cancellation”, or “automatic refund” in a quick repo grep. Go-live checklist items all `done=false`.  
`UI_CAPABILITY_MISMATCHES=NONE_FOUND_IN_STATIC_SCAN` (browser copy not re-crawled).

## Phase 18 — State machine

With ticketing disabled, statuses can remain: search → booking pending → PNR created → payment pending/paid → **ticketing pending / manual review** → ticketed (manual). Cancellation requested → staff process. No impossible transitions identified for this limited profile. Auto-ticket claims would break truthfulness — already disabled.

## Phase 20 — Sabre activation sequence (future; not executed)

1. Search — **CURRENT=ON**
2. Revalidation — **CURRENT=live-capable**
3. Admin manual PNR — **CURRENT=ON**
4. Public direct PNR — **CURRENT=live-capable; UAT still required for 0018**
5. Connecting PNR — **flags ON; UAT required**
6. Staff ticketing — **PREREQ LNIATA + ticketing_enabled + cert**
7. Public/auto ticketing — **keep OFF**
8. Cancel — **already armed; playbook/UAT**
9. Void/refund — **keep OFF until separate cert**

## Phase 21 — Kill switches

| Path | Switch | Current |
|------|--------|---------|
| GDS lane | gds_global_kill_switch | false (armed) |
| Booking HTTP | booking_enabled / booking_live_call_enabled | true / true |
| Ticketing | ticketing_enabled / ticketing_live_call_enabled | false / true |
| Cancel | cancel_* / unticketed_cancel_enabled | true |
| Void/refund | void_* / refund_* | false |
| NDC | ndc.global_kill_switch / ndc.enabled | false / false |

`SUPPLIER_KILL_SWITCH_COVERAGE=PASS_FOR_SABRE_FAMILY` — mutation paths have env gates. Residual risk: cancel + booking live already ON.

## Phase 22 — Safe live-UAT plan (NOT executed)

Separate later plans only:

1. **PNR UAT** — one QA booking, one itinerary class, human approval, cancel plan  
2. **Ticketing UAT** — after LNIATA; staff-only; no public auto  
3. **Cancel UAT** — unticketed only; never bundle with void/refund  
4. **Void/refund UAT** — separate authorization  

## Phase 24 — OPS-DEF-0018 impact

| Journey | Supplier blocker |
|---------|------------------|
| OJ-B2C-001 | YES — live PNR not journey-certified; ticketing manual/off; payment OPS-DEF-0017 |
| OJ-B2B-OWN-001 | YES — same supplier + wallet/payment |
| OJ-B2B-MGR-001 | PARTIAL — capability matrix ok; live book not executed |
| OJ-B2B-SUP-001 | PARTIAL |
| OJ-STAFF-001 | YES for ticketing issuance; cancel path armed but not UAT'd |

```
OPS_DEF_0018_SUPPLIER_BLOCKERS=SABRE_PNR_LIVE_UAT;SABRE_TICKETING_DISABLED_OR_MANUAL;CANCEL_LIVE_PLAYBOOK
```

## Phase 25 — OPS-DEF-0017

```
OPS_DEF_0017_STILL_REQUIRED=YES
```

Payment activation remains separate; not conflated.

## Artifacts

- `SUPPLIER-LAUNCH-CONTRACT.csv`
- `SUPPLIER-ACTIVATION-DEPENDENCIES.csv`
- `_raw-ops-def-0016-flags-connections.json`
- `_raw-ops-def-0016-prod-probe.json`
- Updated `SUPPLIER-READINESS-MATRIX.csv`, ledgers, scorecard

## Closure basis

Explicit launch scope + classifications + kill switches + excluded frozen/disabled suppliers + UI static scan clean + no unknown flight supplier connections with live flags ON except Sabre (documented).  
Does **not** certify end-to-end journeys (OPS-DEF-0018) or AbhiPay (OPS-DEF-0017).
