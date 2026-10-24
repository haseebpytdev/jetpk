# Dedicated AirBlue v3 TEST connection (design only)

## Decision

Prefer a **separate** `SupplierConnection` for Zapways OTA v3.0 TEST.
Do **not** protocol-switch Conn22.

## Draft credentials / fields (not persisted)

| Field | Value |
| --- | --- |
| provider | airblue |
| environment | sandbox |
| base_url | `https://otatest4.zapways.com/v3.0/OTAAPI.asmx` |
| api_channel | zapways_ota |
| protocol_version | 3.0 |
| certification_status | pending |
| search_certified | false / absent |
| client_id / client_key / agent_* | same authorized agency pattern as Conn22 |
| tls_cert_path / tls_key_path | same mTLS secret paths as Conn22 |
| ticketing_enabled | false until seat+ticket certification |
| booking_enabled | false until Booking D authorization |

## Search policy guarantees

- Pending v3 is **not** eligible for public search.
- Certified v3 may supersede v2 via protocol search rank (300 > 200).
- Conn22 remains the operational certified v2 lane until v3 certification closes.

## Lifecycle rule

A v3 booking must keep `protocol_version=3.0` from search through ticketing.
No hidden mixed-protocol customer bookings.
`AirBlueProtocolGuard` remains fail-closed on mismatch.

## Persistence

DB row creation is **out of scope** for this foundation loop.
