# Zapways TEST AirBook NRE — Supplier Escalation Pack

Sanitized. No credentials, certificates, private keys, or passenger PII.

| Field | Value |
|-------|-------|
| Timestamp (UTC) | 2026-10-22 (JetPakistan production runtime cert loop) |
| Runtime | JetPakistan production |
| Supplier credential class | Zapways **TEST** |
| Hostname | `otatest4.zapways.com` |
| Protocol | Zapways OTA 2.0 |
| Operation | AirBook |
| SOAPAction | `http://zapways.com/air/ota/2.0/AirBook` |
| HTTP status | 500 |
| Fault class | `provider_nre` |
| Fault snippet | `Server was unable to process request. ---> Object reference not set to an instance of an object.` |
| WSDL/contract structural checks (JetPK) | PASS — no hard mismatch found; not a full XSD validation |
| Wire contract (JetPK) | PASS (`Gender=Male`, `NamePrefix=MR`, `PTC=ADT`, `FareBasis/RBD=U`, `OperatingAirline=PA`, `Target=Test`) |
| Route | ISB→KHI |
| Flight | 201 |
| RBD / Fare basis | U / U |
| Passenger count / PTC | 1 / ADT |
| Currency / total | PKR / 26000 |
| PNR returned | NO |
| Instance returned | NO |
| AirBook calls this attempt | 1 (no retry) |

Request: JetPakistan asks Zapways to diagnose TEST AirBook server NRE for an OTA 2.0 AirBook request that passed JetPakistan's WSDL/contract structural checks under the dedicated TEST tenant.
