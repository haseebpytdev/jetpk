# PIA NDC — Reference Code Audit (Binham)

Scanned paths: `Binham/public_html`, `Binham/iati`, `Binham/Iati_new`, `Binham/ota.binham.pk`

---

## Summary

| Path | PIA/Hitit NDC? | Verdict |
|------|----------------|---------|
| Main OTA `app/` | No NDC | Greenfield `pia_ndc` module |
| `Binham/ota.binham.pk` | Legacy Crane **OTA** SOAP | Reference only — **not** NDC |
| `Binham/Iati_new` | Broken Hitit refs | Incomplete; trait missing |
| `Binham/public_html`, `Binham/iati` | No Hitit | Unrelated |

**No `CraneNDCService`, `DoAirShopping`, or `IATA_AirShoppingRQ` found in Binham.**

---

## Relevant Binham files

| File | Purpose | Reuse? |
|------|---------|--------|
| [`Binham/ota.binham.pk/app/Http/Traits/APIS/HititTrait.php`](../../Binham/ota.binham.pk/app/Http/Traits/APIS/HititTrait.php) | PIA via Crane OTA: `GetAvailability`, `CreateBooking`, `issueTicket`, cancel, void | **Do not copy payloads** — different SOAP namespace (`impl.soap.ws.crane.hititcs.com`) |
| [`Binham/ota.binham.pk/app/Http/Controllers/Admin/Flight/CheckoutController.php`](../../Binham/ota.binham.pk/app/Http/Controllers/Admin/Flight/CheckoutController.php) | Checkout orchestration | Flow reference only |
| [`Binham/ota.binham.pk/public/last_soap_request.xml`](../../Binham/ota.binham.pk/public/last_soap_request.xml) | Captured CreateBooking SOAP | Sensitive; legacy OTA |

---

## Unsafe patterns to avoid

- Credentials in `env('pia_url')` / committed XML debug files
- `dd()`, `dump()`, `var_dump()` in production paths
- Raw SOAP errors shown to users
- Blind copy of Hitit OTA XML into NDC module

---

## Useful mapping (conceptual only)

| Legacy OTA (HititTrait) | NDC 20.1 equivalent |
|-------------------------|---------------------|
| `GetAvailability` | `DoAirShopping` |
| `CreateBooking` (option) | `DoOrderCreate` |
| `fetchPNR` | `DoOrderRetrieve` |
| `issueTicket` | `DoTicketPreview` + `DoOrderChange` |
| `cancelBookingRequest` | Cancel preview + commit |
| `voidBookingRequest` | `DoVoidTicket` |

Implement NDC strictly from manual + sample XML, not from HititTrait payloads.
