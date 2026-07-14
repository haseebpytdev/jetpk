# Asif Travels OTA â€” Product overview

This document describes **what the codebase implements today**, separate from sales positioning. For deployment steps see [`staging-deployment.md`](staging-deployment.md).

## Product summary

White-label-style **online travel agency** platform built on Laravel:

- **Public site:** Flight search, results, offer detail, passenger and review steps, confirmation; airport autocomplete; optional guest booking lookup with tokenized access.
- **Back office:** Admin console for the full booking lifecycle: operational queues, payments, refunds, cancellations, supplier-assisted booking, manual PNR, ticketing attempts, PDF documents, communication logs, audits.
- **Suppliers:** Flight offers come from **active per-agency supplier connections**. **Duffel** is the fully wired NDC-style path; additional providers are represented by adapters and configuration but may require credentials and certification before production use.
- **Portals:** Separate authenticated areas for **staff**, **agents**, and **customers** with route middleware enforcing account type and agency context.

## Data and persistence

- **Database:** MySQL/MariaDB or SQLite (local); all core business entities use Eloquent.
- **Supplier credentials:** Stored on `supplier_connections` and **encrypted at rest** (`credentials` cast); not committed to the repo.
- **Documents:** Generated files stored on the **local** disk (private paths under `config/ota.php`); downloads authorized via policies or guest tokens.

## Configuration map

| Concern | Primary config |
| --- | --- |
| Default agency slug, passenger rules, paths | `config/ota.php` |
| Public branding copy | `config/ota-brand.php`, `config/ota-client.php` |
| Supplier marketing / readiness narrative | `config/ota-suppliers.php` |
| Admin credential form fields per provider | `config/supplier_credentials.php` |
| Fixture-style flight snippets (non-runtime demos) | `config/ota-flights.php` |

## Supplier lifecycle (conceptual)

1. **Search:** `FlightSearchService` loads active connections for the agency, resolves `FlightSupplierInterface` per provider, merges markup/pricing.
2. **Offer hold / validation:** Checkout validates offers with the supplier pipeline (`OfferValidationService` and related); unstable-sandbox behavior may be gated by environment flags in `config/ota.php`.
3. **Booking:** `SupplierBookingService` creates supplier-side orders where supported; failures log diagnostics and can trigger notifications.
4. **Ticketing:** `TicketingService` / ticketing adapters â€” capability depends on supplier and agency rules.
5. **Diagnostics:** Structured logs in `supplier_diagnostic_logs` with **redacted** metadata for UI and exports.

## Notifications and scheduled jobs

- **Transactional / operational email:** Central dispatcher (`OtaNotificationService`) respects agency communication settings and templates; payloads are sanitized by scope.
- **Scheduled reports:** `routes/console.php` schedules daily/weekly/monthly reports and monthly ledgers **via Artisan** â€” requires OS-level `schedule:run` (and queue worker if mail is queued).

## Boundaries (honest scope)

| In scope (implemented patterns) | Outside or partial |
| --- | --- |
| DB-backed bookings, payments, refunds, documents | Fully automated PCI card vault |
| Duffel search/book with configured token | Every airline-direct certification |
| Agency-scoped isolation | Full multi-tenant billing product |
| Policy + middleware authorization | Drag-and-drop RBAC admin UI |
| Guest lookup with reference + contact | Anonymous PNR retrieve without verification |

## Related documentation

- [`staging-deployment.md`](staging-deployment.md) â€” server, env, migrate, build, scheduler.
- [`releases/phase-23-staging-package.md`](releases/phase-23-staging-package.md) â€” staged verification snapshot notes.
- [`releases/phase-23c-notification-management.md`](releases/phase-23c-notification-management.md) â€” notification module status and remaining expansion items.

