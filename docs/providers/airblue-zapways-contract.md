# AirBlue Zapways OTA contract

Authoritative JetPakistan mapping for AirBlue direct integration (Zapways only).

## Version layers

| Item | Current JetPK | Legacy/reference | Authoritative value |
| --- | --- | --- | --- |
| endpoint (live) | `https://ota3.zapways.com/v2.0/OTAAPI.asmx` | Binham `AIRBLUE_API_URL` | `ota3.zapways.com/v2.0/OTAAPI.asmx` |
| endpoint (QA) | `https://ota.qa.zapways.com/v2.0/OTAAPI.asmx` | Binham cert | `ota.qa.zapways.com/v2.0/OTAAPI.asmx` |
| namespace | `http://zapways.com/air/ota/2.0` | Binham trait | `http://zapways.com/air/ota/2.0` |
| SOAPAction (search) | `http://zapways.com/air/ota/2.0/AirLowFareSearch` | Binham trait | same |
| SOAPAction (Read live) | `https://ota.zapways.com/Read` | ZW-OTA v2.06 | distinct Read URL style |
| SOAPAction (Read QA) | `https://ota.qa.zapways.com/Read` | ZW-OTA v2.06 | distinct Read URL style |
| Target | credential `service_target`, default `Production` | Binham `SERVICE_TARGET` | per credential |
| Version (RQ attr) | credential `service_version`, default `1.04` | Binham `SERVICE_VERSION` | `1.04` unless credential overrides |
| RequestorID Type | credential `agent_type` | Binham `AIRBLUE_AGENT_TYPE` | per credential |
| ERSP_UserID | `{client_id}/{client_key}` | Binham trait | `{client_id}/{client_key}` |
| agent authentication | `RequestorID` ID + `MessagePassword` | Binham trait | `agent_id` + `agent_password` |

Doc revision **v2.06** (ZW-OTA API PDF) refers to documentation branding, not the HTTP path or RQ `Version` attribute.

## Pricing contract

Zapways OTA does not expose a separate AirPrice/DoOfferPrice step in JetPakistan's integration. `AirLowFareSearch` response is authoritative for checkout; `AirBlueOfferPriceService` validates stored provider context only.

## Unsupported under Zapways OTA

- Hitit Crane NDC (use `pia_ndc` for PIA)
- NDC void/ticket-preview ancillary probes
- Separate offer-price repricing operation
