# PIA NDC 20.1 — Sample XML Audit

Samples: [`pia-ndc-samples/PIA-NDC/`](pia-ndc-samples/PIA-NDC/) (36 files)

---

## Air shopping

| File | Type | Body root | Notes |
|------|------|-----------|-------|
| `doAirShopping_OW_req.xml` | Request | `IATA_AirShoppingRQ` | KHI→ISB, 1 ADT+CHD+INF in sample pax list |
| `doAirShopping_OW_res.xml` | Response | `IATA_AirShoppingRS` | Multiple offers; persist `ShoppingResponseRefID`, `OfferID`, `OfferItemID`, `OwnerCode`, segment/journey refs |
| `doAirShopping_RT_req.xml` | Request | `IATA_AirShoppingRQ` | Two `OriginDestCriteria` blocks (outbound + return) |
| `doAirShopping_RT_res.xml` | Response | `IATA_AirShoppingRS` | RT offers with combined journey refs |
| `doAirShopping_2A1C1I_req.xml` | Request | `IATA_AirShoppingRQ` | 2 ADT, 1 CHD, 1 INF pax IDs |
| `doAirShopping_2A1C1I_res.xml` | Response | `IATA_AirShoppingRS` | Per-PTC `OfferItem` pricing |

**Persist from shopping RS → OrderCreate RQ:** `ShoppingResponseRefID`, `OfferRefID` (= `OfferID`), `SelectedOfferItem/OfferItemRefID`, `PaxRefID`, `OwnerCode`, `PaymentTimeLimitDateTime`.

---

## Order create (option PNR)

| File | Type | Body root | Notes |
|------|------|-----------|-------|
| `doOrderCreate_OW_req.xml` | Request | `IATA_OrderCreateRQ` | No payment; `CreateOrder/SelectedOffer` from shopping |
| `doOrderCreate_OW_res.xml` | Response | `IATA_OrderViewRS` | `OrderID`, `PaymentTimeLimitDateTime` |
| `doOrderCreate_RT_req.xml` | Request | `IATA_OrderCreateRQ` | `OfferRefID` = two base64 blobs joined by `\|` |
| `doOrderCreate_RT_res.xml` | Response | `IATA_OrderViewRS` | RT order view |

**Missing:** `doOrderCreate_2A1C1I` — derive from OW + 2A1C1I shopping (multiple `SelectedOfferItem` per pax).

---

## Order retrieve

| File | Type | Body root | Notes |
|------|------|-----------|-------|
| `doOrderRetrieve_*_req.xml` | Request | `IATA_OrderRetrieveRQ` | `OrderFilterCriteria/Order/OrderID` + `OwnerCode` |
| `doOrderRetrieve_*_res.xml` | Response | `IATA_OrderViewRS` | Sync status, limits, tickets when ticketed |

---

## Ticket preview & ticketing

| File | Type | Body root | Notes |
|------|------|-----------|-------|
| `doTicketPreview_*_req.xml` | Request | `IATA_OrderChangeRQ` | Order + currency only — **no** `PaymentFunctions` |
| `doTicketPreview_*_res.xml` | Response | `IATA_OrderViewRS` | Payable total for OrderChange |
| `doOrderChange_OW_req.xml` | Request | `IATA_OrderChangeRQ` | `PaymentFunctions` MCO `AccountableDoc` + `Amount` |
| `doOrderChange_*_res.xml` | Response | `IATA_OrderViewRS` | Ticket numbers after issue |

**Ticketing flow:** TicketPreview amount → OrderChange `PaymentProcessingDetails/Amount`.

---

## Cancel

| File | Type | Body root | Notes |
|------|------|-----------|-------|
| `doOrderCancelPreview_OW_req.xml` | Request | `IATA_OrderReshopRQ` | `UpdateOrder/CancelOrder/OrderRefID` |
| `doOrderCancelPreview_OW_res.xml` | Response | `IATA_OrderReshopRS` | Penalty/refund preview |
| `doOrderCancelCommit_OW_req.xml` | Request | `IATA_OrderChangeRQ` | `ChangeOrder/CancelOrder` + `Order/OrderID` |
| `doOrderCancelCommit_OW_res.xml` | Response | `IATA_OrderViewRS` | Cancelled status |

---

## Void & reissue

| File | Type | Body root | Notes |
|------|------|-----------|-------|
| `doVoidTicket_req.xml` | Request | `IATA_OrderChangeRQ` | Order only — no payment, no cancel block |
| `doVoidTicket_res.xml` | Response | `IATA_OrderViewRS` | Restored option + new `PaymentTimeLimitDateTime` |
| `doReissuePreview_*` | Request/Response | OrderReshop RQ/RS | Reissue quote |
| `doReissueCommit_*` | Request/Response | OrderChange RQ / OrderView RS | Reissue completion |

**Void vs Cancel:** Void uses minimal OrderChange (no CancelOrder); Cancel uses Reshop preview + OrderChange with CancelOrder.

---

## Round trip representation

- Search: two `OriginDestCriteria` in one `FlightRequest`.
- OrderCreate RT: single `SelectedOffer` with pipe-concatenated `OfferRefID`.
- Retrieve/ticket: single `OrderID` covers full itinerary.

---

## 2A1C1I representation

- Search pax list: separate `Pax` entries with `PTC` ADT/CHD/INF.
- Shopping RS: multiple `OfferItem` per offer with `FareDetail` per pax type.
- OrderCreate: one `SelectedOfferItem` per pax ref (derive from shopping when sample missing).
