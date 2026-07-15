# IATI Reference Code Audit (Binham)

**Audit date:** 2026-06-22  
**Primary reference:** `Binham/Iati_new/modules/flights/iati/helper.php`  
**Mirror:** `Binham/public_html/modules/flights/iati/`  
**Not useful:** `Binham/iati`, `Binham/ota.binham.pk` (no IATI v2)

---

## Core files

| Path | Role | Endpoints |
|------|------|-----------|
| `modules/flights/iati/helper.php` | Main client + normalization + booking | All IATI v2 |
| `modules/flights/iati/search.php` | Search route | POST `/search` |
| `modules/flights/iati/creds.php` | Credential test | Auth + ping/airport |
| `modules/flights/iati/actions/issue.php` | Booking issue | `/fare`, `/option`, `/book` |
| `modules/flights/iati/actions/cancel.php` | Cancel | GET cancel endpoints |
| `modules/flights/iati/actions/status.php` | Order status | GET `/order/{id}` |
| `modules/flights/iati/actions/refund.php` | **Stub** — no API | — |
| `app/lib/flight-supplier-booking.php` | Checkout wiring | `IATI_ISSUE_BOOKING()` |

---

## Auth flow

- `IATI_AUTH_TOKEN()` → GET `{auth_base}/token`, Basic auth
- Cache: in-memory + `api_tokens` table (~24h)
- **Do not copy:** plaintext tokens in DB

---

## Search flow

- `IATI_SEARCH_PAYLOAD_FROM_POST()` builds search body
- `IATI_NORMALIZE_SEARCH_RESPONSE()` → PHPTRAVELS result cards with `booking_data`
- Persists `departure_fare_key`, `return_fare_key`, brand metadata

---

## Revalidation

- `POST /fare` inside `IATI_ISSUE_BOOKING()` before book/option
- Requires `fare_detail_key`; selects `offer_key` via `IATI_SELECT_OFFER_KEYS()`

---

## Booking flow

1. Existing `order_id` → return or `POST /option/{id}/book` if paid
2. Else `POST /fare` → build passengers → `POST /option` (unpaid) or `POST /book` (paid)
3. HTTP 409 + `VA009` → deferred book path

---

## Ticketing

No `/ticket` endpoint. Ticketing = book confirmation. Status `BOOKED` inferred from order retrieve.

---

## Retrieve

- `IATI_GET_ORDER_STATUS()` → GET `/order/{orderId}`
- `IATI_FIND_ORDER_FOR_IMPORT()` → POST `/order` date range scan

---

## Cancel

- `IATI_CANCEL_BOOKING()` → GET `/book/{id}/cancel` or `/option/{id}/cancel`
- Fallback book→option cancel on failure

---

## Passenger mapping

| Local | IATI |
|-------|------|
| first/last name | `name`, `lastname` |
| DOB | `birthdate` |
| type | ADULT/CHILD/INFANT |
| title | gender MALE/FEMALE |
| passport | `identity_info.passport` |
| CNIC | `identity_info.cnic` |
| email/phone | `contact` |

**Do not copy:** synthetic default DOB, phone, passport expiry

---

## Unsafe patterns (must not copy)

- `?debug=1` writes unsanitized search logs to `uploads/iati_search_debug.txt`
- CORS `*` on module routes
- Debug files in web-accessible uploads
- Default PII when fields missing

---

## Balance

Not implemented in reference.
