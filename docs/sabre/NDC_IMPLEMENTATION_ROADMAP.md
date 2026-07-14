# Sabre NDC Implementation Roadmap

Phase **SABRE-NDC-FOUNDATION-1** establishes lane separation, capability reporting, and
read-only diagnostics. Sabre GDS PNR/ticketing remains on hold (printer LNIATA). Sabre NDC
must not reuse GDS AirTicketRQ, LNIATA, PNR printer designation, or GDS cancel logic.

## Lane separation (enforced)

| Lane | Lifecycle | APIs | OTA handlers |
|------|-----------|------|--------------|
| **Sabre GDS** | PNR create → sync → GDS ticketing → GDS cancel | Trip Orders, Passenger Records, AirTicketRQ | `sabre_gds`, `SabreGdsTicketing*`, `SabreGdsCancel*` |
| **Sabre NDC** | Offer → price → order → fulfill → cancel | v5 shop, offer price, order create/retrieve/change | `sabre_ndc`, `SabreNdc*` |

Routing: `SupplierLifecycleContextResolver` → `HANDLER_SABRE_GDS` vs `HANDLER_SABRE_NDC`.
GDS ticketing/cancel block NDC channel bookings (`sabre_ndc_channel_use_ndc_services`).

## Current state (foundation audit)

### Implemented (scaffold / env-gated)

- Config: `config/suppliers.php` → `sabre.ndc.*` env flags (all default false)
- Connection channel: `SupplierConnection.settings.sabre_ndc_enabled` via `SabreSupplierChannelConfig`
- Services: `app/Services/Suppliers/Sabre/Ndc/*` (search, offer price, order create/retrieve/change, reprice)
- Diagnostics: `sabre:ndc-status`, `sabre:ndc-capability-report`, `sabre:ndc-connection-probe`
- Admin: NDC order panel in `AdminSabreGdsTicketingPanelsPresenter`
- Lifecycle: `SupplierLifecycleRouter` NDC handler (`lifecycle_mode: sabre_ndc_order`)

### Placeholder / not production-ready

- `SabreNdcOfferSearchService` — status only; no live shop HTTP
- Order create/retrieve/change/reprice — preview/gate scaffolds; live HTTP env-gated
- NDC ticketing/fulfillment — not implemented
- NDC cancel/void/refund — not implemented (`ndc_cancel` matrix = disabled)
- Public checkout NDC path — not certified (`SABRE_NDC_PUBLIC_ORDER_CREATE_ENABLED=false`)

### Credentials gap

Sabre NDC shares OAuth/EPR credentials on the existing `sabre` `SupplierConnection`
(admin-managed, encrypted). No separate NDC-only credential schema yet. Env-only secrets
(if any) should migrate to Admin Supplier Settings in a later pass.

## Phased roadmap

### Phase NDC-SEARCH-1

- NDC search request builder (v5 `/offers/shop`)
- Response parser → `NormalizedFlightOfferData` with `distribution_channel: ndc`
- Branded fares / fare families when returned
- Baggage display from NDC offer items
- Admin diagnostics (`sabre:ndc-capability-report`, shop probe behind env gate)
- **Gaps before test:** `SABRE_NDC_ENABLED`, `SABRE_NDC_SEARCH_ENABLED`, connection
  `sabre_ndc_enabled`, Sabre NDC entitlements on tenant, shop payload builder + HTTP in
  `SabreNdcOfferSearchService`

### Phase NDC-REVALIDATE-1

- Offer price / revalidation (`SabreNdcOfferPriceService` live path)
- Price change and expiry handling
- Safe `sabre_ndc_context` selected-offer storage on booking meta
- Block GDS `SabreGdsRevalidationService` for NDC channel bookings

### Phase NDC-ORDER-1

- Admin-approved order create (`sabre:ndc-create-order --send` + confirm phrase)
- Duplicate protection and attempt logging
- Order ID / owner code persistence in `meta.sabre_ndc_context`
- Retrieve/sync after create (`SabreNdcOrderRetrieveService`)

### Phase NDC-FULFILLMENT-1

- Payment / ticketing / fulfillment investigation (Sabre NDC order payment APIs)
- Admin-approved only; no public auto-ticketing
- Safe failure evidence; no AirTicketRQ or LNIATA

### Phase NDC-CANCEL-VOID-REFUND-1

- Only after order create + fulfillment stable
- Admin-approved NDC order cancel / void / refund
- Separate from `SabreGdsCancelService` and GDS void/refund

## Client / Sabre prerequisites

- Sabre REST OAuth credentials (EPR or client_id/secret) on active connection
- PCC with NDC offer shop / order entitlements (confirm with Sabre account manager)
- Cert/sandbox NDC endpoint access before production
- NDC airline list and PCC scope for target markets
- **Not required for foundation:** GDS printer LNIATA (GDS ticketing only)

## Verification commands

```bash
php artisan sabre:ndc-status
php artisan sabre:ndc-capability-report --json
php artisan sabre:ndc-connection-probe --json
php artisan test --filter=SabreNdc
php artisan test --filter=SupplierLifecycle
php artisan test --filter=SabreGds
```
