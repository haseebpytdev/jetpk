# Iati_new vs OTA Security Risk Register

**Audit date:** 2026-06-08  
**Mode:** Read-only  
**Severity scale:** Critical | High | Medium | Low | Informational

---

## Summary Counts

| Severity | Iati_new | OTA |
|----------|----------|-----|
| Critical | 3 | 0 |
| High | 7 | 1 |
| Medium | 6 | 3 |
| Low | 2 | 4 |
| Informational | 2 | 2 |

---

## Combined Risk Register

| ID | Risk | Project | File | Class/Function | Severity | Evidence | Impact | Recommended fix |
|----|------|---------|------|----------------|----------|----------|--------|-----------------|
| SEC-001 | Public Sabre diagnostic script | Iati_new | `diagnostic.php` | root script | **Critical** | Prints environment, endpoint, PCC; runs OAuth + search | Unauthenticated Sabre recon; PCC disclosure | Remove from deploy; IP-restrict; never copy to OTA |
| SEC-002 | Web-accessible debug payload files | Iati_new | `uploads/sabre_booking_debug.txt`, `sabre_status_debug.txt`, `sabre_import_debug.txt`, `sabre_debug.txt` | `SABRE_CREATE_PNR` log closure, `SABRE_GET_BOOKING_STATUS` | **Critical** | `@file_put_contents` to uploads with PNR, EPR, PCC, raw JSON | PII/credential breach if HTTP serves uploads | Move to private storage; redact; disable in prod |
| SEC-003 | OAuth bearer tokens stored in DB | Iati_new | `api_tokens` table | `$sabre_store_access_token` in `search.php` | **Critical** | Plain bearer tokens persisted | Token replay if DB leaked | Encrypt at rest or cache-only; OTA uses Laravel Cache |
| SEC-004 | Cancel uses legacy module creds not booking account | Iati_new | `modules/flights/sabre/actions/cancel.php` | inline route handler L107–131 | **High** | Loads `modules` not `sabre_accounts` / `booking_data.account_id` | Cancel against wrong PCC; auth failures; data corruption | Retrieve booking account; match OTA `SupplierConnection` from booking |
| SEC-005 | Cancel without getBooking / isCancelable | Iati_new | `actions/cancel.php` | L197–207 | **High** | Direct `cancelBooking` with only `confirmationId` | Failed cancels; ticketed mishandling | Retrieve → eligibility → cancel → retrieve (OTA Phase G) |
| SEC-006 | CORS Allow-Origin `*` on supplier API | Iati_new | `modules/index.php` | gateway bootstrap | **High** | `Access-Control-Allow-Origin: *` on Sabre routes | Browser-origin abuse of supplier endpoints | Restrict origins; require server-side auth |
| SEC-007 | Search returns PHP file/line on error | Iati_new | `modules/flights/sabre/search.php` | JSON error handler | **High** | `display_errors=1`; error JSON includes file/line | Path disclosure; recon | Generic errors to client; log server-side only |
| SEC-008 | test_booking.php in web tree with hardcoded DB | Iati_new | `test_booking.php` | CLI/diagnostic | **High** | `username => root`, `password => ''` | Dev credential exposure | Remove from web root; .gitignore deploy |
| SEC-009 | issue.php echoes full supplier result | Iati_new | `modules/flights/sabre/actions/issue.php` | route handler | **Medium** | Returns `$result` to API client | Internal Sabre response leakage | Return safe summary only |
| SEC-010 | Inconsistent debug redaction | Iati_new | `helper.php` | `SABRE_REDACT_DEBUG_PAYLOAD`, import revalidate log | **Medium** | Some logs write raw `$response` / `$priceRes` | PII in log files | Apply redactor to all log paths |
| SEC-011 | Frontend booking_data as authority | Iati_new | `app/routes/api/flights/bookingRoutes.php` | booking create from POST flight data | **Medium** | Client-supplied flight JSON stored | Price/itinerary tampering if revalidation skipped | Server-side snapshot + mandatory revalidate (OTA pattern) |
| SEC-012 | Sabre passwords in DB plaintext | Iati_new | `sabre_accounts.password`, `modules.c4` | admin CRUD | **Medium** | Password column not encrypted in code | DB breach → Sabre account takeover | Encrypt; rotate; OTA `SupplierConnection` pattern |
| SEC-013 | Public search debug opt-in | Iati_new | `search.php` | `?sabre_debug=1` | **Medium** | Verbose logging to web-writable file | Operational intel leak | Admin-only diagnostics |
| SEC-014 | Live PNR on payment without multi-gate | Iati_new | `app/lib/payment-gateway.php` | post-payment issue | **High** | Auto `SABRE_CREATE_PNR` after wallet payment | Unreviewed live bookings | OTA-style gates + manual review flags |
| SEC-015 | Refund endpoint DB-only but publicly routable | Iati_new | `actions/refund.php` | POST handler | **Low** | No Sabre call but updates financial state | Fraudulent refund requests if unauthenticated | Auth + RBAC (OTA has portal workflow) |
| SEC-016 | error_log with PNR in cancel flow | Iati_new | `actions/cancel.php` | L200 | **Low** | `error_log("... PNR: " . $booking['pnr'])` | PII in server logs | Structured redacted logging |
| SEC-017 | Hardcoded IATA/agency name in NDC payload | Iati_new | `helper.php` | NDC OfferPrice builder | **Informational** | `iataNumber`, agency name constants | Low security; config rigidity | Move to settings/env |
| SEC-018 | jet.iati.pk parallel codebase | Iati_new | `jet.iati.pk/` | Laravel subproject | **Informational** | Second attack surface if deployed | Stale credentials/routes | Inventory deploy; decommission if unused |
| SEC-019 | Production live Sabre booking flags mis-set | OTA | `config/suppliers.php` | env `SABRE_BOOKING_LIVE_CALL_ENABLED` | **High** | Single env flip enables HTTP | Unintended live PNR in prod | CI check; deploy checklist; default false |
| SEC-020 | Cancel production requires explicit confirms | OTA | `SabreInspectGate`, `config/suppliers.php` | inspect commands | **Low** | `CANCEL-LIVE-PROD-PNR` token required | Prevents accidental prod cancel probes | Keep; extend to live service when built |
| SEC-021 | CERT credentials in env only | OTA | `.env.example`, `config/suppliers.php` `cert_stl` | SabreCertTokenProbe | **Low** | Documented env keys | Leak if committed | Never commit .env; mask in UI |
| SEC-022 | Public flight search rate limits | OTA | `PublicFlightSearchSecurity` | search endpoints | **Medium** | Protections exist | DoS/abuse if disabled | Verify enabled in production |
| SEC-023 | Supplier diagnostic log retention | OTA | `SupplierDiagnosticLog` model | DB storage | **Medium** | Safe summaries but volume growth | Long-term PII/metadata retention | Retention policy + purge job |
| SEC-024 | Admin supplier diagnostics RBAC | OTA | `AdminSectionController::supplierDiagnostics` | admin report | **Low** | Behind admin auth | Privilege escalation if misconfigured | Policy tests |
| SEC-025 | Payment callback idempotency | OTA | payment controllers | **Needs manual confirmation** | — | Duplicate booking risk | Audit payment webhook handlers separately |

---

## Auth Comparison Table

| Project | File | Class/Function | Auth endpoint | Credential source | PCC source | Token storage | Retry behavior | Security risk |
|---------|------|----------------|---------------|-------------------|------------|---------------|----------------|---------------|
| Iati_new | `search.php` | `$sabre_get_access_token` | `/v2/auth/token` | `sabre_accounts` / `modules` | account `pcc` | `api_tokens` + request cache | skip account on fail | Medium |
| Iati_new | `helper.php` | `SABRE_AUTHENTICATE_ACCOUNT` | `/v2/auth/token` | `SABRE_BUILD_ACCOUNT_CONFIG` | config array | none (per call) | throw | Medium |
| Iati_new | `cancel.php` | inline | `/v2/auth/token` | `modules` only | `modules.c1` | none | none | **High** |
| OTA | `SabreClient.php` | `getAccessToken` | `/v2/auth/token` | `SupplierConnection` encrypted | `credentials.pcc` | Laravel Cache | single fetch | Low |
| OTA | `SabreEprEncodedCredentials` | encode helper | — | connection credentials | — | — | — | Low |

---

## Unsafe Debug Exposure List (Iati_new — Do Not Copy)

| Path | Exposure type | Contains |
|------|---------------|----------|
| `diagnostic.php` | HTTP script | PCC, token success, search probe |
| `uploads/sabre_booking_debug.txt` | Writable file | PNR create payloads, NDC price responses |
| `uploads/sabre_status_debug.txt` | Writable file | PCC, EPR, getBooking context |
| `uploads/sabre_import_debug.txt` | Writable file | Import PNR raw responses |
| `uploads/sabre_debug.txt` | Writable file | Search parameters, account types |
| `debug_search.log` | Root log | Search debug |
| `dump_schedule.php` | Root script | **Needs manual confirmation** |
| `scratch/` | Dev folder | **Needs manual confirmation** |

---

## Recommended OTA Diagnostic Record Schema

```json
{
  "id": "uuid",
  "booking_id": 123,
  "supplier_connection_id": 1,
  "operation": "revalidate|create_pnr|get_booking|cancel",
  "environment": "cert|production",
  "endpoint_host": "api.platform.sabre.com",
  "endpoint_path": "/v4/shop/flights/revalidate",
  "http_status": 200,
  "duration_ms": 842,
  "safe_summary_category": "PRICE_CHANGED",
  "safe_summary": "Fare increased; customer acceptance required",
  "retryable": true,
  "manual_review_reason": null,
  "pcc_scope": "auth_pcc_only",
  "request_type": "bfm_revalidate_v1",
  "distribution_channel": "gds",
  "created_at": "ISO8601"
}
```

**Never store:** raw passenger names, passports, card data, full Sabre JSON, bearer tokens, client_secret.

---

## Recommended safe_summary Categories (OTA)

| Category | When |
|----------|------|
| `AUTH_INVALID_CLIENT` | OAuth 401/403 invalid_client |
| `TOKEN_EXPIRED` | 401 on API after token use |
| `PCC_MISMATCH` | POS PCC ≠ auth PCC or booking connection mismatch |
| `REVALIDATION_STALE` | Itinerary no longer available |
| `PRICE_CHANGED` | Revalidate/refresh returned different total |
| `SEGMENT_UC` | Host segment UC in PNR response |
| `NO_FARES_RBD_CARRIER` | Fare basis/RBD/carrier rejection |
| `PNR_CREATE_FAILED` | CPNR/createBooking non-success |
| `PNR_RETRIEVE_FORBIDDEN` | getBooking 403 |
| `CANCEL_NOT_ELIGIBLE` | isCancelable false |
| `CANCEL_PAYLOAD_MISSING` | Missing bookingId/signature |
| `TICKETED_REFUND_REQUIRED` | Ticketed; void/refund manual |
| `EMAIL_SEND_FAILED` | Notification transport error |
| `TIMEOUT` | HTTP timeout |
| `UNKNOWN_SUPPLIER_ERROR` | Unclassified |

---

## OTA Controls to Preserve

1. No Sabre debug HTTP routes — Artisan + `SabreInspectGate` only
2. `SupplierConnection` `encrypted:array` credentials
3. `SensitiveDataRedactor` on attempts and diagnostics
4. `SABRE_BOOKING_LIVE_CALL_ENABLED` default false
5. `SABRE_CANCEL_ENABLED` / `cancel_live_call_enabled` default false
6. Public checkout complex itinerary defer (`ComplexItineraryPolicy`)
7. `PublicFlightSearchSecurity` debug fare gating
8. Cancel/ticketing discovery excludes destructive endpoints

---

*End of security risk register.*
