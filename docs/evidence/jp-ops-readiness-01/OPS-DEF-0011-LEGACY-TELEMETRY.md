# OPS-DEF-0011 — Sanitized legacy guest-token route telemetry

**Date (UTC):** 2026-10-17  
**Branch:** `security/ops-def-0011-legacy-telemetry`  
**Base:** `df0da1ab0fc77093f55fa3686771a590e38cf967`  
**Deploy this loop:** NO

## Status

```text
OPS_DEF_0011_STATUS=SUNSET_TELEMETRY_DEPLOYED_OBSERVATION_ACTIVE
SUNSET_OBSERVATION_ACTIVE=YES
```

Production deploy evidence: `OPS-DEF-0011-LEGACY-TELEMETRY-PROD.md`. Legacy routes remain until an observation window shows zero successful legacy usage (or owner waiver). Not closed.

## Mechanism

| Field | Value |
|-------|-------|
| `EXISTING_STRUCTURED_LOGGING` | YES (`Log::info` structured context) |
| `EXISTING_METRICS_BACKEND` | NONE dedicated for this surface |
| `RECOMMENDED_TELEMETRY_MECHANISM` | Middleware on legacy routes only + small helper |
| `TELEMETRY_STORAGE` | Application log (`laravel.log` / stack) |
| `DATABASE_MIGRATION_REQUIRED` | NO |

**Files:**

- `app/Support/GuestBooking/LegacyGuestTokenUsageTelemetry.php`
- `app/Http/Middleware/LogLegacyGuestTokenUsage.php`
- `bootstrap/app.php` alias `guest.legacy.telemetry`
- `routes/web.php` — middleware attached to 6 legacy routes only

## Allowed telemetry fields

```text
event=guest.legacy_token.usage
route_category=guest_detail_exchange|payment_proof|promo_apply|promo_remove|abhipay_start|cancellation
method=GET|POST|…
outcome=success|denied|validation_failure|other_failure
```

Timestamp is supplied by the logging system (not a custom field).

## Prohibited fields (confirmed)

Never stored: raw token, token hash, booking reference/ID, document ID, customer/agency ID, email, phone, passenger data, IP, User-Agent, Referer, cookie, session ID, request URI, query string, request body, payment reference, promo code, cancellation reason, Authorization header.

Outcome is classified from **HTTP status only**.

## Route map

| Route name | Category |
|------------|----------|
| `guest.bookings.exchange` | `guest_detail_exchange` |
| `guest.bookings.payment-proof.legacy` | `payment_proof` |
| `guest.bookings.promo.apply.legacy` | `promo_apply` |
| `guest.bookings.promo.remove.legacy` | `promo_remove` |
| `guest.bookings.abhipay.start.legacy` | `abhipay_start` |
| `guest.bookings.cancellations.store.legacy` | `cancellation` |

Clean token-free routes do **not** attach this middleware.

## Flood control

```text
TELEMETRY_FLOOD_RISK=MODERATE (invalid-token scanners)
TELEMETRY_RATE_CONTROL=Cache gate max 30 writes / route_category / UTC minute
```

Existing route throttles remain unchanged.

## Sunset metric

```text
START_MEASUREMENT_AFTER_DEPLOYMENT=YES
SUNSET_REQUIRES_ZERO_SUCCESSFUL_LEGACY_USAGE_OVER_APPROVED_WINDOW=YES
```

Prefer counting `outcome=success` by `route_category` after deploy. Window length chosen once real volume is visible (not invented here).

## Tests

| Suite | Result |
|-------|--------|
| `LegacyGuestTokenUsageTelemetryTest` | PASS |
| `GuestLegacyTokenTelemetryPrivacyTest` | PASS |
| `GuestTokenHardeningTest` | PASS |
| `GuestBookingDetailJsonTest` | PASS |

Privacy assertions use synthetic token `TEST_RAW_TOKEN_DO_NOT_LOG_123456789` and confirm it never appears in telemetry payloads.

## Next required action

1. ChatGPT review this runtime PR (do **not** auto-merge without review).
2. After merge: scoped production deploy of telemetry-only files.
3. Observe successful legacy usage; then Phase-2 hard remove when window is clean.
