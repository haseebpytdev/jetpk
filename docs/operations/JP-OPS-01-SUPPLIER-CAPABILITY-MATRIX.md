# JP-OPS-01 Supplier Capability Matrix

**Phase:** JP-OPS-01 | **SHA:** `cfd65a76b448ec7fb77fddfb4995f290b5d841b3`

**Policy:** No live supplier calls during this audit. Classifications from adapter code, tests, and documented gates.

## Summary

| Supplier | Enabled | Auth | Search | Revalidation | Return Pairing | Booking | Ticketing | Cancel | Refund | Production Readiness |
|----------|:-------:|:----:|:------:|:------------:|:--------------:|:-------:|:---------:|:------:|:------:|:--------------------:|
| **Sabre** | config | ✓ | ✓ | ✓ | ✓ validated | ✓ | ✓ | ✓ gated | partial | **Operational** — cancellation gates preserved |
| **PIA NDC** | config | ✓ | ✓ | ✓ | NDC-specific | ✓ | ✓ | ✓ | partial | **Operational** (env-dependent) |
| **One API** (FlyJinnah/Air Arabia) | config | ✓ | ✓ | ✓ | partial | ✓ | ✓ | varies | varies | **Partial** |
| **AirBlue** | config | ✓ | ✓ | ✓ | partial | ✓ | ✓ | varies | varies | **Partial** |
| **Duffel** | config | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | **Operational** (if connection enabled) |
| **Airline Direct** | config | ✓ | ✓ | ✓ | partial | ✓ | ✓ | varies | varies | **Partial** |
| **IATI** | config | **✗ unresolved** | adapter exists | adapter exists | unknown | adapter | adapter | unknown | unknown | **NOT OPERATIONAL** |
| **Al-Haider Group** | config | ✓ | N/A (groups) | N/A | N/A | ✓ groups | manual | N/A | N/A | **Operational** for umrah groups |
| Amadeus / Travelport | enum only | — | — | — | — | — | — | — | — | **Not implemented** |

## Adapter locations

| Supplier | Search | Booking | Ticketing |
|----------|--------|---------|-----------|
| Sabre | `Adapters/SabreFlightSupplierAdapter` | `BookingAdapters/SabreSupplierBookingAdapter` | `TicketingAdapters/SabreSupplierTicketingAdapter` |
| PIA NDC | `Adapters/PiaNdcFlightSupplierAdapter` | `BookingAdapters/PiaNdcSupplierBookingAdapter` | ticketing adapter |
| One API | `Adapters/OneApiFlightSupplierAdapter` | `BookingAdapters/OneApiSupplierBookingAdapter` | — |
| IATI | `Adapters/IatiFlightSupplierAdapter` | `BookingAdapters/IatiSupplierBookingAdapter` | — |
| AirBlue | `Adapters/AirBlueFlightSupplierAdapter` | `BookingAdapters/AirBlueSupplierBookingAdapter` | — |
| Duffel | `Adapters/DuffelFlightSupplierAdapter` | `BookingAdapters/DuffelSupplierBookingAdapter` | `TicketingAdapters/DuffelSupplierTicketingAdapter` |
| Airline Direct | `Adapters/AirlineDirectFlightSupplierAdapter` | `BookingAdapters/AirlineDirectSupplierBookingAdapter` | `TicketingAdapters/AirlineDirectSupplierTicketingAdapter` |
| Al-Haider | — | `AlHaider/AlHaiderUmrahGroupService` | manual ops |

## Capability details

### Sabre
- Environment: PCC/credential via `SupplierConnection`
- Return pairing: `POST flights/select-return-combo` with supplier-validated linkage
- Cancellation: production gates preserved per project policy — **do not disable**
- Error normalization: `classifyAdapterFailure()` in adapter
- Tests: extensive Sabre phase tests in `tests/Feature/Sabre*`

### PIA NDC
- NDC order lifecycle: option PNR, ticket preview, void, eticket resend (admin routes)
- Sync routes: `sync-pia-ndc-booking`, `refresh-pia-ndc-status`

### IATI
- **Authentication unresolved** — must not mark operational
- Audit commands: `IatiAuditDocsCommand`, `IatiPublicSearchFlowAuditCommand` (non-live)

### Al-Haider (Group ticketing)
- Separate from HifzaTravel — JetPakistan `group-ticketing` module
- Inventory sync: `group-ticketing:release-expired` scheduler
- Frontend: `frontend/features/group-ticketing/`

## Webhooks / callbacks

| Type | Route | Supplier |
|------|-------|----------|
| Payment | `payments/abhipay/callback` | AbhiPay (not flight supplier) |
| Supplier webhooks | None named `webhook` in routes | — |

## Timeout / retry / idempotency

- Implemented per-adapter in `SupplierBookingService`, `OfferValidationService`
- Payment callback: throttle `abhipay-payment-callback`, server-authoritative Paid state
- Booking duplicate-submit: `throttle:public-booking-submit` on checkout routes

## Test coverage

| Supplier | Unit/Feature tests | Audit commands |
|----------|-------------------|----------------|
| Sabre | 50+ feature tests | `ota:audit-sabre-status` |
| IATI | audit commands | `ota:one-api-audit`, IATI audits |
| One API | — | `ota:one-api-audit` |
| AirBlue | — | `AirBlueAuditDocsCommand` |
| Groups | group-ticketing specs | `ota:audit-group-ticketing` |
