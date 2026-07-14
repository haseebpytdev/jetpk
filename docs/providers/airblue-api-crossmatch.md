# AirBlue API crossmatch — Crane NDC vs Zapways OTA

**Provider code:** `airblue`  
**Airline:** AirBlue / PA  
**Last updated:** 2026-06-23

## Channels

| | Crane NDC 20.1 | Zapways OTA v2.06 |
|--|----------------|-------------------|
| **Docs** | Ancillary NDC API Samples RAR, PIA NDC 20.1 pattern | `ZW-OTA API v2.06.pdf` |
| **Endpoint (live)** | `https://app.crane.aero/cranendc/v20.1/CraneNDCService` | `https://ota3.zapways.com/v2.0/OTAAPI.asmx` |
| **Endpoint (cert)** | Admin-configured | `https://ota.qa.zapways.com/v2.0/OTAAPI.asmx` |
| **Auth** | HTTP headers `username` / `password` + Party block | POS `ERSP_UserID` + `RequestorID` in SOAP body; optional TLS client cert |
| **Search** | `doAirShopping` | `AirLowFareSearch` |
| **Book** | `doOrderCreate` | `AirBook` |
| **Retrieve** | `doOrderRetrieve` | `Read` |
| **Ticket** | `doTicketPreview` + `doOrderChange` | `AirDemandTicket` |
| **Cancel** | `doOrderCancelPreview` + `doOrderCancelCommit` | `Cancel` (unticketed) |
| **Ancillary** | Seat/baggage samples in RAR | Not in OTA PDF scope |

## Binham reference

| Path | Channel | Notes |
|------|---------|-------|
| `Binham/Iati_new/jet.iati.pk/.../AirblueTrait.php` | Zapways OTA | Search only (`AirLowFareSearch`) |
| `Binham/ota.binham.pk/.../HititTrait.php` | PIA Crane (PK) | **Not AirBlue** — do not copy |

## OTA implementation notes

- Connection field `credentials.api_channel`: `crane_ndc` | `zapways_ota`
- Separate XML stacks: `AirBlueXml*` (NDC) vs `AirBlueOtaXml*` (OTA)
- Unified adapters; `SupplierSourcePresenter` label: **AirBlue**
- Never expose raw SOAP/XML to customers
