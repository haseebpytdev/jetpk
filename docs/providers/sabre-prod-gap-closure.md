# Sabre Prod Gap Closure

Reference: fresh Binham/iati.pk working copy at `Binham/public_html/modules/flights/sabre/`.

Audit command: `php artisan sabre:prod-gap-audit`

## Capability matrix

| Capability | Binham reference | OTA implementation | Status |
|---|---|---|---|
| GDS revalidation | `SABRE_REVALIDATE_GDS_CHECKOUT()` → `/v4/shop/flights/revalidate` | `SabreGdsRevalidationService`, `SabreBookingService::runRevalidationBeforeBooking()` | complete |
| Multi-city revalidation | Multi ODI + RPH in helper revalidate builder | `SabreGdsRevalidationService::revalidateMulticityDraft()`, `sabre:gds-revalidate-multicity` | complete |
| GDS PNR create | `SABRE_CREATE_PNR()` → CPNR v2.4 | `SabreBookingService::createSupplierBooking()`, `sabre:gds-create-pnr-production` | complete (env/route gated) |
| Ticket issue | Binham hold-only (7TAW); OTA uses Enhanced Air Ticket | `SabreGdsTicketingService`, `sabre:gds-issue-ticket` | complete (env gated) |
| Ticket documents | `getBooking` + `flightTickets[]` | `SabreGdsTicketDocumentService`, `sabre:gds-ticket-documents` | complete |
| Void | Binham `cancelBooking` only | `SabreGdsVoidTicketService` → `voidFlightTickets` when configured | complete |
| Refund | Manual Red Workspace in `actions/refund.php` | `SabreGdsRefundTicketService` live or manual record | provider_unsupported_manual when live off |
| Cancel | `DELETE /v1/trip/orders/cancelBooking` | `SabreBookingCancelService`, `sabre:production-cancel-evidence` | complete |
| NDC reprice | `/v1/offers/repriceOrder` | `SabreNdcRepriceOrderService`, `sabre:ndc-reprice-order` | complete |
| NDC order change | `/v1/orders/change` acceptOffers | `SabreNdcOrderChangeService`, `sabre:ndc-order-change` | complete |
| NDC retrieve | `/v1/orders/view` + `/v1/ndc/orders/retrieve` | `SabreNdcOrderRetrieveService`, `sabre:ndc-retrieve-order` | complete |
| Multi-city search | `search.php` multi ODI | `SabreFlightSearchRequestBuilder`, `sabre:multicity-search-probe` | complete |

## Provider limitations (evidence-backed)

1. **Binham does not implement live GDS ticket issue** — PNR hold via CPNR/NDC order create only. OTA adds Enhanced Air Ticket REST behind env gates.
2. **Binham void/cancel both use `cancelBooking`** — not a dedicated ticket void API. OTA void uses configurable `voidFlightTickets` when tenant supports it.
3. **Binham refund is manual** — `actions/refund.php` records `refund_pending` only. OTA matches this when `SABRE_REFUND_LIVE_CALL_ENABLED=false`.

## Mutation command safety

All mutation commands default to dry-run. Live send requires:

- `--send`
- exact `--confirm=...` phrase
- relevant `SABRE_*` env flags enabled

## Evidence commands

```bash
php artisan sabre:prod-gap-audit
php artisan sabre:gds-revalidate --booking=ID --dry-run
php artisan sabre:gds-revalidate-multicity --fixture=two_od_lhe_dxb_ist --dry-run
php artisan sabre:multicity-search-probe
php artisan sabre:architecture-report
```
