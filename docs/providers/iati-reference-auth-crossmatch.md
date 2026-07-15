# IATI Reference Auth / Search Crossmatch (iati.pk vs OTA)

**Date:** 2026-06-23  
**Official docs:** http://testapi.iati.com/rest/flight/v2/docs#section/Introduction  
**Scope:** Read-only analysis of Binham reference trees vs OTA `app/Services/Suppliers/Iati/*`. No production behavior changed in this pass.

---

## A. Reference files found

| Path | Role | IATI API evidence |
|------|------|-------------------|
| `Binham/Iati_new/modules/flights/iati/helper.php` | **Primary** — auth, HTTP client, search payload, response normalize, booking | `IATI_MODULE_CONFIG`, `IATI_AUTH_TOKEN`, `IATI_API_REQUEST`, `IATI_SEARCH_PAYLOAD_FROM_POST`, `IATI_NORMALIZE_SEARCH_RESPONSE` |
| `Binham/Iati_new/modules/flights/iati/search.php` | Search route `POST flights/iati/search` | Calls `IATI_API_REQUEST($config, 'POST', '/search', $payload)` |
| `Binham/Iati_new/modules/flights/iati/creds.php` | Credential verify route | `IATI_AUTH_TOKEN` + `IATI_VALIDATE_FLIGHT_API` |
| `Binham/Iati_new/modules/flights/iati/index.php` | Module bootstrap | Loads creds/search/actions |
| `Binham/Iati_new/modules/flights/iati/actions/*.php` | issue/void/cancel/refund/status | All via `IATI_API_REQUEST` in helper |
| `Binham/Iati_new/modules/index.php` | Admin module cred test handler | Same auth/token/ping flow as `creds.php` |
| `Binham/public_html/modules/flights/iati/*` | **Mirror** of `Iati_new` IATI module | Same `helper.php` auth/search logic (byte-for-byte pattern) |
| `Binham/Iati_new/uploads/iati_search_debug.txt` | Runtime search audit log | Successful `POST /search` HTTP 200, payload + response previews |
| `Binham/Iati_new/uploads/iati_booking_debug.txt` | Runtime booking audit log | `flight_base`, `organization_id` in **contact** payload only |
| `Binham/Iati_new/app/routes/users/iatiImportRoutes.php` | PNR import UI | `require_once` helper; no separate auth |
| `Binham/public_html/app/routes/users/iatiImportRoutes.php` | Mirror | Same |
| `Binham/iati/` | Legacy PHPTraveler-style site | **No** `modules/flights/iati` — no IATI Flight v2 client found |
| `Binham/ota.binham.pk/` | Laravel vendor tree | **No** IATI flight auth code found |

**Search terms with no hits in reference IATI code:**
- `get-jwt-token` — not used
- `availability` — not used (search only)
- `AG007` — not in source (OTA-observed error only)
- `Organization-Id` HTTP header — not sent by reference client

---

## B. Auth method used by iati.pk

**Confirmed pattern (code evidence):**

1. **Both `auth_code` and `secret` are required** (`modules.c1` + `modules.c2`). Missing either throws: *"IATI auth code and secret are required."*
2. **JWT is obtained via token exchange**, not by using auth_code as Bearer on flight calls.
3. **Token endpoint:** `GET {auth_base}/token` where `auth_base` is `https://testapi.iati.com/rest/auth` (test) or `https://api.iati.com/rest/auth` (prod).
4. **Token request headers:**
   - `Authorization: Basic base64(auth_code + ':' + secret)`
   - `Accept: application/json`
5. **No Agency ID as Basic username.** Username = `auth_code`, password = `secret`.
6. **No separate “agency password” field** beyond `secret` (c2).
7. **organization_id (c3)** is stored but **not used for HTTP auth** — only embedded in booking `contact.organization_id` when present.

Credential field mapping (`modules` table):

| DB column | Config key | Purpose |
|-----------|------------|---------|
| `c1` | `auth_code` | Basic auth username + IATI “auth code” |
| `c2` | `secret` | Basic auth password (required) |
| `c3` | `organization_id` | Booking contact only |
| `c4` | `language_code` | `/airport` payload (default `en`) |
| `dev_mode` | `dev_mode` | `1` → testapi, `0` → api.iati.com |

---

## C. Search endpoint used by iati.pk

| Item | Value |
|------|-------|
| Method | `POST` |
| URL | `{flight_base}/search` |
| Test base | `https://testapi.iati.com/rest/flight/v2` |
| Prod base | `https://api.iati.com/rest/flight/v2` |
| Alternate path | None (`/availability` not used) |

Evidence: `search.php` line 36 — `IATI_API_REQUEST($config, 'POST', '/search', $payload)`.

Debug log (`iati_search_debug.txt` line 5–8): production `flight_base` + successful HTTP 200 on `/search`.

---

## D. Search payload used by iati.pk

Built by `IATI_SEARCH_PAYLOAD_FROM_POST()`:

```json
{
  "from_destination": {"code": "LHE", "city": false},
  "to_destination": {"code": "DXB", "city": false},
  "departure_date": "2026-05-30",
  "pax_list": [{"type": "ADULT", "count": 1}],
  "accept_pending": true,
  "cabin_type": "ECONOMY"
}
```

**Differences vs OTA `IatiPayloadBuilder::buildSearchPayload()`:**

| Field | Reference (iati.pk) | OTA |
|-------|---------------------|-----|
| `pax_list` | ADULT always; CHILD/INFANT **only if count > 0** | Always sends ADULT + CHILD(0) + INFANT(0) |
| `cabin_type` | `ECONOMY` unless class is `business` or `first` → `BUSINESS` | `ECONOMY` / `BUSINESS` / `FIRST` (no combined business→BUSINESS only) |
| `return_date` | Added when trip type is return/round/roundtrip | Same logic |
| Structure | Same `from_destination` / `to_destination` shape | Same |

**Likely auth blocker, not payload:** reference succeeds with the same LHE→DXB shape; OTA AG007 points to JWT, not body validation.

---

## E. Headers used by iati.pk

### Token exchange (`GET /rest/auth/token`)

```
Authorization: Basic {base64(auth_code:secret)}
Accept: application/json
```

### Flight v2 calls (`IATI_API_REQUEST`)

```
Authorization: Bearer {jwt_from_token_exchange}
Accept: application/json
Content-Type: application/json
```

**Not sent by reference:**
- `Organization-Id`
- `X-Correlation-ID`
- Custom agency headers
- IP spoofing / whitelist client logic

---

## F. Token / JWT behavior

| Aspect | Reference (iati.pk) | OTA |
|--------|---------------------|-----|
| Exchange | **Always** before every `IATI_API_REQUEST` | Default: **no exchange** — uses `auth_code` as Bearer |
| Exchange when | On cache miss / expiry | Only if `secret` set AND `preferTokenExchange` or 401 retry |
| Cache | PHP static array + `api_tokens` DB table (~86000s) | Laravel `Cache` (~86000s) when exchange used |
| Bearer on flight calls | **JWT** from `/rest/auth/token` | **auth_code** by default |
| Ping | Uses same JWT path via `IATI_API_REQUEST` | Uses auth_code Bearer directly (works — HTTP 201) |
| Search | JWT Bearer | auth_code Bearer → **AG007** observed |

Token extraction keys tried: `access_token`, `token`, `bearer_token`, `jwt`, nested `data.*`, plus raw non-JSON body — same strategy as OTA `IatiAuthService::extractToken()`.

---

## G. Difference vs our OTA IATI module

| Component | Reference behavior | OTA behavior | Impact |
|-----------|-------------------|--------------|--------|
| `IatiAuthService` | N/A (reference always exchanges) | `getBearerToken()` returns `auth_code` unless `preferTokenExchange` | **Search gets non-JWT Bearer → AG007** |
| `IatiClient` | N/A | Sends `Organization-Id` on every flight call | Unknown if required; reference omits it |
| `IatiConfigResolver` | Requires auth_code + secret | Requires auth_code + organization_id; secret optional | OTA may lack secret in DB |
| `IatiPayloadBuilder` | Omits zero pax types | Sends CHILD/INFANT count 0 | Minor; unlikely AG007 cause |
| `IatiFlightSearchService` | N/A | Uses `IatiClient` → wrong token mode | Search fails |
| `IatiTestSearchCommand` | N/A | Exercises current (broken) path | Reproduces AG007 |
| `config/supplier_credentials.php` | N/A | UI says auth_code is Bearer; secret optional | **Misdocuments real IATI flow** |
| `IatiSupplierConnectionNormalizer` docblock | N/A | Says auth_code as Bearer | Stale vs reference |

**Observed OTA symptom (user report):**
- `GET /test/ping` + `Authorization: Bearer {auth_code}` + `Organization-Id: 187570` → **HTTP 201**
- `POST /search` same Bearer → **HTTP 401 AG007** *"Requested JWT Token invalid. Please refresh your token."*

**Reference explains this:** ping may accept auth_code; search requires exchanged JWT (reference never sends auth_code to flight endpoints).

---

## H. Required OTA fixes (not applied in this pass)

1. **`IatiAuthService::getBearerToken()`** — Default to JWT via `GET /rest/auth/token` when `secret` is configured (match reference). Treat auth_code-only Bearer as legacy/ping-only fallback.
2. **`IatiConfigResolver`** — Require `secret` for active IATI connections (or fail fast with clear admin message), matching reference `c1`+`c2` requirement.
3. **`IatiClient`** — Always obtain token through auth service exchange path for POST (search/fare/book). Keep 401 → refresh retry.
4. **`Organization-Id` header** — Run probe with/without; reference omits it on flight calls. Make header conditional or remove from search if probe shows unnecessary.
5. **`IatiPayloadBuilder::paxList()`** — Omit CHILD/INFANT when count is 0 (parity with reference).
6. **`config/supplier_credentials.php` + `IatiSupplierConnectionNormalizer` docblocks** — Update help text: secret required; auth_code is Basic username, not search Bearer.
7. **`IatiHealthCommand`** — Report `bearer_mode=jwt_exchange` after fix; optionally test `/search` smoke.

**Suggested verification after fix:**
```bash
php artisan iati:reference-auth-probe --connection={id}
php artisan iati:test-search --from=LHE --to=DXB --date=2026-07-15
```

---

## I. Risks / unknowns

| Risk | Notes |
|------|-------|
| Secret missing in OTA `supplier_connections.credentials` | Exchange cannot work until secret stored (reference always has c2) |
| `Organization-Id` on OTA | May be required for some tenants; reference prod traffic works without it on search — probe must confirm for org `187570` |
| IP whitelist | Reference errors surface `audit.request_ip` (e.g. `145.223.77.132` in logs); no client-side handling — whitelist is server-side at IATI |
| Ping vs search auth split | IATI may intentionally allow auth_code on ping only; do not assume ping success means search auth is correct |
| `Binham/iati` tree | Not the active IATI flight integration; use `Iati_new` / `public_html` |
| FIRST cabin | OTA maps `first`; reference maps only business/first → `BUSINESS` — minor parity gap |

---

## Direct answers (reference evidence)

| Question | Answer |
|----------|--------|
| Does iati.pk call `/rest/auth/token`? | **Yes** — `GET {auth_base}/token` |
| Basic credentials? | **`auth_code:secret`** (Base64 Basic) |
| Agency ID as username? | **No** — `auth_code` is username |
| Auth Code as password? | **No** — `secret` is password |
| Separate secret/password? | **Yes** — `c2` / `secret`, distinct from auth_code |
| Store JWT/access token? | **Yes** — memory + `api_tokens` table |
| Bearer JWT or Bearer AuthCode on flight API? | **JWT only** (from exchange) |
| `Organization-Id` header? | **No** on flight calls (only in booking contact JSON) |
| Different base URL for search? | **No** — same `flight_base` + `/search` |
| `/search` vs other path? | **`POST /search`** |
| Different body format? | **Same structure**; reference omits zero-count pax types |

---

## OTA classes inspected

- `app/Services/Suppliers/Iati/IatiAuthService.php`
- `app/Services/Suppliers/Iati/IatiClient.php`
- `app/Services/Suppliers/Iati/IatiConfigResolver.php`
- `app/Services/Suppliers/Iati/IatiPayloadBuilder.php`
- `app/Services/Suppliers/Iati/IatiFlightSearchService.php`
- `app/Services/Suppliers/Iati/IatiResponseNormalizer.php`
- `app/Console/Commands/IatiTestSearchCommand.php`
- `app/Console/Commands/IatiHealthCommand.php`
- `app/Support/Suppliers/IatiSupplierConnectionNormalizer.php`
- `config/suppliers.php` (iati section)
- `config/supplier_credentials.php` (iati fields)

## Probe commands

- `php artisan iati:reference-auth-probe` — auth/search header matrix (no credential logging). See `IatiReferenceAuthProbeCommand.php`.
- `php artisan iati:search-payload-probe` — payload shape variants (city, accept_pending, cabin).
- `php artisan iati:reference-payload-replay` — replay **exact** successful Binham debug payloads via OTA JWT; header variants isolate `Organization-Id` / `X-Correlation-ID`. See `IatiReferencePayloadReplayCommand.php`.

---

## J. Working-reference payload parity audit (2026-06-24)

**Symptom (OTA):** JWT exchange OK, `POST /search` reachable, payload variants accepted, but routes return **HTTP 202 FE001** (*No result found for related request*).

### Reference files inspected

| File | Finding |
|------|---------|
| `Binham/Iati_new/modules/flights/iati/helper.php` | `IATI_AUTH_TOKEN` → `GET {auth_base}/token` (Basic auth_code:secret); `IATI_API_REQUEST` → Bearer JWT + `Accept` + `Content-Type` only — **no** `Organization-Id`, **no** `X-Correlation-ID`, **no** `/balance` preflight |
| `Binham/Iati_new/modules/flights/iati/search.php` | Direct `POST /search` with `IATI_SEARCH_PAYLOAD_FROM_POST` — no extra steps |
| `Binham/public_html/modules/flights/iati/helper.php` | Mirror of `Iati_new` |
| `Binham/Iati_new/uploads/iati_search_debug.txt` | 46× HTTP 200 with inventory; 1× HTTP 202 FE001 (same-day LHE→DXB `2026-05-17`) |
| `Binham/public_html/uploads/iati_search_debug.txt` | Same content as `Iati_new` |
| `Binham/iati/` | No Flight v2 client |
| `Binham/ota.binham.pk/` | No IATI flight integration code |

### Exact successful reference payload (LHE→DXB, 61 flights, HTTP 200)

```json
{
  "from_destination": {"code": "LHE", "city": false},
  "to_destination": {"code": "DXB", "city": false},
  "departure_date": "2026-05-30",
  "pax_list": [{"type": "ADULT", "count": 1}],
  "accept_pending": true,
  "cabin_type": "ECONOMY"
}
```

- **Endpoint:** `POST https://api.iati.com/rest/flight/v2/search`
- **Environment:** production (`dev_mode=0`)
- **Headers:** `Authorization: Bearer {jwt}`, `Accept: application/json`, `Content-Type: application/json`
- **Token source:** `GET https://api.iati.com/rest/auth/token` with Basic `auth_code:secret`; cached in `api_tokens` (~86000s)
- **organization_id (c3):** booking contact only — **not** sent on search
- **Reference request_ip:** `145.223.77.132` (Hostinger server)

### Exact successful response shape

```json
{
  "audit": {"test": false, "reference": "…", "request_ip": "145.223.77.132", "service": "search", "timestamp": "…"},
  "result": {
    "departure_flights": [ { "provider_key": "PA-JP", "legs": […], "fares": […] } ],
    "return_flights": []
  }
}
```

HTTP **200**, `raw_length` ~440KB for LHE→DXB.

### Exact difference vs OTA

| Aspect | Reference (iati.pk) | OTA (current) | Parity impact |
|--------|---------------------|---------------|---------------|
| Search payload JSON | See above | `IatiPayloadBuilder` produces **identical** hash for same route/date/pax | **No payload blocker** |
| JWT exchange | Always before flight calls | `IatiAuthService` when secret set | **Aligned** (post-fix) |
| `Organization-Id` header | **Omitted** on search | `IatiClient` sends when configured | **Possible tenant filter** — replay command tests with/without |
| `X-Correlation-ID` | **Omitted** | OTA sends | Unlikely inventory blocker |
| `/balance` preflight | **Not called** | Not called | Same |
| `api.iati.com` prod base | Yes | Yes | Same |
| FE001 on same-day search | Yes (LHE→DXB `2026-05-17`) | User reports all routes | Reference proves FE001 can be **valid empty inventory** for some dates |

**Conclusion:** No material payload or endpoint difference remains vs working reference. If `iati:reference-payload-replay` with `reference_exact` headers still returns FE001 for historical high-inventory routes (e.g. LHE→DXB `2026-05-30`), the blocker is **account entitlement, IP whitelist, or credential pair** — not OTA request shape.

### Replay verification

```bash
php artisan iati:reference-payload-replay --connection=12 --reference=latest --route=LHE-DXB
php artisan iati:reference-payload-replay --connection=12 --reference=lhe-dxb-2026-05-30 --variant=reference_exact
```

Compare `reference_exact` vs `reference_plus_org_header` vs `ota_client_headers`. If only `reference_exact` succeeds → make `Organization-Id` optional on search in `IatiClient`.
