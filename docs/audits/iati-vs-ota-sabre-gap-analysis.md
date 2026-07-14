# Iati_new vs OTA Sabre Gap Analysis

**Audit date:** 2026-06-08  
**Reference:** `Binham/Iati_new`  
**Target:** Laravel OTA (`C:\Users\khadi\ota`)  
**Mode:** Read-only comparison

---

## 1. Executive Summary

| Dimension | Iati_new | Our OTA | Verdict |
|-----------|----------|---------|---------|
| Overall maturity | Production GDS+NDC search, live PNR, live cancel HTTP | Strong architecture, gated live calls, inspect/cert tooling | OTA is **safer**; Iati is **more live-complete** |
| Sabre integration style | Monolithic `helper.php` + inline search | Layered services, DTOs, config-driven styles | OTA wins maintainability |
| Security | Critical debug/diagnostic exposure | Redaction, inspect gates, no public debug routes | OTA wins decisively |
| Live booking | Un gated production PNR | Gated by `SABRE_BOOKING_LIVE_CALL_ENABLED` | OTA safer; Iati faster to prod mistakes |
| Cancel/refund | Live cancel HTTP (fragile); refund DB-only | Portal workflow; cancel inspect-only | Mixed — Iati attempts supplier cancel; OTA has better process |
| NDC/BOTH | Native v5 shop + NDC order path | v5 supported; NDC booking less complete | Iati ahead on NDC live path |
| Diagnostics | Raw file logs | 36+ Artisan commands, `SupplierDiagnosticLog` | OTA wins |

**Main opportunity:** Build an OTA that matches Iati's **behavioral completeness** (multi-account BFM, NDC path, live cancel with proper gates) while keeping OTA's **safety architecture** (SabreContext, snapshots, classifiers, no public debug).

---

## 2. Project Structure Comparison

### Our OTA Equivalent Map

| Iati_new | Our OTA |
|----------|---------|
| `modules/flights/sabre/search.php` | `SabreClient::searchFlights()` + `SabreFlightSearchNormalizer` |
| `modules/flights/sabre/helper.php` | `SabreBookingService`, `SabreRevalidationPayloadBuilder`, `SabreBookingPayloadBuilder`, `SabrePnrItinerarySyncService` |
| `app/lib/flight-supplier-booking.php` | `SabreSupplierBookingAdapter` → `SabreBookingService::createSupplierBooking()` |
| `app/routes/flights/bookingRoutes.php` | `BookingController`, `BookingProviderRouter` |
| `sabre_accounts` table | `SupplierConnection` (encrypted credentials) + agency linkage |
| `bookings` table | `Booking` model + `meta` JSON + `SupplierBookingAttempt` |
| `api_tokens` | Laravel `Cache` (`sabre:token:connection:{id}`) |
| `NOTIFY` / email views | `OtaNotificationService`, `BookingCommunicationService`, Mailables |
| `diagnostic.php` | `sabre:*` Artisan commands + `SabreInspectGate` |
| `actions/cancel.php` | `BookingCancellationService` (portal) + `SabreCancelBookingInspectProbe` (inspect) |

### Data Flow (OTA)

```
FlightController → FlightSearchService → SabreFlightSupplierAdapter → SabreClient
  → SabreFlightSearchNormalizer → NormalizedFlightOfferData (+ sabre_shop_context, gir_archive)

BookingController::review → SabreBookingOfferRefreshService → SabreBookingService::createBooking
  → SabreRevalidationPayloadBuilder → SabreBookingPayloadBuilder → SabreBookingClient
```

---

## 3. Sabre Endpoint Version Matrix

| Feature | Iati_new endpoint/version | OTA endpoint/version | Difference | Recommendation |
|---------|---------------------------|----------------------|------------|----------------|
| Auth | `/v2/auth/token` | `/v2/auth/token` (`config/suppliers.php`) | Same | Keep OTA `SabreEprEncodedCredentials` |
| BFM GDS shop | `/v4/offers/shop` | `/v4/offers/shop` (default `SABRE_SHOP_PATH`) | Same | OTA: add account-type selector like Iati |
| BFM NDC shop | `/v5/offers/shop` | `/v5/offers/shop` (config override) | Iati auto-selects by account type | OTA: `SabreContext.distribution_channel` drives v4/v5 |
| Legacy shop | `/v4/shop/flights` (diagnostic only) | Not used in production path | Iati legacy only | Do not port |
| Revalidate | `/v4/shop/flights/revalidate` only | Same default + `/v4/offers/shop/revalidate`, `/v5/offers/shop/revalidate` (cert compare) | OTA has more endpoint options | Keep OTA multi-endpoint cert; production single certified path |
| NDC reprice | `/v1/offers/repriceOrder` | **Not implemented** for live | Gap | Phase J: controlled NDC reprice |
| GDS PNR | `/v2.4.0/passenger/records?mode=create` | `/v2.5.0/passenger/records?mode=create` (default certified) + v2.4 IATI style (opt-in) | OTA on v2.5 default | Keep v2.5; IATI v2.4 as certified alternate only |
| Trip Orders create | Not primary for GDS | `/v1/trip/orders/createBooking` (config default path) | OTA dual-path | Continue CPNR-first for certified GDS |
| NDC order | `/v1/offers/price` + `/v1/orders/create` | Partial / gated | **Gap** | Phase J |
| getBooking | `/v1/trip/orders/getBooking` | Same — `SabrePnrItinerarySyncService` | OTA sanitizes snapshot | OTA ahead on safe sync |
| cancelBooking | Live `DELETE /v1/trip/orders/cancelBooking` | Inspect-only (`SabreCancelBookingInspectProbe`) | Iati live; OTA cert-only | Phase G: gated live cancel after retrieve |
| Refund/void API | None (DB workflow) | None (portal `BookingRefundService`) | Similar manual posture | Phase H: manual workflow + templates |
| Ticketing | Implicit via PNR issue | `issueTicket()` → `pending_implementation` | Both manual-heavy | Keep disabled until certified |

---

## 4. PCC / Account Context Matrix

| Context field | Iati_new | OTA | Risk | Recommended OTA design |
|---------------|----------|-----|------|------------------------|
| Auth PCC | Per `sabre_accounts` row | Per `SupplierConnection.credentials.pcc` | Low | `SabreContext.authPcc` |
| POS.Source.PseudoCityCode | Search/booking uses account PCC | `SabreFlightSearchRequestBuilder` | Low | Same object as auth PCC unless contract requires split |
| targetCity | Not observed in search payload | Not in default builder | Needs manual confirmation | Add only if Sabre contract requires |
| Multi-account search | Loops all active accounts with airline rules | Typically single connection per agency search | Iati richer | OTA: optional multi-connection fan-out behind feature flag |
| Account handoff at booking | `booking_data.account_id` | `supplier_connection_id` on offer | Low | Persist `SabreContext` on booking snapshot |
| Cancel PCC | **Legacy `modules` only** | Inspect uses booking's connection | **Iati High risk** | OTA must use booking's `SupplierConnection` |
| NDC party PCC | In NDC order payloads (hardcoded agency meta) | Config `SABRE_AGENCY_*` | Medium | Env-driven agency block; never hardcode in payload builder |
| CERT vs PROD | `sabre_accounts.env` + `modules.dev_mode` | `SupplierConnection` environment + `SABRE_BASE_URL` | Low | Explicit `SabreContext.environment` on every attempt |
| Token cache key | `sabre_{accountId}_{pcc}_{env}` | `sabre:token:connection:{id}` | Low | OTA pattern is cleaner |

---

## 5. Search / BFM Matrix

| Feature | Iati_new | OTA | Gap | Priority |
|---------|----------|-----|-----|----------|
| v4/v5 auto-select by GDS/NDC/BOTH | Yes (`search.php`) | Manual via `SABRE_SHOP_PATH` | **Gap** | P1 |
| DataSources NDC/ATPCO/LCC toggles | Per account type | In `SabreFlightSearchRequestBuilder` | Partial parity | P1 |
| Multi-account parallel search | Yes | Single connection typical | Gap | P2 |
| Airline include/exclude per account | `sabre_accounts.airlines` + `rule_type` | Not equivalent | Gap | P2 |
| Branded fares | Extensive inline parsing | `branded_fares` on DTO + probe flag | OTA adequate | P3 |
| Search audit log | `logs_searches` | `SupplierDiagnosticLog` | OTA adequate | — |
| Result cache | Client/session + `logs_bookings` | DB search sessions + UUID `search_id` | OTA ahead | — |
| Debug fare exposure | `?sabre_debug=1` → file | Admin-only `debug_fares` non-prod | OTA ahead | — |
| BFM version fallback | None (skip account) | Certified route selector (no fallback chains) | OTA safer | — |
| RequestType 100ITINS | Yes | Configurable in builder | Parity | — |

---

## 6. Normalization Matrix

| Field | Iati preserves? | OTA preserves? | Required for booking? | Recommendation |
|-------|-------------------|----------------|----------------------|----------------|
| itinerary id / ref | Yes (`booking_data`) | Yes (`offer_id`, `sabre_shop_context`) | Yes | Canonical `booking_snapshot.itinerary_ref` |
| leg_refs | Yes | Yes (`sabre_shop_context.leg_refs`) | Yes (revalidate) | Required |
| schedule_refs | Yes | Yes (`sabre_shop_context.schedule_refs`) | Yes (revalidate) | Required |
| pricing_information_index | Yes | Yes | Yes | Required |
| validating_carrier | Yes | Yes (`validating_carrier` on DTO) | Yes | Required |
| marketing/operating carrier | Yes | Yes (chains on DTO) | Yes | Required |
| RBD / booking class | Yes (per segment) | Yes (segments array) | Yes | Required |
| fare_basis | Yes | Yes (in raw_payload/linkage) | Yes | Required |
| fare family / brand | Yes (extensive) | Yes (`fare_family`, `branded_fares`) | Preferred | Keep Iati-level brand depth |
| baggage | Yes | Yes (`BaggageAllowanceData`) | Display + NDC | Required |
| sabre_bfm_gir_archive | No (reconstructed) | **Yes** (`SabreFlightSearchNormalizer`) | **OTA better** for revalidate | OTA advantage — do not drop |
| distribution_channel GDS/NDC | Account-inferred | Explicit on DTO | Yes | OTA ahead |
| mixed_carrier flag | Implicit | Explicit `mixed_carrier` | Yes | OTA ahead |
| account_id / connection | Yes | `supplier_connection_id` | Yes | Map to `SabreContext` |
| marriage group | Partial in booking_data | In payload builder from segments | Yes for CPNR | Verify per cert matrix |
| segment chronology repair | No | `SabreSegmentChronologyRepair` | Risky itineraries | OTA ahead |
| Frontend as booking authority | **Yes (risk)** | Snapshot + refresh gates | No | OTA must never trust UI alone |

### Critical fields Iati preserves better

- Per-account airline filtering metadata on results
- Deep brand/benefits/refundable inference from `priceClassDescriptions`
- Live NDC offer/offerItem IDs through checkout (when NDC path)

### Critical fields OTA preserves better

- `sabre_bfm_gir_archive` for revalidation linkage
- `sabre_shop_context` with pricing linkage handoff
- `distribution_channel`, `mixed_carrier`, segment order correction flags
- Typed `NormalizedFlightOfferData` + `FareBreakdownData`

### Fields missing in OTA before safer PNR

1. Automated v5 shop when connection is NDC/BOTH (config-only today)
2. NDC OfferPrice → order create live pipeline
3. Live cancel with retrieve-before/after (inspect exists; service stubbed)

### Fields missing in Iati that OTA should avoid depending on

1. Reconstructed-only booking_data without GIR archive
2. Cancel credential source mismatch (`modules` vs `sabre_accounts`)
3. Unredacted debug files as operational truth

---

## 7. Revalidation Comparison

### Sequence

| Step | Iati_new | OTA |
|------|----------|-----|
| When | Before `SABRE_CREATE_PNR` (GDS) | `revalidate_before_booking` + public `SabreBookingOfferRefreshService` before PNR |
| Payload source | DB `booking_data` segments | Booking snapshot + `sabre_shop_context` + GIR archive |
| Endpoint | `/v4/shop/flights/revalidate` only | Configurable; default same |
| Styles | Single IATI-like OTA envelope | Multiple styles incl. `iati_like_bfm_revalidate_v1` |
| Price change | Apply to preview; flag `price_revalidated` | `price_changed` + customer acceptance (`acceptUpdatedFare`) |
| Failure | Exception → error_response | `manual_review_required`, classifier, `SupplierBookingAttempt` |
| Stale offer | Logged; may proceed on some paths | `StaleSegmentRequiresNewSearch` notification event |

### Revalidation Failure Matrix

| Condition | Iati_new behavior | OTA behavior | Best OTA behavior | Priority |
|-----------|-------------------|--------------|-------------------|----------|
| Auth fail | Skip account / throw | Block booking; safe log | Block + `AUTH_INVALID_CLIENT` | P0 |
| NO FARES / 27131 | Log; may fail booking | Endpoint style compare; gatekeeper | Classify + admin alert | P1 |
| SEGMENT UC | HaltOnStatus on PNR; revalidate may fail earlier | `SabrePnrFailureClassifier` | Block PNR + customer message | P1 |
| PRICE_CHANGED | Auto-apply to preview | Require acceptance (public) | Keep OTA acceptance UX | — |
| Stale itinerary | Unclear auto-retry | Refresh service + stale event | New search prompt | P1 |
| Revalidate timeout | curl timeout 30s | Config timeouts + diagnostic | Retry once + `TIMEOUT` category | P2 |
| Missing linkage | Proceeds with stored booking_data | `allow_createbooking_without_revalidation` false default | Block live PNR | P0 |

---

## 8. Booking / PNR Matrix

| Feature | Iati_new | OTA | Gap | Recommended sprint |
|---------|----------|-----|-----|-------------------|
| Live GDS CPNR | Yes (`v2.4.0`) | Gated (`v2.5.0` default) | OTA more conservative | Phase E |
| Live NDC order | Yes | Not live-complete | **Gap** | Phase J |
| Revalidate before PNR | Yes (GDS) | Yes (config) | Parity | — |
| HaltOnStatus UC/HL/NO | Yes | In `SabreBookingPayloadBuilder` | Parity | — |
| Idempotency (duplicate PNR) | Early return if PNR exists | `explicitRetry` + attempt logging | OTA ahead | — |
| Localhost PNR block | Yes | Env/gate based | Similar | — |
| Host status classification | HK/UC arrays in helper | `SabrePnrFailureClassifier` | OTA more structured | — |
| Complex RT/MC PNR | Live (risky) | Deferred (`ComplexItineraryPolicy`) | OTA safer | Keep defer until certified |
| Mixed-carrier PNR | Attempted | Blocked/ manual review | OTA safer | Phase J |
| getBooking sync | Live on invoice | `SabrePnrItinerarySyncService` | OTA safer snapshot | Phase F |
| Payment → auto PNR | Yes (`payment-gateway.php`) | Gated manual/admin | OTA safer | — |

### Host Response Classification

| Status | Iati_new | OTA |
|--------|----------|-----|
| HK, SS, RR, LK, PK | Confirmed (`helper.php`) | Success path in classifier |
| UC, UN, US, UU, NO, HL | HaltOnStatus + cancelled list | `SabrePnrFailureClassifier` → manual review |
| NO FARES/RBD/CARRIER | Exception on PNR fail | `NO_FARES_RBD_CARRIER` safe category |

---

## 9. Cancellation / Refund Matrix

| Feature | Iati_new | OTA | Gap | Recommended sprint |
|---------|----------|-----|-----|-------------------|
| Customer cancel request | Portal route + NOTIFY | `BookingCancellationService::requestCancellation` | OTA ahead (workflow) | — |
| Admin approve/process | Partially in routes | Full approve/reject/process | OTA ahead | — |
| Sabre cancel HTTP | Live `cancelBooking` | `SabreBookingService::cancelBooking()` → `pending_implementation` | **Gap** | Phase G |
| Retrieve before cancel | **No** | Inspect probe uses getBooking | OTA ahead in design | Phase G |
| isCancelable check | **No** | `SabreTripOrderCancelContext` | OTA ahead | Phase G |
| Ticketed vs unticketed | Heuristic on JSON; same payload | Manual warning; no supplier call | OTA safer | Phase H |
| Refund API | DB-only | `BookingRefundService` portal | Similar | Phase H |
| Audit log | Partial (`error_log`) | `AuditLog` + `CommunicationLog` | OTA ahead | — |
| Post-cancel retrieve | **No** | Inspect verifies ineffectual cancel | OTA ahead | Phase G |

---

## 10. Email / Alert Matrix

| Trigger | Iati_new | OTA | Gap | Better template/action |
|---------|----------|-----|-----|------------------------|
| Booking confirmed | NOTIFY (often commented) / payment path | `BookingRequestReceived`, `SupplierBookingCreated` | OTA more complete | Keep OTA registry |
| Payment received | `triggerNotification('booking.payment_received')` | `PaymentVerified`, `PaymentRecorded` | Parity | — |
| PNR failed | `booking.issue_failed` | `SupplierBookingFailed` | OTA structured | Safe customer wording in registry |
| Revalidation failed | Not dedicated | `SupplierReadinessFailed`, manual review | OTA ahead | `StaleSegmentRequiresNewSearch` |
| Manual review | Implicit in failures | `BookingManualReviewRequired` | OTA ahead | — |
| Fare updated | Partial | `BookingFareUpdatedRequiresAcceptance` | OTA ahead | — |
| Cancellation requested | NOTIFY::cancellation | `CancellationRequested` | Parity | — |
| Cancellation processed | Partial | `CancellationStatusChanged` | OTA ahead | — |
| Refund | DB notes | `RefundRequested/Approved/Paid/Rejected` | OTA ahead | Phase I |
| Ticket issued | `booking.issued` | `TicketIssued` (manual) | Similar | — |
| Admin supplier alert | error_log + debug files | `OtaOperationalNotificationMail` | OTA ahead | Never email raw payloads |

---

## 11. Security Risk Matrix

| Risk | Iati_new | OTA | Severity | Recommendation |
|------|----------|-----|----------|----------------|
| Public diagnostic | `diagnostic.php` | None (Artisan only) | Critical / — | Never copy Iati pattern |
| Debug log files in web root | `uploads/sabre_*.txt` | `storage/logs` + redaction | Critical / Low | Keep OTA pattern |
| Hardcoded secrets in source | `test_booking.php` | None in Sabre layer | High / — | — |
| Credential storage | DB plaintext passwords | `encrypted:array` on model | High / Low | Keep SupplierConnection |
| CORS `*` on supplier API | Yes | Laravel CSRF on web routes | High / Low | — |
| Raw API in customer email | Possible via debug | Redacted operational templates | Medium / Low | — |
| Cancel wrong PCC | Yes | Connection from booking | High / — | Phase G uses booking connection |
| Live booking without gates | Production PNR on payment | Multi-env flags | High / Low | Keep OTA gates |
| OAuth token in DB table | `api_tokens` | Cache TTL | Medium / Low | — |
| Customer-facing raw errors | search.php file/line | Sanitized messages | Medium / Low | — |

---

## 12. Recommended Enhanced OTA Architecture

```
SabreContext
  ├── supplier_connection_id
  ├── environment (cert|prod)
  ├── auth_pcc, pos_pcc
  ├── distribution_channel (gds|ndc|both)
  ├── shop_endpoint_version (v4|v5)
  └── account_metadata (airline rules)

canonical_search_snapshot
  ├── search_id, criteria, passenger_mix
  ├── sabre_bfm_gir_archive (trimmed)
  └── sabre_shop_context per offer

normalized_result_schema (NormalizedFlightOfferData + extensions)
  └── booking_context_handoff from SabreFlightSearchNormalizer

booking_snapshot (on Booking.meta)
  ├── normalized_offer frozen at selection
  ├── revalidation_linkage
  ├── offer_refresh_acceptance
  └── pnr_itinerary_snapshot (post-sync)

Services:
  ├── RevalidationService (SabreBookingService::runRevalidationBeforeBooking)
  ├── HostErrorClassifier (SabrePnrFailureClassifier)
  ├── PnrCreationService (SabreBookingService::createBooking)
  ├── RetrieveService (SabrePnrItinerarySyncService)
  ├── CancellationService (portal + future SabreBookingService::cancelBooking)
  ├── NotificationService (OtaNotificationService)
  └── AdminDiagnosticService (SupplierDiagnosticLogger + readiness panel)
```

---

## 13. Our OTA Strengths (Do Not Regress)

1. Laravel service layering and testability
2. `SupplierConnection` encrypted credentials
3. `SensitiveDataRedactor` + `SupplierBookingAttempt` sanitization
4. `SabreInspectGate` — no destructive HTTP from web routes
5. Customer fare-change acceptance flow
6. `ComplexItineraryPolicy` — defers risky PNR
7. `sabre_bfm_gir_archive` preservation
8. Comprehensive Artisan certification toolkit
9. Portal cancellation/refund workflow with audit trail
10. `OtaNotificationEvent` enum-driven communications

---

## 14. Our OTA Gaps (vs Iati behavioral benchmark)

| Gap | Status | Risk if rushed |
|-----|--------|----------------|
| Auto v4/v5 shop by connection type | Partial | Wrong inventory channel |
| Multi-account Sabre search fan-out | Missing | Commercial parity only |
| Live NDC order create | Not production | NDC revenue |
| Live cancelBooking with retrieve | Inspect only | Ops manual load |
| Airline per-account routing rules | Missing | Markup/routing parity |
| IATI-like v2.4 CPNR as certified alt | Opt-in flag exists | Low if gated |

---

*End of gap analysis.*
