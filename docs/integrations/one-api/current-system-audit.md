# One API — current system audit

Phase: ONE-API-FLYJINNAH-AIRARABIA-FULL-SUPPLIER-INTEGRATION-1

Read-only audit of existing JetPakistan supplier infrastructure that One API extends (no parallel stack).

## Contracts One API implements

| Stage | Contract | One API implementation |
|-------|----------|----------------------|
| Search | `App\Contracts\Suppliers\FlightSupplierInterface` | `OneApiFlightSupplierAdapter` → `OneApiFlightSearchService` |
| Revalidation / price | `validateOffer()` on adapter | `OneApiPricingService` via `OneApiFareRevalidationService` |
| Booking | `App\Contracts\Suppliers\SupplierBookingInterface` | `OneApiSupplierBookingAdapter` → `OneApiBookingRouterService` |
| Ticketing | `SupplierTicketingInterface` | No standalone ticket; e-ticket on book RS when paid |
| Retrieve | (service pattern) | `OneApiRetrieveService` — admin/reconcile/commands |
| Cancel | `BookingCancellationService` | **Not supported** — no vendor cancel API in docs |

## Registration paths (extended)

- `App\Enums\SupplierProvider::OneApi` (`one_api`)
- `App\Services\Suppliers\SupplierAdapterResolver` — search adapter
- `App\Services\Booking\BookingProviderRouter` — `OneApiBookingRouterService`
- `App\Services\Suppliers\SupplierBookingService` — booking adapter resolve
- `App\Services\Suppliers\TicketingService` — optional no-op / book-issued path
- `config/supplier_credentials.php` — connection field schema
- `config/ota-suppliers.php` — admin catalog card
- `config/suppliers.php` — `one_api` timeouts and live gates
- `config/ota.php` — public results supplier list when enabled
- `App\Support\Platform\PlatformModuleGate` — `one_api_supplier`
- `App\Support\Platform\PlatformModuleEnforcer` — search/book gates
- `App\Services\Suppliers\SupplierConnectionService::hasRequiredCredentialKeys`
- `App\Support\Suppliers\SupplierLifecycleCapabilities` — capability matrix
- `App\Support\Bookings\SupplierLifecycleContextResolver` — `HANDLER_ONE_API`

## Models and persistence (reused)

| Model | Use for One API |
|-------|-----------------|
| `SupplierConnection` | `credentials` encrypted:array; `settings`/`meta` for flags |
| `Booking` | `pnr`, `supplier_reference`, `meta.one_api_context` |
| `BookingHoldSession` | validated offer snapshot, hold expiry |
| `SupplierBooking` | supplier PNR, `raw_summary` |
| `SupplierBookingAttempt` | idempotency, redacted payloads, `reconciliation_required` |
| `BookingTicket` | e-ticket numbers from book RS |
| `Airline` | marketing/operating branding (G9, 3L, etc.) |

No duplicate One API bookings table in this phase.

## Orchestration (reused)

- Search fan-out: `App\Services\FlightSearch\FlightSearchService`
- Markup: `PricingRuleService` / `FlightPricingService`
- FX display: `LiveFxRateService`
- Checkout hold: `FareHoldService`, `OfferValidationService`
- Booking create: `BookingProviderRouter`
- Communication: `BookingCommunicationService` (existing once ticketed/paid)
- Redaction: `SensitiveDataRedactor`, `SupplierDiagnosticLogger`

## Reference implementations mirrored

| Concern | Reference |
|---------|-----------|
| REST auth + cache | `App\Services\Suppliers\Iati\IatiAuthService` |
| REST search + normalize | `IatiFlightSearchService`, `IatiResponseNormalizer` |
| SOAP transport | `App\Services\Suppliers\PiaNdc\PiaNdcClient` |
| Booking router | `IatiBookingRouterService`, `PiaNdcBookingRouterService` |

## Admin UI

- `App\Http\Controllers\Admin\SupplierConnectionController`
- `resources/views/dashboard/admin/api-settings/form.blade.php`
- `App\Support\Suppliers\SupplierCredentialFormPresenter`
- One API: readiness panel + safe test (auth/search only when live flag + `--live` pattern)

## Tests

- Fixtures: `tests/Fixtures/Suppliers/OneApi/`
- `Http::fake` for REST; injectable `OneApiSoapTransport` for SOAP
- Live probes: explicit `--live` + connection capability flags (Sabre gate pattern)

## Credential encryption

**Not a blocker.** `SupplierConnection` uses Laravel `encrypted:array` on `credentials` with masking in audits.

## Intentionally not modified

Sabre, PIA NDC, and IATI internal service logic — only registry/orchestrator match arms for `one_api`.
