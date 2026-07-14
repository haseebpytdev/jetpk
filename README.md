# JetPakistan OTA (Laravel)

Laravel **database-backed** OTA for **JetPakistan**: public flight search and checkout, **Duffel**-driven offers when a connection is configured, authenticated **admin / staff / agent / and customer** areas, booking operations, payments and refunds, documents, reports, supplier diagnostics, and a **notification and scheduled reporting** layer (email, agency-scoped settings, and console-scheduled jobs where enabled).

This is a **working application**, not a static mock: bookings, users, agencies, and operational data persist in the database. Scope and maturity vary by module (see **Current status** below).

> **Extended product notes:** [`docs/product-overview.md`](docs/product-overview.md) and release notes under [`docs/releases/`](docs/releases/).

---

## Current status

### Implemented

- **Public site:** home, support, agent registration entry points; **public flight search** and results; **booking checkout** (passenger and review steps) with throttled submissions.
- **Duffel:** configurable per-agency **supplier connection**; search and booking paths use the adapter stack (`SupplierAdapterResolver`, Duffel client/normalizer) when credentials and connection are active.
- **Guest / lookup:** guest booking lookup and time-limited access patterns as implemented in routes and controllers.
- **Authentication:** login, registration, email verification, password reset; role separation via middleware and policies (**platform / agency admin, staff, agent, customer**).
- **Admin:** dashboard, **booking management** (list, filters, detail â€œcommand centerâ€ with tabs), payments, refunds, cancellations, ticketing actions, documents, communications, **API / supplier settings**, agents, applications, commissions, **reports**, **supplier diagnostics**, branding/homepage/media, and safety/checklist-style screens.
- **Staff / agent / customer portals:** live route groups with role-appropriate booking and operations (`/admin`, `/staff`, `/agent`, `/customer`).
- **Persistence:** Eloquent models, migrations; operational data (bookings, payments, documents, communication logs, etc.) stored in the database.
- **Scheduler:** `routes/console.php` registers scheduled tasks (e.g. cleanup, report/ledger commands) when the host runs `php artisan schedule:work` or a system cron.

### Partially implemented

- **Additional GDS / suppliers** beyond Duffel: configuration and abstractions exist; only configured adapters and credentials are operational end-to-end.
- **Card / PSP:** manual and proof-style payment flows are represented in-product; full merchant card capture and PSP reconciliation are not described as complete here.
- **Ticketing:** issue-ticket and itinerary flows depend on **provider capabilities** and certification; some actions are gated or manual where integration is incomplete.
- **WhatsApp / channels:** settings may be present; â€œreadyâ€ does not mean full outbound productization.
- **RBAC UI:** roles/permissions may be documented in UI; **authoritative rules** remain code and policies.

### Planned / needs polish

- Deeper **multi-supplier** parity (second-wave adapters, operational hardening).
- **End-to-end certification** of every path for production traffic (load, observability, runbooks) â€” not claimed as finished in this README.

---

## What this repository includes (modules)

| Area | Description |
| --- | --- |
| **Public flight search** | Search and results; supplier calls via configured connections. |
| **Duffel integration** | Primary integrated path for offers/booking when connection and token are set (Admin â†’ API settings / `SupplierConnection`). |
| **Booking checkout & guest lookup** | Storefront booking steps; guest lookup and access patterns. |
| **Admin dashboard** | Agency-scoped admin routes, KPIs/lists, and settings. |
| **Booking management** | Booking detail, status, notes, supplier booking/PNR, manual PNR, communications, audit context. |
| **Payments & refunds** | Record, verify, reject; refund and cancellation workflows (subject to business rules). |
| **Ticketing & documents** | Ticketing actions and PDF-style documents; download policies apply. |
| **Agents, applications, commissions** | Agent onboarding queue, commissions, ledgers, and related admin screens where enabled. |
| **Reports & supplier diagnostics** | Admin reports, exports, and supplier diagnostic views/logging. |
| **Notifications & reporting foundation** | Email notifications, communication settings, scheduled report/ledger commands (see `routes/console.php` and agency communication settings). |

---

## Main route groups

| Group | File | Purpose |
| --- | --- | --- |
| Public storefront & guest booking | `routes/web.php` | Home, flights, booking, guest lookup |
| Authentication | `routes/auth.php` | Login, register, verification, logout |
| Admin | `routes/admin.php` | Operator console (agency-scoped) |
| Staff | `routes/staff.php` | Staff booking operations |
| Agent | `routes/agent.php` | Agent bookings and commissions |
| Customer | `routes/customer.php` | Customer portal |
| Scheduler | `routes/console.php` | Scheduled commands |

---

## Key directories

| Area | Path |
| --- | --- |
| HTTP controllers | `app/Http/Controllers` |
| Services | `app/Services` (flight, suppliers, booking, payments, documents, communication, reports) |
| Models | `app/Models` |
| Policies | `app/Policies` |
| Migrations | `database/migrations` |
| Views | `resources/views` |
| Product config | `config/ota.php`, `config/ota-brand.php`, `config/ota-suppliers.php`, `config/ota-flights.php`, and related `ota-*.php` files |

---

## Local development

Typical **local** setup (this repo, Windows/macOS/Linux):

- Set **`APP_ENV=local`** in your local `.env` (create from **`.env.example`**; never commit secrets).
- Use **SQLite** for a quick database (`DB_CONNECTION=sqlite` and a `database` path in `.env`, or the default SQLite file your project uses).
- Install PHP dependencies: `composer install`
- Create app key once: `php artisan key:generate`
- Apply schema: `php artisan migrate`
- **Seeding:** run `php artisan db:seed` **only when** you intentionally want foundation or reference data in a local database (not required for every edit session).
- Run the app: `php artisan serve --host=127.0.0.1 --port=8000` â€” then open **http://127.0.0.1:8000**

Run tests: `php artisan test`

Optional: front-end asset pipelines (Vite/npm) and Playwright E2E under `test/e2e` exist for teams that use them; they are **not** listed here as mandatory for describing backend/API correctness.

---

## Staging deployment note

- The **server environment uses its own `.env`** â€” distinct from any developer machine. **Do not commit** `.env` or real credentials.
- **Never** run `migrate:fresh`, `migrate:refresh`, `migrate:reset`, or `db:wipe` on staging or production unless you are executing a deliberate, approved rebuild (these commands destroy data).
- After deployment, use **safe** Artisan optimization commands as appropriate for your host (e.g. clear/config/route/view caches where documented); follow **`docs/staging-deployment.md`** for the full checklist.

Full staging checklist: [`docs/staging-deployment.md`](docs/staging-deployment.md).

---

## Documentation index

- **Staging / production checklist:** [`docs/staging-deployment.md`](docs/staging-deployment.md)
- **Releases / phases:** [`docs/releases/`](docs/releases/)
- **Additional deployment topics:** [`docs/deployment.md`](docs/deployment.md)

---

## Layout conventions

| Area | Layout |
| --- | --- |
| Public OTA | `resources/views/layouts/frontend.blade.php` |
| Dashboards | `resources/views/layouts/dashboard.blade.php` |

---

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT). Third-party themes and assets remain subject to their respective licenses.

