# AirBlue reference code audit (Binham)

**Scanned:** 2026-06-23

## AirBlue-specific

| File | Channel | Operations |
|------|---------|------------|
| `Binham/Iati_new/jet.iati.pk/app/Http/Traits/APIS/AirblueTrait.php` | Zapways OTA | `AirLowFareSearch` only |
| `Binham/Iati_new/jet.iati.pk/app/Http/Traits/APIS/AirblueOneWay.php` | Zapways OTA | Test script (one-way search) |
| `Binham/Iati_new/jet.iati.pk/other.php` | Zapways OTA | Duplicate of AirblueTrait |

### Zapways env fields (Binham)

- `AIRBLUE_API_URL`, `AIRBLUE_API_CLIENT_ID`, `AIRBLUE_API_CLIENT_KEY`
- `AIRBLUE_AGENT_TYPE`, `AIRBLUE_AGENT_ID`, `AIRBLUE_AGENT_PASSWORD`
- `SERVICE_TARGET`, `SERVICE_VERSION`
- Optional TLS: `storage/app/Airblue/certs/combined.pem`

### Zapways SOAPAction (search)

`http://zapways.com/air/ota/2.0/AirLowFareSearch`

## Not AirBlue

| File | Notes |
|------|-------|
| `Binham/ota.binham.pk/.../HititTrait.php` | PIA Crane (PK) — search/book/ticket |
| `public_html` | No AirBlue PHP found |

## Gaps

1. Binham: search-only OTA; no NDC Crane implementation
2. No `AirblueTrait` on `ota.binham.pk` tree
3. Booking/ticketing for OTA must follow `ZW-OTA API v2.06.pdf`, not Binham code
