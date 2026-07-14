# OTA Security Hardening Report

Generated: 2026-06-18T15:00:00+00:00

Generated: 2026-06-18T16:30:00+00:00

## F9Q final controlled PNR retry allowance after green readiness (OTA-DEVCP-F9Q)

| Control | Status |
|---------|--------|
| `sabre:allow-final-controlled-pnr-retry` | added locally — production confirm required for meta write |
| Allowance meta only | yes — no supplier HTTP on allowance command |
| F9P freshness re-check | required at allowance write and controlled create |
| Ticketing / cancel / public auto-PNR | unchanged disabled |

Detail: [`OTA_F9Q_FINAL_CONTROLLED_PNR_RETRY_ALLOWANCE_AFTER_GREEN_READINESS_REPORT.md`](OTA_F9Q_FINAL_CONTROLLED_PNR_RETRY_ALLOWANCE_AFTER_GREEN_READINESS_REPORT.md)

## F9P final controlled PNR readiness after strong linkage (OTA-DEVCP-F9P)

| Control | Status | Note |
|---------|--------|------|
| `SabreControlledPnrFinalReadinessDiagnostics` | added locally | Read-only final pre-PNR gate; 15 min freshness window |
| `sabre:controlled-pnr-final-readiness` | added locally | Production `--confirm=READONLY-CONTROLLED-PNR-FINAL-READINESS`; no HTTP/DB mutation |
| F9N final fresh re-run | added locally | After F9O when `final_refresh_required`; preserve/invalidate strong marker |
| Retry allowance | not enabled | `new_explicit_retry_approval_required` informational only |
| Public auto-PNR / ticketing / cancel | unchanged disabled | No enablement in F9P |

Detail: [`OTA_F9P_CONTROLLED_PNR_FINAL_READINESS_AFTER_STRONG_LINKAGE_REPORT.md`](OTA_F9P_CONTROLLED_PNR_FINAL_READINESS_AFTER_STRONG_LINKAGE_REPORT.md)

## F9O-R1 strong linkage apply gate alignment (OTA-DEVCP-F9O-R1)

| Control | Status | Note |
|---------|--------|------|
| Apply eligibility source of truth | fixed locally | F9O diagnostic; F9M lane not hard gate |
| Controlled stale window | added locally | `ota.controlled_strong_linkage_apply.max_minutes_after_fresh_context_apply` |
| Apply CLI diagnostic fields | added locally | `stale_context_risk_hard_blocker`, `f9o_diagnostic_recommended_lane` |
| Public auto-PNR / ticketing / cancel | unchanged disabled | No enablement in F9O-R1 |

Detail: [`OTA_F9O_R1_STRONG_LINKAGE_APPLY_GATE_ALIGNMENT_REPORT.md`](OTA_F9O_R1_STRONG_LINKAGE_APPLY_GATE_ALIGNMENT_REPORT.md)

## F9O strong BFM revalidation linkage before controlled PNR (OTA-DEVCP-F9O)

| Control | Status | Note |
|---------|--------|------|
| `SabreControlledPnrStrongRevalidationLinkageDiagnostics` | added locally | Read-only matrix; optional shop probe |
| `sabre:inspect-controlled-pnr-strong-revalidation-linkage` | added locally | Production confirm required |
| `SabreControlledStrongRevalidationLinkageApply` | added locally | Eligibility gates; snapshot linkage rebuild only |
| `sabre:controlled-apply-strong-revalidation-linkage` | added locally | Dry-run + production confirm for live apply |
| Raw supplier output | blocked | No payload/response/PII in CLI |
| Public auto-PNR / ticketing / cancel | unchanged disabled | No enablement in F9O |

Detail: [`OTA_F9O_SABRE_STRONG_BFM_REVALIDATION_LINKAGE_BEFORE_CONTROLLED_PNR_REPORT.md`](OTA_F9O_SABRE_STRONG_BFM_REVALIDATION_LINKAGE_BEFORE_CONTROLLED_PNR_REPORT.md)

## F9N controlled fresh context apply before PNR (OTA-DEVCP-F9N)

| Control | Status | Note |
|---------|--------|------|
| `SabreControlledFreshPnrContextApply` | added locally | Eligibility gates; safe meta only |
| `sabre:controlled-apply-fresh-pnr-context` | added locally | Dry-run + production confirm for live apply |
| F9M `--reference` lookup | fixed locally | Queries `booking_reference` column |
| Offer refresh apply path | reused C3 service | No PNR/ticket/cancel HTTP |
| Public auto-PNR / ticketing / cancel | unchanged disabled | No enablement in F9N |

Detail: [`OTA_F9N_SABRE_CONTROLLED_FRESH_CONTEXT_APPLY_BEFORE_PNR_REPORT.md`](OTA_F9N_SABRE_CONTROLLED_FRESH_CONTEXT_APPLY_BEFORE_PNR_REPORT.md)

## F9L controlled retry recovery after F9J pre-HTTP schema fix (OTA-DEVCP-F9L)

| Control | Status | Note |
|---------|--------|------|
| F9L schema recovery gate | added locally | Narrow bypass; exact confirm + one-shot meta |
| F9J accounting diagnostics | enhanced locally | Read-only CLI; no raw payload/PII |
| Public auto-PNR / ticketing / cancel | unchanged disabled | No enablement in F9L |

Detail: [`OTA_F9L_CONTROLLED_RETRY_RECOVERY_AFTER_F9J_PREHTTP_SCHEMA_FIX_REPORT.md`](OTA_F9L_CONTROLLED_RETRY_RECOVERY_AFTER_F9J_PREHTTP_SCHEMA_FIX_REPORT.md)

## F9K CPNR AirPrice ValidatingCarrier schema compatibility (OTA-DEVCP-F9K)

| Component | Status | Notes |
|-----------|--------|-------|
| `SabreCpnrIatiWireSchemaValidator` | added locally | Pre-HTTP schema gate; safe pointer/message only |
| IATI VC wire path | fixed locally | FlightQualifiers VendorPrefs; no PricingQualifiers ValidatingCarrier |
| F9J schema-failure accounting | enhanced locally | Does not fully consume allowance without host ApplicationResults |
| CLI schema diagnostics | enhanced locally | `cpnr_schema_validation_*` on inspect + controlled-create |
| Public auto-PNR / ticketing / cancel | unchanged disabled | No enablement in F9K |

Detail: [`OTA_F9K_SABRE_CPNR_AIRPRICE_VALIDATING_CARRIER_SCHEMA_COMPATIBILITY_REPORT.md`](OTA_F9K_SABRE_CPNR_AIRPRICE_VALIDATING_CARRIER_SCHEMA_COMPATIBILITY_REPORT.md)

## F9J controlled retry after clean AirPrice VC fix (OTA-DEVCP-F9J)

| Component | Status | Notes |
|-----------|--------|-------|
| `SabreControlledPnrRetryAfterAirpriceVcFixAllowanceGate` | added locally | F9F consumed + prior NO FARES + clean digest; exact controlled confirm only |
| `SupplierBookingPreflightGuard` F9J integration | enhanced locally | Second one-shot gate; no broad retry bypass |
| `SabreBookingService` F9J meta + safe_summary | enhanced locally | Digest enriched before preflight on controlled command |
| CLI F9J availability fields | enhanced locally | Dry-run + inspect payload digest |
| Public auto-PNR / ticketing / cancel | unchanged disabled | No enablement in F9J |

Detail: [`OTA_F9J_CONTROLLED_RETRY_AFTER_CLEAN_AIRPRICE_VALIDATING_CARRIER_FIX_REPORT.md`](OTA_F9J_CONTROLLED_RETRY_AFTER_CLEAN_AIRPRICE_VALIDATING_CARRIER_FIX_REPORT.md)

## F9I AirPrice ValidatingCarrier qualifier (OTA-DEVCP-F9I)

| Component | Status | Notes |
|-----------|--------|-------|
| `buildIatiLikeCpnrV24GdsWire` AirPrice VC | fixed locally | Controlled IATI-like lane; draft `validating_carrier` only |
| `SabrePassengerRecordsPayloadDigest` hard/warning | enhanced locally | Brand diagnostics; no raw body/PII |
| `sabre:inspect-controlled-pnr-payload-digest` | enhanced locally | New VC/brand/hard-risk fields |
| `sabre:controlled-create-pnr` dry-run/failure | enhanced locally | Payload digest summary fields |
| Public auto-PNR / ticketing / cancel | unchanged disabled | No enablement in F9I |

Detail: [`OTA_F9I_SABRE_AIRPRICE_VALIDATING_CARRIER_QUALIFIER_FIX_REPORT.md`](OTA_F9I_SABRE_AIRPRICE_VALIDATING_CARRIER_QUALIFIER_FIX_REPORT.md)

## F9M host sellability diagnostics (OTA-DEVCP-F9M)

| Component | Status | Notes |
|-----------|--------|-------|
| `SabreControlledPnrSellabilityDiagnostics` | added locally | Composes F9G/F9H + context matrices; lane classifier |
| `sabre:inspect-controlled-pnr-sellability` | added locally | Read-only; production confirm required |
| `--probe-fresh-revalidate` | added locally | Stricter confirm; fresh shop dry-run only; no DB write |
| Public auto-PNR / ticketing / cancel | unchanged disabled | No enablement in F9M |

Detail: [`OTA_F9M_SABRE_HOST_NO_FARES_RBD_CARRIER_SELLABILITY_DIAGNOSTICS_REPORT.md`](OTA_F9M_SABRE_HOST_NO_FARES_RBD_CARRIER_SELLABILITY_DIAGNOSTICS_REPORT.md)

## F9H Passenger Records payload digest (OTA-DEVCP-F9H)

| Component | Status | Notes |
|-----------|--------|-------|
| `SabrePassengerRecordsPayloadDigest` | added locally | Safe AirBook/AirPrice wire digest; context comparison; no raw body/PII |
| `SabreBookingService::inspectControlledPnrPayloadDigestForBooking` | added locally | Rebuilds controlled CPNR wire read-only |
| `sabre:inspect-controlled-pnr-payload-digest` | added locally | Read-only; production confirm required |
| `sabre:controlled-create-pnr` failure output | enhanced locally | Payload digest risk fields on failure |
| Public auto-PNR / ticketing / cancel | unchanged disabled | No enablement in F9H |

Detail: [`OTA_F9H_SABRE_ENHANCED_AIRBOOK_NO_FARES_RBD_CARRIER_DIAGNOSTICS_REPORT.md`](OTA_F9H_SABRE_ENHANCED_AIRBOOK_NO_FARES_RBD_CARRIER_DIAGNOSTICS_REPORT.md)

## F9G Passenger Records application error diagnostics (OTA-DEVCP-F9G)

| Component | Status | Notes |
|-----------|--------|-------|
| `SabrePassengerRecordsApplicationResultDigest` | added locally | Safe ApplicationResults extract; no raw body/PII |
| `SabreBookingClient` failure attach | enhanced locally | Digest on Incomplete/NotProcessed application failures |
| `SabreBookingService` meta persistence | enhanced locally | `meta.sabre_passenger_records_application_digest` + convenience keys |
| `sabre:inspect-controlled-pnr-application-error` | added locally | Read-only; production confirm required |
| `sabre:controlled-create-pnr` failure output | enhanced locally | Digest availability + first safe error |
| Public auto-PNR / ticketing / cancel | unchanged disabled | No enablement in F9G |

Detail: [`OTA_F9G_SABRE_PASSENGER_RECORDS_APPLICATION_ERROR_DIAGNOSTICS_REPORT.md`](OTA_F9G_SABRE_PASSENGER_RECORDS_APPLICATION_ERROR_DIAGNOSTICS_REPORT.md)

## F9F controlled retry after fare acceptance (OTA-DEVCP-F9F)

| Component | Status | Notes |
|-----------|--------|-------|
| `SabreControlledPnrRetryAllowanceGate` | added locally | One-shot bypass of preflight retry block after F9E |
| `SupplierBookingPreflightGuard` F9F integration | enhanced locally | Controlled context passed to `nonRetryableFailedAttempt` |
| `SabreBookingService` retry allowance meta | enhanced locally | Safe `meta.controlled_supplier_retry_allowance` on use |
| `sabre:controlled-create-pnr` retry diagnostics | enhanced locally | `controlled_supplier_retry_allowance_used` output |
| Public auto-PNR / ticketing / cancel | unchanged disabled | No enablement in F9F |

Detail: [`OTA_F9F_CONTROLLED_SUPPLIER_BOOKING_RETRY_AFTER_ACCEPTED_FARE_CHANGE_REPORT.md`](OTA_F9F_CONTROLLED_SUPPLIER_BOOKING_RETRY_AFTER_ACCEPTED_FARE_CHANGE_REPORT.md)

## F9E controlled fare acceptance (OTA-DEVCP-F9E)

| Component | Status | Notes |
|-----------|--------|-------|
| `SabreControlledPnrFareChangeAcceptance` | added locally | Meta-only acceptance + safe fingerprints |
| `sabre:accept-controlled-pnr-fare-change` | added locally | Exact confirm; no supplier HTTP |
| `SabreControlledPnrReadiness` fare gate | enhanced locally | Blocks until acceptance when refresh gate active |
| `SabreControlledPnrApprovalOverrideGate` | enhanced locally | Requires F9E when fare gate active |
| Public auto-PNR / ticketing / cancel | unchanged disabled | No enablement in F9E |

Detail: [`OTA_F9E_CONTROLLED_FARE_CHANGE_ACCEPTANCE_BEFORE_PNR_RETRY_REPORT.md`](OTA_F9E_CONTROLLED_FARE_CHANGE_ACCEPTANCE_BEFORE_PNR_RETRY_REPORT.md)

## F9D controlled defer override bridge (OTA-DEVCP-F9D)

| Component | Status | Notes |
|-----------|--------|-------|
| `SabreControlledPnrApprovalOverrideGate` | added locally | Narrow bypass of historical defer meta on controlled create only |
| `SabreBookingService::createSupplierBooking` controlled context | enhanced locally | 7th arg passes approval context to preflight |
| `sabre:controlled-create-pnr` diagnostics | enhanced locally | Override + historical defer fields in output |
| `SupplierConnectionService` null provider | patched locally | Dev CP Sabre snapshot fail-soft |
| Public auto-PNR / ticketing / cancel | unchanged disabled | No enablement in F9D |

Detail: [`OTA_F9D_CONTROLLED_PNR_APPROVAL_OVERRIDE_SERVICE_BRIDGE_REPORT.md`](OTA_F9D_CONTROLLED_PNR_APPROVAL_OVERRIDE_SERVICE_BRIDGE_REPORT.md)

## F9B controlled context recovery (OTA-DEVCP-F9B)

| Component | Status | Notes |
|-----------|--------|-------|
| `sabre:controlled-pnr-context` | added locally | Production `--confirm=READONLY-CONTROLLED-PNR-CONTEXT`; no HTTP/PII/raw payloads |
| `SabreControlledPnrContextDigest` | added locally | Classifies usable controlled-certified context; explicit freshness blockers |
| `SabreControlledPnrReadiness` bridge | enhanced locally | Legacy revalidation recovery; admin confirm ≠ operational impossibility |
| Inspect booking commands | unchanged | Local/testing protection preserved |
| Public auto-PNR / ticketing / cancel | unchanged disabled | No enablement in F9B |

Detail: [`OTA_F9B_SABRE_REVALIDATION_CONTEXT_RECOVERY_REPORT.md`](OTA_F9B_SABRE_REVALIDATION_CONTEXT_RECOVERY_REPORT.md)

## F9 controlled PNR readiness (OTA-DEVCP-F9)

| Component | Status | Notes |
|-----------|--------|-------|
| `sabre:controlled-pnr-readiness` | added locally | Production requires `--confirm=READONLY-CONTROLLED-PNR-READINESS`; no HTTP |
| `sabre:controlled-create-pnr` | added locally | Dry-run default; exact `CREATE-PNR-FOR-BOOKING-{id}` for live attempt |
| `SabreControlledPnrReadiness` | added locally | Normalized blockers; no raw payloads/PII |
| Admin POST guard | enhanced locally | Additive block on hard readiness codes |
| Public auto-PNR / ticketing / cancel | unchanged disabled | No enablement in F9 |

Detail: [`OTA_F9_CONTROLLED_SABRE_PNR_CREATION_READINESS_REPORT.md`](OTA_F9_CONTROLLED_SABRE_PNR_CREATION_READINESS_REPORT.md)

## F8 booking flow production QA (OTA-DEVCP-F8-BOOKING-FLOW-PRODUCTION-QA)

| Component | Status | Notes |
|-----------|--------|-------|
| `ota:smoke-live-routes` F8 extension | enhanced locally | Full READ-ONLY banner; validation POST only; booking count guard |
| Booking-flow route smoke | verified locally | No secret patterns in responses; guest invalid token → 403 |
| Runtime 500 patches | none required | Automated smoke found no new 500-risk paths |
| `APP_DEBUG` on live | **unsafe — ops action** | Set `APP_DEBUG=false` after F8 manual browser smoke; not changed in code |

Detail: [`OTA_F8_BOOKING_FLOW_PRODUCTION_QA_REPORT.md`](OTA_F8_BOOKING_FLOW_PRODUCTION_QA_REPORT.md)

## F7 production operations hardening (OTA-DEVCP-F7-PRODUCTION-OPERATIONS-HARDENING)

| Component | Status | Notes |
|-----------|--------|-------|
| `ota:production-readiness-audit` | added locally | READ-ONLY banner; no secret output; PASS/WARN/FAIL summary |
| Env / debug checks | verified locally | APP_DEBUG unsafe → FAIL in production env, WARN otherwise |
| Storage / log checks | verified locally | Writable paths + log size bucket + production.ERROR count |
| Sabre mutation flags | verified locally | yes/no only; no supplier HTTP |
| Scheduler/queue guidance | reported only | Cron + supervisor recommendations; not auto-applied |
| `APP_DEBUG` on live | **unsafe — ops action** | Set `APP_DEBUG=false` after F7/F8 manual browser smoke; not changed in code |

Detail: [`OTA_F7_PRODUCTION_OPERATIONS_HARDENING_REPORT.md`](OTA_F7_PRODUCTION_OPERATIONS_HARDENING_REPORT.md)

## F6 live smoke QA (OTA-DEVCP-F6-ADMIN-BOOKING-LIVE-SMOKE-QA)

| Component | Status | Notes |
|-----------|--------|-------|
| `ota:smoke-live-routes` | added locally | READ-ONLY banner; no supplier POST; secret pattern scan on responses |
| Dev CP / admin / public smoke | verified locally | 71 checks pass with `--seed`; 45 with `--guest-only` |
| Runtime 500 patches | none required | Automated smoke found no new 500-risk paths |
| `APP_DEBUG` on live | **unsafe — ops action** | Set `APP_DEBUG=false` after F6/F7 manual browser smoke; not changed in code |

Detail: [`OTA_F6_ADMIN_BOOKING_LIVE_SMOKE_QA_REPORT.md`](OTA_F6_ADMIN_BOOKING_LIVE_SMOKE_QA_REPORT.md)

## F5 Sabre diagnostic gap closure (OTA-DEVCP-F5-SABRE-DIAGNOSTIC-GAP-CLOSURE)

| Component | Status | Notes |
|-----------|--------|-------|
| `SensitiveDataRedactor` | enhanced locally | Added payment/card/session keys; fail-soft `redact()` / `sanitizeErrorMessage()` |
| `SupplierDiagnosticLogger` | enhanced locally | `safe_message` through `sanitizeErrorMessage()`; persist try/catch |
| `ota:audit-sabre-status` | enhanced locally | READ-ONLY classification; no raw tokens in export |
| Dev CP Sabre page | enhanced locally | No credentials; `live_supplier_call_attempted=false` |

Detail: [`OTA_F5_SABRE_DIAGNOSTIC_GAP_CLOSURE_REPORT.md`](OTA_F5_SABRE_DIAGNOSTIC_GAP_CLOSURE_REPORT.md)

## F4 production gap closure (OTA-DEVCP-F4-PRODUCTION-GAP-CLOSURE)

| Component | Status | Notes |
|-----------|--------|-------|
| Public route aliases | fixed locally | Smoke 404s closed via unnamed redirects |
| Admin booking Sabre panels | fixed locally | `pnrReadinessPanel` / `hostClassificationPanel` fail-soft |
| Sync PNR itinerary POST guard | fixed locally | `assertSyncPnrItineraryPostAllowed()` mirrors UI gate |
| Auth login notification fail-soft | verified + tested | `AuthLoginNotificationFailSoftTest` |
| `APP_DEBUG` on live | **unsafe — ops action** | Set `APP_DEBUG=false` after F4/F5 smoke; not changed in code |

Detail: [`OTA_F4_PRODUCTION_GAP_CLOSURE_REPORT.md`](OTA_F4_PRODUCTION_GAP_CLOSURE_REPORT.md)

## F2 crash-gap fixes (OTA-DEVCP-F2-PRODUCTION-CRASH-GAP-CLOSURE)

| Component | Status | Notes |
|-----------|--------|-------|
| `TurnstileVerifier` + `RESPONSE_FIELD` | fixed locally | Fail-open when Turnstile disabled; component gated by `isEnabled()` |
| `SensitiveDataRedactor` | fixed locally | Passwords, tokens, Authorization, Sabre credential keys, PII redacted in supplier diagnostics |
| `EmailTemplateRegistry` | fixed locally | Exhaustive `OtaNotificationEvent` ops entries + `default` match fallbacks |
| Auth security emails | fixed locally | `App\Services\Communication\AuthSecurityEmailNotificationService`; try/catch on login paths |
| `SecurityEventLogger` / `PlatformAuditLogger` | fixed locally | Append-only; DB failures logged, never break user flow |
| Qualified `bookings.created_at` | fixed locally | Reports/dashboard/booking list filters when fare joins present |

Detail: [`OTA_F2_PRODUCTION_CRASH_GAP_REPORT.md`](OTA_F2_PRODUCTION_CRASH_GAP_REPORT.md)

## Summary

| Classification | Count |
|----------------|------:|
| safe | 0 |
| needs change | 6 |
| unsafe | 0 |
| needs manual verification | 192 |

## Config hotspots

| Item | Value | Classification |
|------|-------|----------------|
| password reset expire (minutes) | 60 | safe |
| password reset throttle (seconds) | 60 | safe |
| APP_DEBUG | true | unsafe |
| CORS config file | absent (Laravel default) | needs manual verification |

## Code findings

| File | Line | Pattern | Classification | Note |
|------|-----:|---------|----------------|------|
| `resources/views/dashboard/admin/customers/show.blade.php` | 77 | raw_blade | needs manual verification | Unescaped Blade output |
| `resources/views/dashboard/admin/reports.blade.php` | 1121 | raw_blade | needs manual verification | Unescaped Blade output |
| `resources/views/dashboard/admin/reports.blade.php` | 1136 | raw_blade | needs manual verification | Unescaped Blade output |
| `resources/views/dashboard/admin/reports.blade.php` | 1249 | raw_blade | needs manual verification | Unescaped Blade output |
| `resources/views/dashboard/admin/settings/communications/template-preview.blade.php` | 76 | raw_blade | needs manual verification | Unescaped Blade output |
| `resources/views/emails/layouts/modern.blade.php` | 58 | raw_blade | needs manual verification | Unescaped Blade output |
| `resources/views/frontend/about.blade.php` | 30 | raw_blade | needs manual verification | Unescaped Blade output |
| `resources/views/frontend/checkout/partials/shell.blade.php` | 11 | raw_blade | needs manual verification | Unescaped Blade output |
| `resources/views/frontend/cms-pages/show.blade.php` | 45 | raw_blade | needs manual verification | Unescaped Blade output |
| `resources/views/frontend/home.blade.php` | 26 | raw_blade | needs manual verification | Unescaped Blade output |
| `resources/views/mobile/public/about.blade.php` | 28 | raw_blade | needs manual verification | Unescaped Blade output |
| `app/Console/Commands/DevcpBootstrapPlatformAdminCommand.php` | 74 | where_raw | needs manual verification | Raw WHERE clause |
| `app/Console/Commands/DeveloperControlPanelUserCommand.php` | 60 | where_raw | needs manual verification | Raw WHERE clause |
| `app/Console/Commands/OtaImportAirportsAirlinesCommand.php` | 272 | where_raw | needs manual verification | Raw WHERE clause |
| `app/Console/Commands/OtaImportAirportsAirlinesCommand.php` | 280 | where_raw | needs manual verification | Raw WHERE clause |
| `app/Console/Commands/OtaImportAirportsAirlinesCommand.php` | 293 | where_raw | needs manual verification | Raw WHERE clause |
| `app/Http/Controllers/Admin/AgentApplicationController.php` | 264 | where_raw | needs manual verification | Raw WHERE clause |
| `app/Http/Controllers/Agent/DashboardController.php` | 47 | where_raw | needs manual verification | Raw WHERE clause |
| `app/Http/Controllers/Agent/DashboardController.php` | 56 | where_raw | needs manual verification | Raw WHERE clause |
| `app/Http/Controllers/Developer/DeveloperAuthController.php` | 51 | where_raw | needs manual verification | Raw WHERE clause |
| `app/Http/Controllers/Frontend/AgentRegistrationController.php` | 75 | where_raw | needs manual verification | Raw WHERE clause |
| `app/Http/Controllers/Frontend/AgentRegistrationController.php` | 99 | where_raw | needs manual verification | Raw WHERE clause |
| `app/Http/Controllers/Frontend/AgentRegistrationController.php` | 110 | where_raw | needs manual verification | Raw WHERE clause |
| `app/Http/Requests/Admin/StoreSupplierConnectionRequest.php` | 30 | where_raw | needs manual verification | Raw WHERE clause |
| `app/Http/Requests/Admin/UpdateSupplierConnectionRequest.php` | 23 | where_raw | needs manual verification | Raw WHERE clause |
| `app/Http/Requests/Auth/LoginRequest.php` | 135 | where_raw | needs manual verification | Raw WHERE clause |
| `app/Http/Requests/Auth/LoginRequest.php` | 136 | where_raw | needs manual verification | Raw WHERE clause |
| `app/Models/Airline.php` | 44 | where_raw | needs manual verification | Raw WHERE clause |
| `app/Models/Airport.php` | 60 | where_raw | needs manual verification | Raw WHERE clause |
| `app/Models/Airport.php` | 83 | where_raw | needs manual verification | Raw WHERE clause |
| `app/Models/Airport.php` | 103 | where_raw | needs manual verification | Raw WHERE clause |
| `app/Models/Airport.php` | 104 | where_raw | needs manual verification | Raw WHERE clause |
| `app/Models/SupportTicket.php` | 210 | where_raw | needs manual verification | Raw WHERE clause |
| `app/Services/Communication/AuthSecurityEmailNotificationService.php` | 238 | where_raw | needs manual verification | Raw WHERE clause |
| `app/Services/Communication/AuthSecurityEmailNotificationService.php` | 239 | where_raw | needs manual verification | Raw WHERE clause |
| `app/Services/Finance/Ledger/LedgerQueryService.php` | 221 | where_raw | needs manual verification | Raw WHERE clause |
| `app/Services/Finance/MasterLedgerService.php` | 191 | where_raw | needs manual verification | Raw WHERE clause |
| `app/Services/GroupTicketing/GroupInventoryFacetService.php` | 214 | where_raw | needs manual verification | Raw WHERE clause |
| `app/Services/GroupTicketing/GroupInventorySearchService.php` | 49 | where_raw | needs manual verification | Raw WHERE clause |
| `app/Services/GroupTicketing/GroupInventorySearchService.php` | 63 | where_raw | needs manual verification | Raw WHERE clause |
| `app/Services/GroupTicketing/GroupInventorySearchService.php` | 124 | where_raw | needs manual verification | Raw WHERE clause |
| `app/Services/Marketing/AbandonedFlightSearchProcessor.php` | 166 | where_raw | needs manual verification | Raw WHERE clause |
| `app/Services/Promo/PromoCodeValidationService.php` | 29 | where_raw | needs manual verification | Raw WHERE clause |
| `app/Services/TravelData/AirlineBrandingService.php` | 50 | where_raw | needs manual verification | Raw WHERE clause |
| `app/Http/Controllers/Admin/AgentApplicationController.php` | 269 | db_raw | needs manual verification | Raw SQL expression |
| `app/Http/Controllers/Admin/AgentApplicationController.php` | 284 | db_raw | needs manual verification | Raw SQL expression |
| `app/Http/Controllers/Admin/AgentApplicationController.php` | 287 | db_raw | needs manual verification | Raw SQL expression |
| `app/Services/Communication/AdminReportMailerService.php` | 31 | db_raw | needs manual verification | Raw SQL expression |
| `app/Services/Communication/AdminReportMailerService.php` | 191 | db_raw | needs manual verification | Raw SQL expression |
| `app/Services/Communication/AdminReportMailerService.php` | 192 | db_raw | needs manual verification | Raw SQL expression |
| `app/Services/Communication/AdminReportMailerService.php` | 217 | db_raw | needs manual verification | Raw SQL expression |
| `app/Services/Customers/GuestCustomerService.php` | 118 | db_raw | needs manual verification | Raw SQL expression |
| `app/Services/Dashboard/AgencyDashboardService.php` | 730 | db_raw | needs manual verification | Raw SQL expression |
| `app/Services/Reports/BookingReportService.php` | 75 | db_raw | needs manual verification | Raw SQL expression |
| `app/Services/Reports/BookingReportService.php` | 329 | db_raw | needs manual verification | Raw SQL expression |
| `app/Services/Reports/BookingReportService.php` | 432 | db_raw | needs manual verification | Raw SQL expression |
| `app/Services/Reports/BookingReportService.php` | 1080 | db_raw | needs manual verification | Raw SQL expression |
| `app/Support/FlightSearch/AirlineDisplayNameResolver.php` | 96 | db_raw | needs manual verification | Raw SQL expression |
| `app/Support/FlightSearch/AirlineDisplayNameResolver.php` | 97 | db_raw | needs manual verification | Raw SQL expression |
| `app/Support/FlightSearch/FlightOfferDisplayPresenter.php` | 39 | db_raw | needs manual verification | Raw SQL expression |
| `app/Http/Controllers/Admin/AgencyAboutUsSettingsController.php` | 39 | request_all | needs change | Mass assignment risk — prefer validated() |
| `app/Http/Controllers/Admin/AgencyBrandingController.php` | 143 | request_all | needs change | Mass assignment risk — prefer validated() |
| `app/Http/Controllers/Admin/AgencyFooterSettingsController.php` | 44 | request_all | needs change | Mass assignment risk — prefer validated() |
| `app/Http/Controllers/Admin/AgencyHomepageController.php` | 72 | request_all | needs change | Mass assignment risk — prefer validated() |
| `app/Http/Controllers/Admin/AgencyHomepageController.php` | 74 | request_all | needs change | Mass assignment risk — prefer validated() |
| `app/Http/Controllers/Frontend/AgentRegistrationController.php` | 55 | request_all | needs change | Mass assignment risk — prefer validated() |
| `app/Console/Commands/DevcpBootstrapPlatformAdminCommand.php` | 82 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Console/Commands/DeveloperControlPanelUserCommand.php` | 68 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Console/Commands/OtaImportAirportsAirlinesCommand.php` | 259 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Http/Controllers/Admin/AgencyBrandingController.php` | 150 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Http/Controllers/Admin/AgentApplicationController.php` | 409 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Http/Controllers/Admin/AgentApplicationController.php` | 429 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Http/Controllers/Admin/BookingManagementController.php` | 527 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Http/Controllers/Admin/BookingManagementController.php` | 532 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Http/Controllers/Admin/BookingManagementController.php` | 581 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Http/Controllers/Admin/BookingManagementController.php` | 586 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Http/Controllers/Admin/MarkupRuleController.php` | 107 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Http/Controllers/Admin/UserManagementController.php` | 202 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Http/Controllers/Admin/UserManagementController.php` | 228 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Http/Controllers/Admin/UserManagementController.php` | 237 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Http/Controllers/Admin/UserManagementController.php` | 274 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Http/Controllers/Agent/AgentAgencyController.php` | 104 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Http/Controllers/Agent/AgentAgencyController.php` | 107 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Http/Controllers/Agent/AgentBookingController.php` | 206 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Http/Controllers/Agent/AgentStaffController.php` | 161 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Http/Controllers/Agent/AgentStaffController.php` | 183 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | 46 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Http/Controllers/Auth/ForcePasswordChangeController.php` | 41 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Http/Controllers/Auth/NewPasswordController.php` | 58 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Http/Controllers/Auth/SocialAuthController.php` | 202 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Http/Controllers/Auth/SocialAuthController.php` | 242 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Http/Controllers/Auth/SocialAuthController.php` | 244 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Http/Controllers/Developer/DeveloperAuthController.php` | 78 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Http/Controllers/Developer/DeveloperPasswordController.php` | 42 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Http/Controllers/Frontend/BookingController.php` | 397 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Http/Controllers/Frontend/BookingController.php` | 596 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Http/Controllers/Frontend/BookingController.php` | 1068 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Http/Controllers/Frontend/BookingController.php` | 1968 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Http/Controllers/Frontend/BookingController.php` | 3096 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Http/Middleware/EnsureAgencyContext.php` | 34 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Agencies/AgencyReconciliationService.php` | 240 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Agencies/AgencyReconciliationService.php` | 317 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Agencies/AgencyReconciliationService.php` | 396 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Agencies/AgencyReconciliationService.php` | 464 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Agents/AgentApplicationOnboardingService.php` | 54 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Agents/AgentApplicationOnboardingService.php` | 155 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Agents/AgentApplicationOnboardingService.php` | 186 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Agents/AgentCommissionService.php` | 143 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Agents/AgentCommissionService.php` | 163 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Bookings/BookingCancellationService.php` | 47 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Bookings/BookingCancellationService.php` | 75 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Bookings/BookingCancellationService.php` | 81 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Bookings/BookingCancellationService.php` | 103 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Bookings/BookingCancellationService.php` | 110 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Bookings/BookingCancellationService.php` | 279 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Bookings/BookingCancellationService.php` | 311 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Bookings/BookingCancellationService.php` | 329 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Bookings/BookingCancellationService.php` | 338 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Bookings/BookingCancellationService.php` | 344 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Bookings/BookingCancellationService.php` | 363 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Bookings/BookingCancellationService.php` | 470 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Bookings/BookingCancellationService.php` | 471 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Bookings/FareHoldService.php` | 165 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Bookings/FareHoldService.php` | 216 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Bookings/FareHoldService.php` | 228 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Bookings/FareHoldService.php` | 233 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Communication/AgencyCommunicationSettingsService.php` | 123 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Communication/AgencyCommunicationSettingsService.php` | 126 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Communication/BookingCommunicationService.php` | 654 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Communication/BookingCommunicationService.php` | 664 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Communication/BookingCommunicationService.php` | 674 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Communication/BookingCommunicationService.php` | 689 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Communication/BookingCommunicationService.php` | 701 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Communication/BookingCommunicationService.php` | 708 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Communication/BookingCommunicationService.php` | 1186 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Communication/BookingCommunicationService.php` | 1195 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Communication/BookingCommunicationService.php` | 1216 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Communication/BookingCommunicationService.php` | 1226 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Communication/BookingCommunicationService.php` | 1233 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Communication/OtaNotificationService.php` | 149 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Communication/OtaNotificationService.php` | 154 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Communication/OtaNotificationService.php` | 225 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Communication/OtaNotificationService.php` | 231 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Customer/GuestBookingAccessService.php` | 44 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Finance/Ledger/LedgerPostingService.php` | 112 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Finance/Ledger/LedgerReversalService.php` | 77 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Finance/Ledger/LedgerTransactionFactory.php` | 56 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Finance/Ledger/LedgerTransactionFactory.php` | 66 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Finance/Ledger/LedgerTransactionFactory.php` | 82 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Marketing/AbandonedFlightSearchEmailSender.php` | 93 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Marketing/AbandonedFlightSearchEmailSender.php` | 108 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Payments/BookingPaymentService.php` | 115 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Payments/BookingPaymentService.php` | 143 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Payments/BookingPaymentService.php` | 182 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Payments/BookingRefundService.php` | 80 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Payments/BookingRefundService.php` | 117 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Payments/BookingRefundService.php` | 159 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Payments/BookingRefundService.php` | 307 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Payments/BookingRefundService.php` | 309 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php` | 3822 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php` | 3951 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php` | 4010 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php` | 8002 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php` | 8074 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php` | 8132 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php` | 8185 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php` | 8238 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php` | 8294 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php` | 8356 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php` | 9783 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php` | 10016 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php` | 10059 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php` | 10406 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Suppliers/Sabre/Booking/SabreBookingService.php` | 10444 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Suppliers/SupplierBookingService.php` | 154 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Suppliers/SupplierBookingService.php` | 273 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Suppliers/SupplierBookingService.php` | 327 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Suppliers/SupplierBookingService.php` | 336 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Suppliers/TicketingService.php` | 170 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Suppliers/TicketingService.php` | 227 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Suppliers/TicketingService.php` | 236 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Suppliers/TicketingService.php` | 242 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Support/SupportTicketService.php` | 173 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Support/SupportTicketService.php` | 188 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Support/SupportTicketService.php` | 206 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Support/SupportTicketService.php` | 226 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Services/Support/SupportTicketService.php` | 232 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Support/Agencies/AgencyPrefixService.php` | 128 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Support/Agencies/AgencyStaffPermissionAssignment.php` | 124 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Support/Auth/GoogleOnboarding.php` | 171 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Support/Bookings/SabreBookingValidationManualRequestPolicy.php` | 118 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Support/Bookings/SabreBrandedFarePublicAutoPnrEligibility.php` | 219 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Support/Bookings/SabreOfferRefreshAcceptance.php` | 256 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Support/Bookings/SabreOperationalPnrReadiness.php` | 268 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Support/Bookings/SabrePreCheckoutSellabilityDryRun.php` | 78 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Support/Bookings/SabreSafeRefreshContext.php` | 248 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Support/Bookings/SabreVerifiedAutoPnrReadiness.php` | 300 | force_fill | needs manual verification | Ensure guarded attributes |
| `app/Support/Finance/OtaFinanceDemoScenario.php` | 204 | force_fill | needs manual verification | Ensure guarded attributes |
