# JP-INT-01 Integration Inventory (engineering, no secrets)

Private engineering matrix for JetPakistan Integrations Hub.

| Provider | Category | Runtime adapter | Config storage | Admin surface (post JP-INT-01) | Enable control | Secret storage | Health | Docs | RBAC | Audit |
|---|---|---|---|---|---|---|---|---|---|---|
| sabre | flights | SabreFlightSupplierAdapter | supplier_connections + SABRE_* env gates | Integrations + API Connections credentials | connection is_active + env safety gates | encrypted credentials | readiness-only Test Connection | developer.sabre.com / phase docs | integrations.* + suppliers | yes |
| iati | flights | IatiFlightSupplierAdapter | supplier_connections + IATI_* | Integrations + API Connections | connection is_active | encrypted | readiness-only | provider docs | integrations.* | yes |
| pia_ndc | flights | PiaNdcFlightSupplierAdapter | supplier_connections + PIA_NDC_* | Integrations + API Connections | connection is_active | encrypted | readiness-only | provider docs | integrations.* | yes |
| one_api | flights | OneApiFlightSupplierAdapter | supplier_connections + live flags | Integrations + API Connections | connection + live_* flags (default off) | encrypted | readiness-only | docs/integrations/one-api | integrations.* | yes |
| airblue | flights | AirBlueFlightSupplierAdapter | supplier_connections | Integrations + API Connections | connection is_active | encrypted | readiness-only | sparse | integrations.* | yes |
| duffel | flights | DuffelFlightSupplierAdapter | supplier_connections | Integrations + API Connections | connection is_active | encrypted | readiness-only | duffel.com/docs | integrations.* | yes |
| airline_direct | flights | AirlineDirectFlightSupplierAdapter (stub) | supplier_connections | Integrations (adapter stub) | connection is_active | encrypted placeholders | readiness-only | none | integrations.* | yes |
| al_haider | groups | AlHaiderClient | supplier_connections + ALHAIDER_* | Integrations + API Connections | env + connection | encrypted | readiness-only | none | integrations.* | yes |
| amadeus | flights | not installed | catalog only | Integrations draft/adapter_missing | n/a | n/a | n/a | n/a | view | n/a |
| travelport | flights | not installed | catalog only | Integrations draft/adapter_missing | n/a | n/a | n/a | n/a | view | n/a |
| abhipay | payments | AbhiPayGateway | payment_gateways (DB authoritative) | Integrations (primary) | is_active + checkout readiness | encrypted merchant_id/secret | GET /orders/by-rrn diagnostic | docs/payments/abhipay-integration.md | integrations.* | yes |
| hotelbeds | hotels | none | draft registry | Integrations draft | blocked | n/a | blocked | n/a | view | n/a |
| smtp_mail | messaging | Laravel mailer | env MAIL_* | Integrations draft | n/a | env | n/a | n/a | view | n/a |

## Env migration stance

- AbhiPay: **A** DB-backed authoritative (no merchant secrets in env).
- Flight suppliers: **C** DB credentials primary; env feature/safety gates retained and documented as temporary for high-risk controls.
- SMTP: **C** env fallback remains; draft only in hub.

## Notes

- No credential values are recorded in this document.
- Production cancellation / ticketing / host-send gates are unchanged by JP-INT-01.
