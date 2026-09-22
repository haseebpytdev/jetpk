# Ameer-e-Millat Group Flight API

JetPakistan integration for Ameer-e-Millat group inventory and post-payment booking.

## Overview

| Item | Value |
|------|-------|
| Provider key | `ameer_e_millat` |
| Public ID prefix | `AEM-` |
| Channel | Group ticketing |
| Default base URL | `https://fsdameeremillattourism.com` |
| Module flag | `ota_client.modules.ameer_e_millat_group_ticketing` |
| Supplier config | `config/suppliers.php` → `ameer_e_millat` |

## API paths

All paths are relative to the configured base URL.

| Operation | Method | Path |
|-----------|--------|------|
| Login | POST | `/api/login` |
| Profile probe (test connection) | GET | `/api/user` |
| Sectors | GET | `/api/available/sectors` |
| Airlines | GET | `/api/available/airlines` |
| Groups search | GET | `/api/available/groups` |
| Group detail | GET | `/api/group/detail/{id}` |
| Available seats (primary) | GET | `/api/available/seats/{id}` |
| Available seats (legacy fallback) | GET | `/api/check/available_seats/{id}` |
| Create booking | POST | `/api/create/booking` |
| Show booking | GET | `/api/show/booking/{id}` |

Legacy seats path is used only when the primary seats endpoint returns HTTP 404 or 405.

## Authentication

Admin → API Settings supports three modes (mirrors Al-Haider):

1. **manual_token** — paste a bearer token and optional expiry.
2. **managed_token** — admin-managed token with optional auto-renew credentials.
3. **credentials_auto_token** — email/password; client performs login and caches JWT.

Resolution order:

1. Active `supplier_connections` row for `ameer_e_millat`
2. Optional env `AMEER_E_MILLAT_API_TOKEN`
3. Cached dynamic login from env or connection email/password

Manual/managed tokens fail closed on HTTP 401 (no silent re-login). Auto mode clears cache and retries once after 401.

**Never commit or log tokens, passwords, or full bearer values.**

## Seats discrepancy

List/detail payloads may expose seat counts under different keys (`available_no_of_pax`, `available_seats`, `seats`, leg-level `seats`). Normalizer prefers direct row fields, then minimum leg seats. Live seats endpoint may differ from list snapshot; checkout revalidation refreshes inventory from detail + optional seats call.

## Booking semantics

| Capability | Ameer-e-Millat |
|------------|----------------|
| Pre-payment supplier hold | No — local hold only |
| Post-payment supplier booking | Yes (when `booking_enabled`) |
| Cancel / release at supplier | No |
| Booking retrieve | Yes (`show booking`) |

Flow:

1. Customer reserve → local `held_seats` increment, `provider_hold_status=local_hold_post_payment`.
2. Manual payment submitted → admin review.
3. Admin verify payment → single `create/booking` call; idempotent if `supplier_booking_id` already stored in meta.
4. Failed supplier booking leaves booking in manual review with reconciliation flags.

## Admin configuration

1. Admin → API Settings → add **Ameer-e-Millat** provider card.
2. Choose auth mode and credentials.
3. Set status/environment; base URL defaults to provider default unless advanced override is enabled.
4. **Test connection** performs GET `/api/user` (non-mutating) and stores `last_tested_at`, `last_test_status`, `last_error` without token leakage.

Env fallbacks (optional, non-production preferred):

- `AMEER_E_MILLAT_API_ENABLED`
- `AMEER_E_MILLAT_API_BASE_URL`
- `AMEER_E_MILLAT_API_EMAIL` / `AMEER_E_MILLAT_API_PASSWORD`
- `AMEER_E_MILLAT_API_TOKEN`
- `AMEER_E_MILLAT_BOOKING_ENABLED`

## Inventory sync

```bash
php artisan group-ticketing:sync-inventory --provider=ameer_e_millat
php artisan group-ticketing:sync-inventory --all
```

- Sync keys inventory by `supplier` + `supplier_package_id` (collision-safe vs Al-Haider).
- Provider failure deactivates only that supplier's stale rows; other providers are unaffected.
- Al-Haider (`alhaider`) continues independently when both modules are enabled.

## Rollback

1. Disable connection in Admin API Settings or set `AMEER_E_MILLAT_API_ENABLED=false`.
2. Disable module: `OTA_MODULE_AMEER_E_MILLAT_GROUP_TICKETING=false`.
3. Redeploy previous application SHA via protected backup/stage/deploy workflow.
4. Optionally deactivate `ameer_e_millat` inventory rows (`is_active=false`) without deleting booking history.
5. Post-payment bookings already confirmed at supplier require manual reconciliation with Ameer-e-Millat; JetPakistan does not call cancel.

## Code map

| Area | Location |
|------|----------|
| HTTP client | `app/Services/Suppliers/AmeerEMillat/AmeerEMillatClient.php` |
| Adapter | `app/Services/Suppliers/AmeerEMillat/AmeerEMillatGroupTicketAdapter.php` |
| Normalizer | `app/Services/Suppliers/AmeerEMillat/AmeerEMillatPackageNormalizer.php` |
| Connection normalizer | `app/Support/Suppliers/AmeerEMillatSupplierConnectionNormalizer.php` |
| Registry | `app/Services/GroupTicketing/GroupTicketSupplierRegistry.php` |
| Post-payment finalize | `app/Services/GroupTicketing/GroupReservationService.php` |
