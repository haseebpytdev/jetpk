# AirBlue Zapways OTA contract

Authoritative JetPakistan mapping for AirBlue direct integration (Zapways only).

## Version terminology

Three independent version concepts must not be confused:

| Concept | Example | Meaning |
| --- | --- | --- |
| Document revision | ZW-OTA API v2.06 / v3.00 | Supplier PDF revision |
| Wire protocol | `/v2.0/` or `/v3.0/` | HTTP path + XML namespace |
| RQ `Version` attribute | `1.04` | Request attribute on every RQ element |

## Supported protocol versions

| Protocol | Status | Endpoint (TEST) | Endpoint (LIVE) | Namespace |
| --- | --- | --- | --- | --- |
| 2.0 | Implemented; certification pending | `https://otatest4.zapways.com/v2.0/OTAAPI.asmx` | `https://ota4.zapways.com/v2.0/OTAAPI.asmx` | `http://zapways.com/air/ota/2.0` |
| 3.0 | Implemented (TEST); LIVE gated | `https://otatest4.zapways.com/v3.0/OTAAPI.asmx` | `https://ota4.zapways.com/v3.0/OTAAPI.asmx` | `http://zapways.com/air/ota/3.0` |

Missing `protocol_version` on a connection defaults to **2.0**.

## Connection model

Two logical `SupplierConnection` rows may coexist:

- AirBlue Zapways v2 (`protocol_version=2.0`)
- AirBlue Zapways v3 (`protocol_version=3.0`)

Both use `provider=airblue`, `api_channel=zapways_ota`. They may share the same JetPakistan TLS certificate/key paths. Credentials are not assumed interchangeable until issued by Zapways.

Search fan-out dedupes to one active AirBlue connection per search. **Uncertified v3 cannot displace certified v2.** Among certified candidates, ranking uses `search_priority`, then protocol version, then connection id. Certification is inferred from `search_certified`, `certification_status`, or healthy `last_test_status` (`air_shopping_success`, `ready_for_review`, `success`).

Booking/ticketing always follow the offer's stored `protocol_version` and `supplier_connection_id`. Cross-protocol booking is fail-closed.

## v3 SOAPAction policy

v3 SOAPActions are **not** inferred from the XML namespace. Only **Read** is supplier-documented:

| Environment | SOAPAction |
| --- | --- |
| TEST | `https://ota.qa.zapways.com/Read` |
| LIVE | `https://ota.zapways.com/Read` |

v2 Read continues to use the historically established `ota4` / `otatest4` hosts. Other v3 operations default to unresolved (`null`) until Zapways documents them or ops configures per-operation overrides in `config/suppliers.php`. Real calls without a configured SOAPAction fail closed with `missing_soap_action`.

## v3 request shapes (authoritative)

| Operation | Required hierarchy |
| --- | --- |
| AirSeatMap | `airSeatMapRQ` → POS → `SeatMapRequests/SeatMapRequest/FlightSegmentInfo` → `BookingReferenceID` |
| AirAncillaryItems | `airAncillaryItemsRQ` → POS → `AncillaryItemRequests/AncillaryItemRequest/FlightSegmentInfo` → `BookingReferenceID` |
| AirBookModify type 5 | `airBookModifyRQ` → POS → `AirBookModifyRQ@ModificationType=5/TravelerInfo/SpecialReqDetails/{SeatRequests,SpecialServiceRequests}` → `AirReservation/BookingReferenceID` |

Read and Cancel: `Instance` attribute is optional — omit when blank. Exchange cancel requires instance.

## TLS identity

One JetPakistan client certificate identity is intended for both protocols after Zapways registration:

- `tls_cert_path` — public/client certificate
- `tls_key_path` — matching private key

If `tls_key_path` is empty, `tls_cert_path` may be used as combined PEM fallback.

## Request element names (authoritative)

| Operation | SOAP wrapper | Inner RQ element |
| --- | --- | --- |
| Search | `AirLowFareSearch` | `airLowFareSearchRQ` |
| Book | `AirBook` | `airBookRQ` |
| Read | `Read` | `readRQ` |
| Cancel | `Cancel` | `cancelRQ` |
| Demand ticket | `AirDemandTicket` | `airDemandTicketRQ` |
| Book modify | `AirBookModify` | `airBookModifyRQ` |
| Seat map (v3) | `AirSeatMap` | `airSeatMapRQ` |
| Ancillary items (v3) | `AirAncillaryItems` | `airAncillaryItemsRQ` |

## v3-only capabilities

- `CabinClass` and `FareType` on `FlightSegment`
- `AirSeatMap` / `AirAncillaryItems`
- `AirBookModify` `ModificationType=5` for seats and ancillary items
- `PaymentInfo` on `AirDemandTicket` (ticket, seat payment, ancillary payment)
- Read transaction history via `TotalFare/@FareAmountType`
- Seat selection gate before v3 ticketing (certification-safe, fail-closed)

## Certification state

| Item | Status |
| --- | --- |
| JetPakistan Zapways credentials | Waiting (certificate must be supplier-loaded first) |
| v2 supplier certification | Waiting |
| v3 supplier certification | Waiting |
| v3 LIVE activation | Blocked (`AIRBLUE_OTA_V3_LIVE_ENABLED=false` by default) |
| Real supplier mutation in dev loops | Forbidden until credentials issued |

## Unsupported under Zapways OTA

- Hitit Crane NDC (use `pia_ndc` for PIA)
- NDC void/ticket-preview ancillary probes on Zapways
- Separate offer-price repricing operation

`AirBook` must reproduce supplier `PTC_FareBreakdowns` from the selected search offer — never recalculate from display totals.
