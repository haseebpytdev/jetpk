# CONFIG — AirBlue Zapways TEST v2

**Authoritative connection:** `SupplierConnection` id **22** — `AirBlue Zapways TEST v2`  
**Environment:** sandbox (TEST)  
**Endpoint:** `https://otatest4.zapways.com/v2.0/OTAAPI.asmx`

## Safe presence (no secret values)

| Field | Status |
|---|---|
| ENVIRONMENT | TEST (`sandbox`) |
| CLIENT_ID_PRESENT | YES |
| CLIENT_KEY_PRESENT | YES |
| AGENT_ID_PRESENT | YES |
| AGENT_ID expected `JetPakistanOTA` | YES (connection 22) |
| AGENT_PASSWORD_PRESENT | YES |
| TLS_CERT_PATH_CONFIGURED | YES (`AIRBLUE_ZAPWAYS_TLS_CERT_PATH` → expected cert path) |
| TLS_KEY_PATH_CONFIGURED | YES (`AIRBLUE_ZAPWAYS_TLS_KEY_PATH` → expected key path) |

**Note:** Production holds multiple active AirBlue sandbox rows from dashboard write-cert QA (`JPQA-WRITE-*`). Certification used connection **22** only.

## Save / validation

| Step | Result |
|---|---|
| CONFIG_SAVE | NOT_REQUIRED (connection 22 already saved with TEST Zapways v2 fields) |
| CONFIG_VALIDATION | PASS (`credentialKeysPresent` + `airblue:health` healthy for connection 11/22 resolver paths) |

Semantics: configuration valid ≠ connectivity ≠ authenticated ≠ search certified.
