# AirBlue Crane NDC 20.1 service matrix

**Endpoint:** `https://app.crane.aero/cranendc/v20.1/CraneNDCService`  
**WSDL:** `https://app.crane.aero/cranendc/v20.1/CraneNDCService?wsdl`

## Core lifecycle

| SOAPAction | Request | Response | OTA service |
|------------|---------|----------|-------------|
| `doAirShopping` | `IATA_AirShoppingRQ` | `IATA_AirShoppingRS` | Search |
| `doOfferPrice` | `IATA_OfferPriceRQ` | `IATA_OfferPriceRS` | Revalidate (optional) |
| `doOrderCreate` | `IATA_OrderCreateRQ` | `IATA_OrderViewRS` | Option PNR |
| `doOrderRetrieve` | `IATA_OrderRetrieveRQ` | `IATA_OrderViewRS` | Retrieve |
| `doTicketPreview` | `IATA_OrderChangeRQ` | `IATA_OrderViewRS` | Ticket preview |
| `doOrderChange` | `IATA_OrderChangeRQ` + payment | `IATA_OrderViewRS` | Ticketing |
| `doOrderCancelPreview` | `IATA_OrderReshopRQ` | `IATA_OrderReshopRS` | Cancel preview |
| `doOrderCancelCommit` | `IATA_OrderChangeRQ` | `IATA_OrderViewRS` | Cancel commit |
| `doVoidTicket` | `IATA_OrderChangeRQ` | `IATA_OrderViewRS` | Void |

## Ancillary (from samples)

| Operation | Sample file |
|-----------|-------------|
| `doSeatAvailability` | `doSeatAvailability_req.xml` |
| `doBaggageServiceList` | `doBaggageServiceList_req.xml` |
| `doAddAncillary` (seat/weightbag) | `doAddAncillary_*_req.xml` |
| `doSellAncillary` | `doSellAncillary*_req.xml` |

Samples: [`docs/providers/airblue-ndc-samples/`](airblue-ndc-samples/)

## Defaults (AirBlue)

- Carrier: **PA**
- Currency: **PKR**
- Language: **EN**
