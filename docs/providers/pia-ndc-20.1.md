# PIA Hitit Crane NDC 20.1 — OTA Integration Reference

**Last updated:** 2026-06-23  
**Provider code:** `pia_ndc`  
**Airline:** PIA / PK  
**Manual:** `HITIT_CRANENDC_MANUAL_20.1 (2)-updated.pdf`  
**Samples:** [`pia-ndc-samples/PIA-NDC/`](pia-ndc-samples/PIA-NDC/)

---

## API type

| Item | Value |
|------|-------|
| Protocol | SOAP over HTTP |
| NDC version | IATA NDC **20.1** (`RefVersionNumber` / schema year 2020.1) |
| Service name | **CraneNDCService** (Hitit) |
| HTTP method | **POST** |
| Endpoint URL | **Admin `base_url` required** — not present in sample XML; configure per Cert/Live environment |
| Content-Type | `text/xml; charset=utf-8` |
| SOAPAction | Per operation (see matrix below); confirm exact values from manual / WSDL |

---

## Authentication

Credentials are sent in **HTTP headers**, not in the SOAP body.

| Item | OTA storage | Notes |
|------|-------------|-------|
| Username | `credentials.username` | Header name configurable; default `username` |
| Password | `credentials.password` | Header name configurable; default `password` |
| Agency ID | `credentials.agency_id` | Maps to `Party/Sender/TravelAgency/AgencyID` |
| Agency name | `credentials.agency_name` | Maps to `Party/Sender/TravelAgency/Name` |
| Owner code | `credentials.owner_code` | Used in offers/orders (`OwnerCode`); samples show `PK`, `VF`, `S5` |
| Carrier code | `credentials.carrier_code` | Default `PK` for display/validation |
| Currency | `credentials.currency` | Default `PKR` |
| Language | `credentials.language_code` | Default `EN` → `LangCode` / `PrimaryLangID` |

**TBD from manual:** exact HTTP header names if different from `username` / `password`; WSDL URL; Cert/Live host patterns.

---

## SOAP operation matrix

| Hitit operation | Request root (SOAP Body) | Response root | OTA service |
|-----------------|--------------------------|---------------|-------------|
| `DoAirShopping` | `IATA_AirShoppingRQ` | `IATA_AirShoppingRS` | Search |
| `DoOfferPrice` | `IATA_OfferPriceRQ` | `IATA_OfferPriceRS` | Offer price (no sample — optional/no-op) |
| `DoOrderCreate` | `IATA_OrderCreateRQ` | `IATA_OrderViewRS` | Option PNR (no payment) |
| `DoOrderRetrieve` | `IATA_OrderRetrieveRQ` | `IATA_OrderViewRS` | Retrieve/sync |
| `DoTicketPreview` | `IATA_OrderChangeRQ` (order only) | `IATA_OrderViewRS` | Ticket preview |
| `DoOrderChange` | `IATA_OrderChangeRQ` + payment | `IATA_OrderViewRS` | Ticketing (MCO) |
| `DoOrderCancelPreview` | `IATA_OrderReshopRQ` | `IATA_OrderReshopRS` | Cancel preview |
| `DoOrderCancelCommit` | `IATA_OrderChangeRQ` + `ChangeOrder/CancelOrder` | `IATA_OrderViewRS` | Cancel commit |
| `DoVoidTicket` | `IATA_OrderChangeRQ` (order only) | `IATA_OrderViewRS` | Void ticket |
| `DoReissuePreview` | `IATA_OrderReshopRQ` | `IATA_OrderReshopRS` | Reissue preview |
| `DoReissueCommit` | `IATA_OrderChangeRQ` | `IATA_OrderViewRS` | Reissue commit |
| `DoGeneralParams` | TBD manual | TBD | Health/diagnostic |
| `DoAirlineProfile` | `IATA_AirlineProfileRQ` | `IATA_AirlineProfileRS` | Profile/diagnostic |

---

## IATA 20.1 namespaces (request types)

Base pattern: `http://www.iata.org/IATA/2015/00/2020.1/{MessageName}`

Examples from samples:
- `IATA_AirShoppingRQ` / `IATA_AirShoppingRS`
- `IATA_OrderCreateRQ` / `IATA_OrderViewRS`
- `IATA_OrderRetrieveRQ`
- `IATA_OrderChangeRQ`
- `IATA_OrderReshopRQ` / `IATA_OrderReshopRS`

---

## Party block (required on all requests)

```xml
<Party>
  <Sender>
    <TravelAgency>
      <AgencyID>{agency_id}</AgencyID>
      <Name>{agency_name}</Name>
    </TravelAgency>
  </Sender>
</Party>
```

---

## Lifecycle rules (manual + samples)

1. **DoAirShopping** response must be passed into **DoOrderCreate** via `ShoppingResponseRefID`, `OfferRefID`, `SelectedOfferItem`.
2. **Option PNR** = **DoOrderCreate** without payment information.
3. **DoOrderRetrieve** syncs PNR/order status and ticket numbers when ticketed.
4. **Ticketing:** **DoTicketPreview** then **DoOrderChange** with `PaymentFunctions` (MCO `AccountableDoc` in samples).
5. **Cancel/refund:** **DoOrderCancelPreview** (OrderReshop) then **DoOrderCancelCommit** (OrderChange).
6. **DoVoidTicket** reverts ticketed reservation to option (airline permission required).
7. **Reissue:** preview (OrderReshop) then commit (OrderChange).

---

## PNR / order mapping

| OTA field | NDC field |
|-----------|-----------|
| PNR / supplier reference | `OrderID` (e.g. `7UU0J3`) |
| Owner | `OwnerCode` |
| Payment deadline | `PaymentTimeLimitDateTime` |
| Ticket numbers | `TicketDocInfo/Ticket/TicketNumber` (when present) |

---

## Error / warning format

Parse from response bodies:
- SOAP Fault (`faultcode`, `faultstring`)
- NDC `Error` elements (`Code`, `DescText`, `TypeCode`)
- NDC `Warning` elements

Never expose raw XML/SOAP to customers.

---

## Operations requiring airline permissions

Per manual (confirm in PDF):
- Ticketing via OrderChange / MCO
- Cancel commit / refund
- Void ticket
- Reissue commit

---

## Environment

| UI label | Stored enum | Notes |
|----------|-------------|-------|
| Cert | `sandbox` | Test credentials |
| Live | `live` | Production |

Both require admin-configured `base_url` until Hitit provides fixed host URLs in manual.
