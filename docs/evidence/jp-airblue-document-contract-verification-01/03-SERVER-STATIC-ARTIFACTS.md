# Phase 4 — Server-side static artifact search (read-only)

HOST: JetPakistan production app host (SSH). No credential dumps. No passenger data printed.

```
SERVER_CONTRACT_ARTIFACTS_FOUND=YES
SERVER_AUTHORITATIVE_SOURCE_FOUND=YES
```

## Contract artifacts (safe inventory)

| Path | Bytes | SHA256 | Type |
|---|---:|---|---|
| `/tmp/airblue-v3-wsdl-20261024T201600Z/OTAAPI.wsdl` | 294519 | `9fe004b3e20ddcc998481609ebd0edc07bcbfbfe24a7ac49afc25b959135a128` | Zapways OTA **v3.0** WSDL |
| `/tmp/airblue-wsdl-read-20261024T191601Z/OTAAPI.wsdl` | 258347 | `f74c846367fbd7bfee6e09358d05e341305e055e9c586f6c8121d6775662340a` | Zapways OTA **v2.0** WSDL |
| `/tmp/ab-nre-closure/otaapi.wsdl` | 258347 | `f74c846367fbd7bfee6e09358d05e341305e055e9c586f6c8121d6775662340a` | Same v2.0 WSDL |

Also present: `/tmp/airblue-v3-wsdl-20261024T201600Z/op-AirBook.html` (ASMX help page).

## Not found (authoritative national-ID DocType)

- No Zapways/AirBlue official OTA integration PDF/DOCX retained under scanned non-public roots that defines DocType enum values for CNIC/national ID.
- Guide filename scan hit many JetPakistan internal OTA audit/integration markdown files; none are Zapways vendor DocType code lists.

Proprietary full WSDL **not** committed to git (policy: retain hashes + sanitized extracts only).
