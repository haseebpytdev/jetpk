# OneAPI Architecture Inventory (A0)

DATE: 2026-11-02  
HEAD: `884ee8145fd2d58e90c090dfbb62fe394804a799` (+ cert date skew test fixes)

## Supplier key / vendor

- KEY=`one_api`
- VENDOR=`ISA / AccelAero`
- TRANSPORT=`REST auth/search` + `SOAP AAResWebServices` lifecycle

## Core runtime classes (present)

| Area | Class |
|------|-------|
| Auth | `App\Services\Suppliers\OneApi\Authentication\OneApiAuthService` |
| Search | `OneApiFlightSearchService`, `OneApiSearchRequestBuilder`, `OneApiSearchResponseParser` |
| SOAP | `LiveOneApiSoapTransport`, `FixtureOneApiSoapTransport`, `OneApiSoapSecurityBuilder`, `OneApiXmlParser` |
| Pricing | `OneApiPricingService`, `OneApiAirPriceRequestBuilder`, `OneApiAirPriceResponseParser` |
| Ancillaries | `OneApiAncillaryService` (+ seat/meal/baggage parsers) |
| Booking | `OneApiBookingService`, `OneApiSupplierBookingAdapter` |
| Retrieve | `OneApiRetrieveService`, `OneApiReservationReadOrchestrator`, `OneApiReadResponseParser` |
| Hold/payment | `OneApiHoldPaymentService`, `OneApiSupplierHoldPaymentOrchestrator` |
| Adapters | `OneApiFlightSupplierAdapter`, `OneApiSupplierTicketingAdapter` |
| Workflow | `OneApiWorkflowContext`, `OneApiWorkflowContextStore`, `OneApiWorkflowContextGuard` |
| Offer signing | `OneApiOfferTokenSigner`, `OneApiResponseNormalizer` |
| Config | `OneApiConfigResolver`, `OneApiReadinessService` |
| Gates | `OneApiMutationCommandGate`, `OneApiFixtureTransportScope` |
| Provider | `OneApiServiceProvider` |

## Connection model

- Encrypted `SupplierConnection` credentials via `config/supplier_credentials.php` → `one_api.fields`
- Required fields include username/password/agent_code/currency/POS + `rest_auth_url` / `rest_search_url` / `soap_url`
- Feature gates in `config/suppliers.php` → `one_api.live_*_enabled` (default false)
- Platform module gate: `one_api_supplier`

## Session continuity model

- Per-workflow cookie jar on SOAP transport (`JSESSIONID` from Set-Cookie)
- `TransactionIdentifier` parsed from SOAP responses into `OneApiWorkflowContext`
- Context store TTL via `workflow_context_ttl_seconds`
- Logging uses `SensitiveDataRedactor`; book interpreter masks transaction id

## Routes / checkout

- Public OneAPI checkout routes under `booking/one-api/*` (`OneApiCheckoutController`)
- Admin connection UI panel: `resources/views/.../one_api.blade.php`

## Isolation

- No `EjmiralAerost` / `ejmiral_aerost` classes on this branch
- Shared DTOs/enums only via existing JetPakistan domain abstractions
