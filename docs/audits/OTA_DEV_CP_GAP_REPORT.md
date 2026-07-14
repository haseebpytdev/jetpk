# OTA Dev CP Gap Report

Generated: 2026-06-18T16:30:00+00:00

## F9Q final controlled PNR retry allowance after green readiness (OTA-DEVCP-F9Q)

| Item | Status | Note |
|------|--------|------|
| Final retry allowance gate | added locally | `SabreControlledFinalPnrRetryAllowanceGate` |
| CLI allowance command | added locally | `sabre:allow-final-controlled-pnr-retry` |
| Allowance expiry config | added locally | `ota.controlled_final_pnr_retry_allowance.max_minutes` (default 15) |
| Controlled-create integration | added locally | Preflight + pre-HTTP `recordUsage` |
| Live PNR create | not enabled from Cursor | Manual SSH only after allowance + dry-run |

Detail: [`OTA_F9Q_FINAL_CONTROLLED_PNR_RETRY_ALLOWANCE_AFTER_GREEN_READINESS_REPORT.md`](OTA_F9Q_FINAL_CONTROLLED_PNR_RETRY_ALLOWANCE_AFTER_GREEN_READINESS_REPORT.md)

## F9P final controlled PNR readiness after strong linkage (OTA-DEVCP-F9P)

| Item | Status | Note |
|------|--------|------|
| Final readiness diagnostics | added locally | `SabreControlledPnrFinalReadinessDiagnostics` |
| CLI final readiness command | added locally | `sabre:controlled-pnr-final-readiness` |
| Final freshness window | added locally | 15 min default; blocker `final_refresh_required` |
| F9N final fresh re-run | added locally | After F9O when freshness expired; strong marker preserve/invalidate |
| Live PNR retry allowance | added locally | F9Q `sabre:allow-final-controlled-pnr-retry` after F9P green |

Detail: [`OTA_F9P_CONTROLLED_PNR_FINAL_READINESS_AFTER_STRONG_LINKAGE_REPORT.md`](OTA_F9P_CONTROLLED_PNR_FINAL_READINESS_AFTER_STRONG_LINKAGE_REPORT.md)

## F9O-R1 strong linkage apply gate alignment (OTA-DEVCP-F9O-R1)

| Item | Status | Note |
|------|--------|------|
| Apply gate vs F9O diagnostic mismatch | fixed locally | Booking 53 apply path unblocked when BFM matrix complete |
| Controlled stale hard blocker | added locally | 180 min window after F9N; F9M 10 min stale is warning only |
| Live PNR retry | not enabled | Strong linkage apply still does not grant retry allowance |

Detail: [`OTA_F9O_R1_STRONG_LINKAGE_APPLY_GATE_ALIGNMENT_REPORT.md`](OTA_F9O_R1_STRONG_LINKAGE_APPLY_GATE_ALIGNMENT_REPORT.md)

## F9O strong BFM revalidation linkage before controlled PNR (OTA-DEVCP-F9O)

| Item | Status | Note |
|------|--------|------|
| Strong linkage diagnostics | added locally | `SabreControlledPnrStrongRevalidationLinkageDiagnostics` |
| CLI inspect command | added locally | `sabre:inspect-controlled-pnr-strong-revalidation-linkage` |
| CLI apply command | added locally | `sabre:controlled-apply-strong-revalidation-linkage` |
| Shop probe (not true revalidate) | added locally | `--probe-revalidate`; `probe_type=shop_refresh_not_true_revalidate` |
| Booking 53 weak linkage after F9N | diagnosable locally | BFM matrix + apply path before next controlled PNR |
| Live PNR retry | not enabled | Strong linkage apply does not grant retry allowance |

Detail: [`OTA_F9O_SABRE_STRONG_BFM_REVALIDATION_LINKAGE_BEFORE_CONTROLLED_PNR_REPORT.md`](OTA_F9O_SABRE_STRONG_BFM_REVALIDATION_LINKAGE_BEFORE_CONTROLLED_PNR_REPORT.md)

## F9N controlled fresh context apply before PNR (OTA-DEVCP-F9N)

| Item | Status | Note |
|------|--------|------|
| Fresh context apply support | added locally | `SabreControlledFreshPnrContextApply` |
| CLI apply command | added locally | `sabre:controlled-apply-fresh-pnr-context` |
| F9M reference lookup fix | fixed locally | `--reference` → `booking_reference` |
| F9M same_rbd_list fix | fixed locally | Normalized list comparison in probe |
| Booking 53 stale context | unblocked locally | Apply path before next controlled PNR |
| Live PNR retry | not enabled | Read-only retry-approval flag only |

Detail: [`OTA_F9N_SABRE_CONTROLLED_FRESH_CONTEXT_APPLY_BEFORE_PNR_REPORT.md`](OTA_F9N_SABRE_CONTROLLED_FRESH_CONTEXT_APPLY_BEFORE_PNR_REPORT.md)

## F9M host sellability diagnostics (OTA-DEVCP-F9M)

| Item | Status | Note |
|------|--------|------|
| Sellability diagnostics class | added locally | `SabreControlledPnrSellabilityDiagnostics` |
| CLI inspect command | added locally | `sabre:inspect-controlled-pnr-sellability` |
| Optional fresh shop probe | added locally | Stricter production confirm; no DB mutation |
| Booking 53 host triage | enhanced locally | Lane classification A–G |
| Live PNR retry | not enabled | Read-only diagnostics only |

Detail: [`OTA_F9M_SABRE_HOST_NO_FARES_RBD_CARRIER_SELLABILITY_DIAGNOSTICS_REPORT.md`](OTA_F9M_SABRE_HOST_NO_FARES_RBD_CARRIER_SELLABILITY_DIAGNOSTICS_REPORT.md)

## F9L controlled retry recovery after F9J pre-HTTP schema fix (OTA-DEVCP-F9L)

| Item | Status | Note |
|------|--------|------|
| F9L schema recovery gate | added locally | One-shot after F9J pre-HTTP failure + F9K schema pass |
| F9J accounting CLI diagnostics | enhanced locally | `f9j_accounting_state`, `f9k_schema_recovery_*` |
| Booking 53 recovery | unblocked locally | Dry-run should show schema recovery available |

Detail: [`OTA_F9L_CONTROLLED_RETRY_RECOVERY_AFTER_F9J_PREHTTP_SCHEMA_FIX_REPORT.md`](OTA_F9L_CONTROLLED_RETRY_RECOVERY_AFTER_F9J_PREHTTP_SCHEMA_FIX_REPORT.md)

## F9K CPNR AirPrice ValidatingCarrier schema compatibility (OTA-DEVCP-F9K)

| Item | Status | Notes |
|------|--------|-------|
| IATI VC wire placement | fixed locally | `FlightQualifiers.VendorPrefs.Airline.Code`; forbidden under `PricingQualifiers` |
| Local CPNR schema validator | added locally | `SabreCpnrIatiWireSchemaValidator` pre-HTTP gate |
| F9J schema-failure accounting | fixed locally | Schema-only failure does not fully consume host retry |
| Digest + CLI schema fields | enhanced locally | `cpnr_schema_validation_*`; clean requires schema pass |
| Live PNR retry | narrow recovery only | Booking 53 after dry-run pass |

Detail: [`OTA_F9K_SABRE_CPNR_AIRPRICE_VALIDATING_CARRIER_SCHEMA_COMPATIBILITY_REPORT.md`](OTA_F9K_SABRE_CPNR_AIRPRICE_VALIDATING_CARRIER_SCHEMA_COMPATIBILITY_REPORT.md)

## F9J controlled retry after clean AirPrice VC fix (OTA-DEVCP-F9J)

| Item | Status | Notes |
|------|--------|-------|
| F9J retry gate | added locally | One-shot after F9F + NO FARES prior error + clean digest |
| Payload digest clean helpers | added locally | `isPostF9iCleanForControlledRetry` / `postF9iCleanBlockers` |
| Controlled create dry-run F9J availability | enhanced locally | No supplier HTTP; no meta mutation |
| Live PNR retry | narrow one-shot only | Ops single attempt after dry-run shows available |
| Full manual browser QA | deferred | Per sprint workflow |

Detail: [`OTA_F9J_CONTROLLED_RETRY_AFTER_CLEAN_AIRPRICE_VALIDATING_CARRIER_FIX_REPORT.md`](OTA_F9J_CONTROLLED_RETRY_AFTER_CLEAN_AIRPRICE_VALIDATING_CARRIER_FIX_REPORT.md)

## F9I AirPrice ValidatingCarrier qualifier (OTA-DEVCP-F9I)

| Item | Status | Notes |
|------|--------|-------|
| IATI-like CPNR AirPrice VC wire | fixed locally | `buildIatiLikeCpnrV24GdsWire` merges ValidatingCarrier from draft |
| Digest hard/warning split | added locally | `legacy_revalidation_signal_used` is warning-only |
| Brand consistency diagnostics | added locally | Context vs wire brand compare; no wire mutation |
| Controlled create dry-run digest | enhanced locally | Payload digest summary on dry-run when rebuild ok |
| Live PNR retry | not auto-enabled | Digest clean gate before ops retry |
| Full manual browser QA | deferred | Per sprint workflow |

Detail: [`OTA_F9I_SABRE_AIRPRICE_VALIDATING_CARRIER_QUALIFIER_FIX_REPORT.md`](OTA_F9I_SABRE_AIRPRICE_VALIDATING_CARRIER_QUALIFIER_FIX_REPORT.md)

## F9H Passenger Records payload digest / NO FARES/RBD/CARRIER (OTA-DEVCP-F9H)

| Item | Status | Notes |
|------|--------|-------|
| Payload digest inspect command | added locally | Read-only; rebuilds controlled wire; no supplier HTTP |
| AirBook/AirPrice structural digest | added locally | Context comparison + risk reason codes |
| Controlled create diagnostics | enhanced locally | Failure output includes payload digest slim fields |
| Live PNR retry | not auto-enabled | Inspect payload + application error before retry |
| Full manual browser QA | deferred | Per sprint workflow |

Detail: [`OTA_F9H_SABRE_ENHANCED_AIRBOOK_NO_FARES_RBD_CARRIER_DIAGNOSTICS_REPORT.md`](OTA_F9H_SABRE_ENHANCED_AIRBOOK_NO_FARES_RBD_CARRIER_DIAGNOSTICS_REPORT.md)

## F9G Passenger Records application error diagnostics (OTA-DEVCP-F9G)

| Item | Status | Notes |
|------|--------|-------|
| Application error inspect command | added locally | Read-only; no supplier HTTP |
| Booking meta digest | enhanced locally | Post-failure safe ApplicationResults summary |
| Controlled create diagnostics | enhanced locally | Failure output includes digest fields |
| Live PNR retry | not auto-enabled | Inspect before retry; no failure bypass |
| Full manual browser QA | deferred | Per sprint workflow |

Detail: [`OTA_F9G_SABRE_PASSENGER_RECORDS_APPLICATION_ERROR_DIAGNOSTICS_REPORT.md`](OTA_F9G_SABRE_PASSENGER_RECORDS_APPLICATION_ERROR_DIAGNOSTICS_REPORT.md)

## F9F controlled retry after fare acceptance (OTA-DEVCP-F9F)

| Item | Status | Notes |
|------|--------|-------|
| `SabreControlledPnrRetryAllowanceGate` | added locally | One-shot; exact controlled create confirm only |
| Preflight retry bypass | enhanced locally | Narrow F9F path; general retry protection preserved |
| Create command retry diagnostics | enhanced locally | Dry-run never sets allowance used |
| Live PNR create | not broadly enabled | Still F9C + F9E + exact confirm; one Sabre attempt per allowance |
| Full manual browser QA | deferred | Per sprint workflow |

Detail: [`OTA_F9F_CONTROLLED_SUPPLIER_BOOKING_RETRY_AFTER_ACCEPTED_FARE_CHANGE_REPORT.md`](OTA_F9F_CONTROLLED_SUPPLIER_BOOKING_RETRY_AFTER_ACCEPTED_FARE_CHANGE_REPORT.md)

## F9E controlled fare acceptance (OTA-DEVCP-F9E)

| Item | Status | Notes |
|------|--------|-------|
| `sabre:accept-controlled-pnr-fare-change` | added locally | Exact confirm; meta only; no supplier HTTP |
| Readiness fare gate | enhanced locally | Blocks until acceptance when refresh gate active |
| Controlled create fare diagnostics | enhanced locally | Historical fare flags + F9E status in dry-run |
| Live PNR create | not broadly enabled | Still requires F9C + F9E + exact create confirm |
| Full manual browser QA | deferred | Per sprint workflow |

Detail: [`OTA_F9E_CONTROLLED_FARE_CHANGE_ACCEPTANCE_BEFORE_PNR_RETRY_REPORT.md`](OTA_F9E_CONTROLLED_FARE_CHANGE_ACCEPTANCE_BEFORE_PNR_RETRY_REPORT.md)

## F9D controlled defer override (OTA-DEVCP-F9D)

| Item | Status | Notes |
|------|--------|-------|
| Controlled create service bridge | added locally | Preflight defer bypass via F9C approval + controlled context |
| Dev CP Sabre null-provider snapshot | patched locally | `SupplierConnectionService` fail-soft |
| Live PNR create | not broadly enabled | Same controlled command gate; defer no longer blocks approved path |
| Full manual browser QA | deferred | Per sprint workflow |

Detail: [`OTA_F9D_CONTROLLED_PNR_APPROVAL_OVERRIDE_SERVICE_BRIDGE_REPORT.md`](OTA_F9D_CONTROLLED_PNR_APPROVAL_OVERRIDE_SERVICE_BRIDGE_REPORT.md)

## F9B controlled context recovery (OTA-DEVCP-F9B)

| Item | Status | Notes |
|------|--------|-------|
| `sabre:controlled-pnr-context` | added locally | Production read-only confirm; safe digest only |
| Context digest / readiness bridge | added locally | Bookings 53/54 style path clears `missing_revalidation_context` when meta complete |
| Dev CP Sabre panel | updated locally | F9/F9B lane + context command |
| Live PNR create | not enabled | Unchanged F9 create shell |
| Full manual browser QA | deferred | Per sprint workflow |

Detail: [`OTA_F9B_SABRE_REVALIDATION_CONTEXT_RECOVERY_REPORT.md`](OTA_F9B_SABRE_REVALIDATION_CONTEXT_RECOVERY_REPORT.md)

## F9 controlled PNR readiness (OTA-DEVCP-F9)

| Item | Status | Notes |
|------|--------|-------|
| Dev CP Sabre controlled lane panel | added locally | Lane exists, confirm required, flags disabled |
| Route readiness expansion | added locally | supplier-booking + prepare-context routes |
| `sabre:controlled-pnr-readiness` | added locally | Read-only; listed on Dev CP Sabre page |
| Live PNR create | not enabled | Command shell only; `booking_live_call_enabled` gate |
| Full manual browser QA | deferred | Per sprint workflow |

Detail: [`OTA_F9_CONTROLLED_SABRE_PNR_CREATION_READINESS_REPORT.md`](OTA_F9_CONTROLLED_SABRE_PNR_CREATION_READINESS_REPORT.md)

## F8 booking flow production QA (OTA-DEVCP-F8-BOOKING-FLOW-PRODUCTION-QA)

| Item | Status | Notes |
|------|--------|-------|
| Booking-flow smoke extension | added locally | **`LiveRouteSmokeCatalog`** + **`BookingFlowSmokeSafetyOutput`** — F8 GET/validation POST targets |
| `ota:smoke-live-routes` F8 pass | verified locally | 66 guest / 95 full checks; READ-ONLY banner with booking/ticketing/auto-PNR/cancel flags |
| Public checkout / lookup / admin booking | verified locally | No 500-risk under extended smoke |
| Runtime 500 patches | none required | F1–F7 fail-soft sufficient |
| Full manual browser QA | deferred | Client live QA checklist in F8 report |
| `APP_DEBUG` on live | **needs manual verification** | Set `APP_DEBUG=false` after F8 smoke |

Detail: [`OTA_F8_BOOKING_FLOW_PRODUCTION_QA_REPORT.md`](OTA_F8_BOOKING_FLOW_PRODUCTION_QA_REPORT.md)

## F7 production operations hardening (OTA-DEVCP-F7-PRODUCTION-OPERATIONS-HARDENING)

| Item | Status | Notes |
|------|--------|-------|
| Production readiness audit command | added locally | **`ota:production-readiness-audit`** — READ-ONLY; env/cache/queue/mail/storage/logs/scheduler/backup checks |
| F1–F6 command availability | verified locally | `ota:smoke-live-routes`, `ota:audit-sabre-status`, `devcp:seed-default-packages` reported yes/no |
| Sabre mutation flags | verified locally | ticketing/auto-PNR/public auto-PNR/cancellation — yes/no only; no enablement |
| Scheduler/queue/mail guidance | reported only | Recommendations printed; no crontab/worker auto-config |
| Live docs folder check | reported only | `docs_present=yes/no`; manual removal on live if yes |
| `APP_DEBUG` on live | **needs manual verification** | Audit marks unsafe; set `APP_DEBUG=false` after F7/F8 smoke |

Detail: [`OTA_F7_PRODUCTION_OPERATIONS_HARDENING_REPORT.md`](OTA_F7_PRODUCTION_OPERATIONS_HARDENING_REPORT.md)

## F6 live smoke QA (OTA-DEVCP-F6-ADMIN-BOOKING-LIVE-SMOKE-QA)

| Item | Status | Notes |
|------|--------|-------|
| Dev CP logged-in smoke readiness | verified locally | All 11 Dev CP GET paths pass `ota:smoke-live-routes --seed`; 67 Developer tests pass |
| Companies nav / legacy redirect | verified | Nav hidden; `/dev/cp/companies` → users + status message |
| Admin booking smoke | verified locally | Show, preview JSON, supplier diagnostics — smoke + 8 BookingManagement tests |
| Public route smoke | verified locally | Guest pass: aliases, missing flight params fail safe (302/422) |
| `ota:smoke-live-routes` | added locally | READ-ONLY kernel smoke; `--guest-only` safe on production |
| Runtime 500 patches | none required | F1–F5 fail-soft sufficient under automated smoke |
| `APP_DEBUG` on live | **needs manual verification** | Recommend `APP_DEBUG=false` after F6/F7 browser smoke |

Detail: [`OTA_F6_ADMIN_BOOKING_LIVE_SMOKE_QA_REPORT.md`](OTA_F6_ADMIN_BOOKING_LIVE_SMOKE_QA_REPORT.md)

## F5 Sabre diagnostic gap closure (OTA-DEVCP-F5-SABRE-DIAGNOSTIC-GAP-CLOSURE)

| Item | Status | Notes |
|------|--------|-------|
| Dev CP Sabre status page | fixed locally | Expanded snapshot: primary connection, base host, auth keys, config flags, mutation policy, route readiness, warnings |
| `ota:audit-sabre-status` | fixed locally | READ-ONLY banner, redacted failures, `live_supplier_call_attempted=false` |
| Admin booking compact Sabre summary | fixed locally | `compactStatusPanel()` + 8-group table on supplier tab |
| Readiness reason normalization | fixed locally | **`SabreReadinessReasonPresenter`** |
| Sync POST guard messages | fixed locally | Normalized codes: ticketed/cancelled/missing PNR/connection |
| Redaction hardening | fixed locally | Payment/card keys; logger `safe_message` sanitize; fail-soft redact |

Detail: [`OTA_F5_SABRE_DIAGNOSTIC_GAP_CLOSURE_REPORT.md`](OTA_F5_SABRE_DIAGNOSTIC_GAP_CLOSURE_REPORT.md)

## F4 production gap closure (OTA-DEVCP-F4-PRODUCTION-GAP-CLOSURE)

| Item | Status | Notes |
|------|--------|-------|
| Public route aliases | fixed locally | `/password/forgot`, `/booking-lookup`, `/flights` → canonical paths |
| Admin booking fail-soft | fixed locally | Sabre panels try/catch; preview 422; sync POST guard + try/catch |
| Auth login notification regression | fixed locally | `AuthLoginNotificationFailSoftTest` |
| `APP_DEBUG` on live | **needs manual verification** | Recommend `APP_DEBUG=false` after F4/F5 smoke — do not auto-edit `.env` |

Detail: [`OTA_F4_PRODUCTION_GAP_CLOSURE_REPORT.md`](OTA_F4_PRODUCTION_GAP_CLOSURE_REPORT.md)

| Section | Route/Command | Status | Classification |
|---------|---------------|--------|----------------|
| Overview | `dev.cp.index` | implemented | safe |
| Companies (legacy redirect) | `dev.cp.companies.index` | implemented | safe |
| Platform admin users | `dev.cp.users.index` | implemented | safe |
| Module controls | `dev.cp.modules.index` | implemented | safe |
| Security events | `dev.cp.security-events.index` | implemented | safe |
| System health | `dev.cp.health` | implemented | safe |
| Sabre status | `dev.cp.sabre` | enhanced (F5) | safe — expanded snapshot, no live API |
| Group ticketing | `dev.cp.group-ticketing` | implemented | safe |
| Dashboard status | `dev.cp.dashboards` | implemented | safe |
| Deployment status | `dev.cp.deployment` | implemented | safe |
| Bootstrap command | `devcp:bootstrap-platform-admin` | implemented | safe |
| Forced password (web) | `password.force` | implemented | safe |
| Forced password (dev cp) | `dev.cp.password` | implemented | safe |
