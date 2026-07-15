<<<<<<< HEAD
# SPEC.md

## Project Name
Asif Travels OTA (Online Travel Agency)
=======
﻿# SPEC.md

## Project Name
JetPakistan OTA (Online Travel Agency)
>>>>>>> jetpk/main

## Project Goal
Build and maintain this Laravel-based OTA platform with minimal regressions, minimal
unnecessary rewrites, and clear acceptance criteria. Every change must preserve
existing supplier integrations (Sabre, Duffel), booking lifecycle, and admin/agent/customer
flows unless explicitly told otherwise.

## Current Tech Stack
- **Backend:** Laravel 13 (PHP ^8.3)
- **Frontend:** Blade + Alpine.js + Tailwind CSS v3/v4 (via `@tailwindcss/vite`)
- **Build:** Vite 8 (`laravel-vite-plugin`)
- **Database:** SQLite (`DB_CONNECTION=sqlite`, file at `database/database.sqlite`)
- **Auth:** Laravel Breeze + Laravel Socialite (Google, Facebook)
- **PDF:** `barryvdh/laravel-dompdf`
- **Testing:** PHPUnit 12 (unit/feature) + Playwright 1.59 (E2E desktop/mobile)
- **Code Style:** Laravel Pint
<<<<<<< HEAD
- **Hosting/Deployment:** VPS (production URL: `https://ota.haseebasif.com`)
- **Suppliers:** Sabre, Duffel (mock supplier was removed — do not re-introduce)

## Important Existing Architecture
Cursor must preserve the current architecture unless explicitly told otherwise.
=======
- **Hosting/Deployment:** Laravel-compatible Linux VPS. Production credentials, server paths, and operational access details are maintained outside this public repository.
- **Suppliers:** Sabre, PIA NDC, AirBlue, IATI, Duffel and supported direct-airline integrations. Do not reintroduce unsafe mock behavior into production paths.

## Important Existing Architecture
Automation agents must preserve the current architecture unless explicitly instructed otherwise.
>>>>>>> jetpk/main

### Key Folders
- `app/Console/Commands/`: Artisan commands (Sabre/Duffel diagnostics, reports, e2e prep)
- `app/Http/Controllers/{Admin,Agent,Staff,Frontend,Auth}/`: Role-segregated controllers
- `app/Http/Requests/`: FormRequest validation classes (mirror controller namespaces)
- `app/Models/`: Eloquent models (`Booking`, `BookingPassenger`, `SupplierBooking`,
  `Airport`, `Agency`, `User`, `SocialAccount`, `BookingHoldSession`, etc.)
- `app/Services/`: Domain services grouped by concern
<<<<<<< HEAD
  - `Booking/` — booking orchestration, router, precheck, state
  - `Bookings/` — fare hold service
  - `Suppliers/` — `Sabre/`, `Duffel/`, adapters under `Adapters/`,
    `BookingAdapters/`, `TicketingAdapters/`
  - `Communication/` — notification, templates, mailers, sanitizers
=======
  - `Booking/` â€” booking orchestration, router, precheck, state
  - `Bookings/` â€” fare hold service
  - `Suppliers/` â€” `Sabre/`, `Duffel/`, adapters under `Adapters/`,
    `BookingAdapters/`, `TicketingAdapters/`
  - `Communication/` â€” notification, templates, mailers, sanitizers
>>>>>>> jetpk/main
  - `Payments/`, `Documents/`, `Dashboard/`, `Reports/`, `FlightSearch/`,
    `TravelData/`
- `app/Support/`: Presenters, value helpers (Bookings, FlightSearch, Suppliers)
- `app/Data/`: DTOs (Spatie Data style classes: `NormalizedFlightOfferData`, etc.)
- `app/Enums/`: Enum classes (`SupplierProvider`, `BookingDocumentType`,
  `OtaNotificationEvent`)
- `app/Policies/`: Authorization policies
- `routes/`: HTTP route definitions
- `resources/views/`: Blade templates
  - `dashboard/admin/`, `dashboard/agent/`, `dashboard/staff/`
  - `auth/`, `frontend/`, `emails/`, `pdfs/`
- `public/css/`, `public/js/`: Compiled / static front assets
- `database/migrations/`: Schema migrations (timestamps from 2026_05_*)
- `database/seeders/`: `OtaFoundationSeeder`, `AirportAirlineReferenceSeeder`
- `database/factories/`: Eloquent factories
- `config/`: Includes `ota.php`, `ota-flights.php`, `ota-suppliers.php`,
  `supplier_credentials.php`, `suppliers.php`, `services.php`
- `tests/`: PHPUnit feature/unit tests
- `test/e2e/`: Playwright specs (`ota-walkthrough.spec.ts`, `ui-visual-qa.spec.ts`)
- `docs/`: Product overview, phase release notes, deployment, supplier references

### Key Files
- `summary.md`: Agent-oriented map of major files and public APIs; **must stay
  in sync with code** (see non-negotiable #13, Definition of Done, `AGENTS.md`)
- `.env.example`: Canonical env keys (APP, DB, mail, Sabre, Duffel, OAuth providers)
<<<<<<< HEAD
- `composer.json` / `package.json`: Dependency manifests — do not bump versions casually
=======
- `composer.json` / `package.json`: Dependency manifests â€” do not bump versions casually
>>>>>>> jetpk/main
- `routes/web.php`: Web routes (most of the app)
- `routes/api.php`: API routes (if/where applicable)
- `bootstrap/app.php`: Middleware, routing, exception bootstrapping
- `config/ota.php`, `config/ota-suppliers.php`, `config/suppliers.php`,
  `config/supplier_credentials.php`: OTA-specific configuration surface
<<<<<<< HEAD
- `app/Services/Suppliers/SupplierAdapterResolver.php`: Routes provider → adapter
=======
- `app/Services/Suppliers/SupplierAdapterResolver.php`: Routes provider â†’ adapter
>>>>>>> jetpk/main
- `app/Services/Booking/BookingService.php`: Central booking orchestration

## Non-Negotiable Rules
1. Do not rewrite unrelated files.
2. Do not refactor the whole project unless the task explicitly asks for refactoring.
3. Do not remove existing working functionality.
4. Do not change database structure unless the task requires it. When required,
<<<<<<< HEAD
   add a new timestamped migration — never edit historical migrations.
=======
   add a new timestamped migration â€” never edit historical migrations.
>>>>>>> jetpk/main
5. Do not hardcode secrets, API keys, tokens, credentials, or passwords. Use
   `.env` + `config/*.php` + the existing `SupplierConnection` / credential
   storage pattern.
6. Do not change UI layout globally when fixing a small issue.
7. Do not rename routes, controllers, models, components, or database columns
   unless required.
8. Preserve existing naming conventions (PSR-4 namespaces, controller suffix,
   `Service` / `Adapter` / `Presenter` suffixes, kebab-case Blade paths).
9. Preserve existing folder structure.
10. Always prefer the smallest safe change.
11. Do not re-introduce the removed Mock supplier files
    (`MockFlightSupplierAdapter`, `MockSupplierBookingAdapter`,
    `MockSupplierTicketingAdapter`, `MockFlightSupplier`, `OtaE2e`).
12. Do not commit unless the user explicitly asks for a commit.
13. Keep **`summary.md`** accurate when you change code it describes (public APIs,
    file responsibilities, module boundaries). Append a **Changelog** line there
<<<<<<< HEAD
    for notable structural or behavioral shifts. Same changeset as the code —
=======
    for notable structural or behavioral shifts. Same changeset as the code â€”
>>>>>>> jetpk/main
    do not defer doc updates.

## Token-Saving Rules for Cursor
Before editing:
1. Read only files directly related to the task.
2. Summarize what files are relevant.
3. Ask before scanning the whole codebase.
4. Do not repeatedly read the same file unless it changed.
5. Do not paste large unchanged code blocks in chat.
6. Return patches or changed sections only unless full file output is requested.
7. Keep explanations short and implementation-focused.

## Build / Test Commands
Use these where relevant.

```bash
# PHP / Laravel
composer install
php artisan optimize:clear
php artisan route:list
php artisan migrate
php artisan test
vendor/bin/pint --dirty   # style-only on changed files

# Node / Vite
npm install
npm run build
npm run dev

# Playwright (E2E)
npm run e2e:desktop
npm run e2e:mobile
npm run e2e:ui-qa
```

## Definition of Done
A task is complete only when:
- The requested feature/fix is implemented.
- Existing functionality is not broken.
- The relevant build/test command passes, or the reason it cannot be run is
  stated.
- Changed files are listed.
- Any risky assumption is clearly mentioned.
- No unrelated files were modified.
- **`summary.md` (and class docblocks for large touched classes) match the code**
  if the change affects documented modules or public APIs listed there; note in
  the response if nothing in `summary.md` needed updating.

## Response Format Cursor Should Use
For every task, respond in this structure:

### Understanding
<<<<<<< HEAD
Briefly explain the task in 2–4 lines.
=======
Briefly explain the task in 2â€“4 lines.
>>>>>>> jetpk/main

### Relevant Files
List only the files that need inspection or editing.

### Plan
Give a short numbered plan before coding.

### Changes Made
List changed files and what changed.

### Verification
Mention commands run and result (or why they were not run).

### Notes / Risks
Mention anything uncertain, skipped, or requiring manual testing.
<<<<<<< HEAD
=======

>>>>>>> jetpk/main
