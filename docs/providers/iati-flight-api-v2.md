# IATI Flight API v2 — OTA Integration Reference

**Last updated:** 2026-06-22  
**Official docs:** http://testapi.iati.com/rest/flight/v2/docs  
**OpenAPI:** Not found at common paths during audit (`swagger.json`, `openapi.json` returned 404). Use `php artisan iati:audit-docs` to re-probe.

---

## Environment URLs

| Environment | Host | Auth base | Flight v2 base |
|-------------|------|-----------|----------------|
| TEST | `https://testapi.iati.com` | `/rest/auth` | `/rest/flight/v2` |
| PROD | `https://api.iati.com` | `/rest/auth` | `/rest/flight/v2` |

OTA maps `SupplierEnvironment::Demo` / `Sandbox` → TEST; `Live` → PROD.

---

## Authentication

IATI Flight API v2 for this OTA account accepts **`auth_code` directly as the Bearer token** on flight calls. A separate `/rest/auth/token` exchange is **not** used unless a legacy `secret` is stored and a 401 retry triggers token exchange.

| Item | Value |
|------|-------|
| Primary auth | `Authorization: Bearer {auth_code}` on all flight v2 calls |
| Organization header | `Organization-Id: {organization_id}` when configured (required in OTA admin) |
| Legacy token exchange | `GET {auth_base}/token` with `Authorization: Basic base64(auth_code:secret)` — only when `secret` is stored and Bearer auth_code returns 401 |
| Required credentials | `auth_code`, `organization_id` |
| Optional credentials | `secret` (legacy exchange), `language_code` (default `en`) |

---

## Required headers (flight v2)

- `Authorization: Bearer {auth_code}` (or exchanged token when legacy secret fallback applies)
- `Organization-Id: {organization_id}`
- `Accept: application/json`
- `Content-Type: application/json` (POST bodies)
- `X-Correlation-ID` (OTA-generated per request)

---

## Endpoints

### Health / credential test

| Method | Path | Notes |
|--------|------|-------|
| GET | `/test/ping` | Primary health check |
| POST | `/airport` | Fallback; body `{ "language_code": "en" }` |

### Search

| Method | Path |
|--------|------|
| POST | `/search` |

**Request:**
```json
{
  "from_destination": { "code": "LHE", "city": false },
  "to_destination": { "code": "DXB", "city": false },
  "departure_date": "2026-07-01",
  "return_date": "2026-07-08",
  "pax_list": [
    { "type": "ADULT", "count": 1 },
    { "type": "CHILD", "count": 0 },
    { "type": "INFANT", "count": 0 }
  ],
  "accept_pending": true,
  "cabin_type": "ECONOMY"
}
```

**Response (unwrap `result` if present):**
- `departure_flights[]` with `fares[]`, legs, `fare_info`
- `return_flights[]` (round-trip)
- Fare keys: `departure_fare_key`, `return_fare_key` on selected fare

### Fare confirmation (revalidation)

| Method | Path |
|--------|------|
| POST | `/fare` |

**Note:** Reference implementation uses `/fare`, not `/revalidate`. Implement `/fare`.

**Request:**
```json
{
  "departure_fare_key": "...",
  "return_fare_key": "...",
  "pax_list": [{ "type": "ADULT", "count": 1 }]
}
```

**Response fields consumed:**
- `fare_detail_key` (required for booking)
- `offers[]` with `offer_key`, `total_price`, `can_book`, `can_rezerve`
- `change_rules[]` embedded on fare objects (no separate fare-rules endpoint in reference)

### Booking

| Method | Path | When |
|--------|------|------|
| POST | `/option` | Unpaid hold |
| POST | `/book` | Paid immediate confirm |
| POST | `/option/{orderId}/book` | Convert hold after payment |

**Request shape:**
```json
{
  "fare_detail_key": "...",
  "contact": {
    "email": "...",
    "phone": { "country_code": "92", "area_code": "300", "phone_number": "1234567" },
    "organization_id": "..."
  },
  "pax_list": [{
    "name": "John",
    "lastname": "Doe",
    "birthdate": "1990-01-01",
    "type": "ADULT",
    "gender": "MALE",
    "identity_info": {
      "not_turkish_citizen": true,
      "passport": {
        "no": "AB1234567",
        "citizenship_country": "PK",
        "end_date": "2031-01-01"
      }
    }
  }],
  "offers": ["offer_key_1"],
  "accept_pending": true,
  "notes": "optional"
}
```

**Response:**
- Option: `options[0]` or `option` → `order_id`, `pnr`, `last_ticketing_date`
- Book: `books[0]` → `order_id`, `pnr`

### Retrieve

| Method | Path |
|--------|------|
| GET | `/order/{orderId}` |
| POST | `/order` | Body: `{ "start_date", "end_date" }` max 7 days |

### Cancel

| Method | Path |
|--------|------|
| GET | `/book/{orderId}/cancel` |
| GET | `/option/{orderId}/cancel` |

Response: `{ "cancelled": true|false }`

### Balance

Not confirmed in reference code. `iati:balance` probes `/balance` if documented.

---

## Persisted provider references (OTA `provider_context`)

| Field | Stage |
|-------|-------|
| `departure_fare_key`, `return_fare_key` | Search selection |
| `pax_counts` | Search |
| `fare_detail_key` | After `/fare` |
| `offer_keys[]` | After `/fare` |
| `order_id` | After option/book |
| `mode` | `option` or `book` |
| `pnr` | After option/book |
| `last_ticketing_date` | Option hold |
| `search_correlation_id` | Diagnostics |

---

## Docs vs reference conflicts

| Topic | Implement |
|-------|-----------|
| Fare endpoint name | `POST /fare` |
| Ticketing | `POST /book` or `POST /option/{id}/book` — no `/ticket` |
| Fare rules | Parse `change_rules` from fare payload |
| Balance | Probe only; graceful unsupported |
| Refund HTTP | Not implemented (reference is DB-only stub) |

---

## Error format

Provider may return HTTP status + JSON with `error`, `code`, `error_code`, `message`, or `description`. OTA maps to customer-safe messages; never expose raw JSON on public frontend.

Known codes from reference: `VA009` (defer book-after-payment on option conflict).

---

## TEST vs PROD

Only host base differs. Credentials are environment-specific; never reuse iati.pk tokens.
