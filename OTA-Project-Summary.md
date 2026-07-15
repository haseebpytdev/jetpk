# OTA Project Summary

**Purpose:** Safe, reusable context for Cursor and handoffs. Sourced from `SPEC.md`, `AGENTS.md`, `summary.md`, workspace rules, and recent agent work (M8E–M8F-Rerun).  
**Last updated:** 2026-06-02  
**Production URL:** `https://ota.haseebasif.com` (Hostinger VPS; app path on server: `/home/u654883295/domains/haseebasif.com/ota_app`)

> **Security:** This file intentionally excludes passwords, API keys, tokens, `.env` values, SFTP credentials, customer PII, and raw logs. QA account emails are listed because they are test-only markers, not secrets.

---

## 1. Safe Project Summary

### What this is

**Asif Travels OTA** — Laravel 13 online travel agency with:

- **Suppliers:** Sabre, Duffel (Mock supplier removed — do not re-introduce)
- **Portals:** Public site, customer, agent (+ agent_staff), staff, platform admin
- **Stack:** PHP 8.3+, Blade + Alpine.js + Tailwind, Vite 8, SQLite locally, PHPUnit + Playwright
- **Booking:** Search → hold/checkout → payments → supplier PNR (Sabre heavily instrumented; **live ticketing disabled** unless explicitly approved)

### Architecture decisions (preserve unless asked)

| Area | Decision |
|------|----------|
| Code layout | Services in `app/Services/`; supplier adapters under `app/Services/Suppliers/`; DTOs `app/Data/`; enums `app/Enums/` |
| Auth / roles | `AccountType`: `platform_admin`, `staff`, `agent`, `agent_staff`, `customer`; legacy `agency_admin` → blocked notice route |
| Staff RBAC | `users.meta.staff_permissions` + `staff.permission:<key>` middleware |
| Agent RBAC | `users.meta.agent_permissions` + `agent.permission:<key>`; commissions owner-only (`agent.admin`) |
| Finance | Wallet = source of truth; parallel **accounting ledger** (read-only UI + live hooks); canonical agency wallet enforcement |
| Sabre public checkout | Certified route selector; complex RT/MC deferred; offer refresh acceptance before PNR; freshness/stale-segment guards |
| Mobile UX | Parallel **mobile app shell** via `MobileViewPreference` + `config/ota-mobile.php` `mobile_pages` map — **do not edit desktop results Blade** unless explicitly asked |
| Docs for agents | `summary.md` must stay in sync with code changes (Changelog + public API tables) |

### Production QA accounts (M8G — test only)

Dedicated agency: **`qa-portal-test`** (QA Portal Test Agency).

| Role | Email |
|------|--------|
| Customer | `qa.customer@ota.haseebasif.com` |
| Agent owner | `qa.agent@ota.haseebasif.com` |
| Agent staff (restricted) | `qa.agent.staff@ota.haseebasif.com` |

- Password: **`OTA_QA_PORTAL_PASSWORD`** on production server `.env` only (never commit or paste in chat).
- Staff grants: `agent.bookings.view`, `agent.travelers.manage` only.
- Meta flags: `qa_portal_test`, `permission_group = M8G QA`.
- Local Playwright: set `OTA_AUDIT_CUSTOMER_EMAIL`, `OTA_AUDIT_AGENT_EMAIL`, `OTA_AUDIT_AGENT_STAFF_RESTRICTED_EMAIL`, `OTA_AUDIT_PASSWORD` (from server env, not repo).

**Local demo users** (seeder): `admin@ota.demo`, `staff@ota.demo`, `agent@ota.demo`, `customer@ota.demo` — password `password` per `UI_test/README.md` (local only; **do not work on production**).

---

## 2. Completed Work

### Production QA (mobile / portal sprints)

| Sprint | Outcome |
|--------|---------|
| **M8E** | Public desktop/mobile, agent registration, flight search, SSH/log sanity — **no public blockers** |
| **M8F** (initial) | Blocked — no production QA logins (demo accounts invalid on prod) |
| **M8G / M8G-Execute** | Created isolated QA agency + 3 portal users via server PHP script (no repo deploy); `OTA_QA_PORTAL_PASSWORD` added server-side |
| **M8F-Rerun** | **Ready for portal/RBAC handover** — customer, agent owner, restricted staff (desktop + mobile shell), negative access 403s, no 500s in scope |

### Mobile shell (2026-06-02)

- **M6A:** Auto device mode + manual `/mobile-view` / `/desktop-view` override
- **M4B:** Public checkout branches (`passengers`, `review`, `confirmation`) to mobile views
- **M7A:** Agent bookings create + travelers index/create/edit on mobile shell
- **M7B:** Agent reports, commissions, finance statement, accounting ledger on mobile shell
- Asset version in `mobile-app.blade.php` (see `summary.md` Changelog for current `?v=`)

### Finance & admin (2026-05-31 – 2026-06-01)

- Double-entry ledger (schema, dry-run, live posting hooks, reconciliation UI)
- Admin finance dashboard, wallet audit, canonical agency wallet, manual adjustments + idempotency/reversal
- Agent finance statements (admin/staff/agent), CSV exports (Finance-Reports 15)
- Duplicate wallet archive (read-only audit + controlled archive)
- `OtaFinanceDemoScenario` + mandatory finance QA rules

### RBAC, agencies, access (2026-05-31)

- Portal routing by account type; staff/agent permission foundations and presets
- Agency Management vs Users & Access; agency reconciliation commands
- Platform admin hardening; legacy `agency_admin` removed from effective grants
- Email **or username** login; Google onboarding for customers

### Sabre / booking (ongoing, inspection-heavy)

- Certified route selector, IATI-like CPNR v2.4, freshness/stale shop guards, PNR failure classification
- Offer refresh + customer acceptance modal; PNR itinerary sync (getBooking); cancel inspect matrix (dry-run / gated send)
- Admin-safe diagnostics panels (no raw payloads in UI)
- **Ticketing remains disabled** for live issue unless explicitly approved

### Public UI / CMS

- Homepage CMS sections, hero, featured fares, footer settings, airline display names, results card presenters
- Horizontal overflow fixes (`ota-public.css` cache bust tracked in `frontend.blade.php`)

---

## 3. Pending Work

### Explicitly deferred (product / roadmap)

From `summary.md` / E-series checkpoint:

- CMS static pages CRUD
- Promo code **checkout** integration (admin CRUD exists)
- Agent **credit enforcement at booking** (wallet exists; no booking-time enforcement)
- WhatsApp integration
- Live **ticketing** planning and enablement
- Sabre: mixed-carrier / multi-segment **public** checkout enablement (inspection-only today)
- Return / multi-city public live PNR (deferred by certified route selector)
- Historical ledger **backfill** (live hooks only; backfill deferred)
- Full **Users & Access RBAC** beyond `agent_staff` toggles (Admin-Access-3 note)

### QA / process still open

| Item | Notes |
|------|--------|
| **Full manual QA** | Per `.cursor/rules/sprint-workflow.mdc` — desktop/tablet/mobile across public, booking, agent, admin, staff **after all sprints**; Playwright audit ≠ final manual pass |
| **Mobile public About** | Log showed missing view `mobile.public.about` (non-blocking for M8F-Rerun); fix if mobile `/about-us` required |
| **UI_test failures** | Many responsive audit screenshots under `UI_test/failures/` — triage per sprint scope, not all resolved |
| **QA account lifecycle** | Inactivate QA users or rotate `OTA_QA_PORTAL_PASSWORD` after QA cycle (see rollback in §7) |
| **Staff/admin mobile shells** | Mobile map focuses on public + customer + agent; admin/staff largely desktop dashboard |

### Optional follow-ups

- Deploy missing mobile About Blade if in scope
- Re-run `npm run ui:audit:responsive` locally after layout sprints
- Finance: review duplicate wallet archive candidates on production (`agent-wallets:audit`)

---

## 4. Important File Paths

### Rules & agent docs

| Path | Role |
|------|------|
| `SPEC.md` | Stack, architecture, non-negotiables, test commands |
| `AGENTS.md` | Agent workflow, cache bust rules, sprint notes |
| `summary.md` | Module map, public APIs, **Changelog** (update with code) |
| `OTA-Project-Summary.md` | This handoff file |
| `.cursor/rules/sftp-live-server-rules.mdc` | Live deploy safety |
| `.cursor/rules/sprint-workflow.mdc` | Per-sprint vs final QA |
| `.cursor/rules/finance-reports-qa.mdc` | Finance sprint test requirements |
| `.cursor/rules/mobile-public-results.mdc` | Mobile results parallel layer |
| `sftpsummary.md` | Auth/register SFTP notes + CSS bust |
| `.vscode/sftp.json` | SFTP profiles (**credentials — never copy to docs**) |

### Core application

| Path | Role |
|------|------|
| `routes/web.php` | Most web routes |
| `routes/staff.php`, agent routes | Staff/agent middleware groups |
| `bootstrap/app.php` | Middleware registration |
| `config/ota.php`, `config/ota-suppliers.php`, `config/suppliers.php` | OTA + supplier config |
| `config/ota-mobile.php` | Mobile shell page map |
| `app/Services/Booking/BookingService.php` | Booking orchestration |
| `app/Services/Suppliers/SupplierAdapterResolver.php` | Provider → adapter |
| `app/Services/Suppliers/Sabre/` | Sabre booking/search clients |
| `app/Support/Mobile/MobileViewPreference.php` | Mobile/desktop shell selection |
| `app/Policies/` | Authorization |
| `app/Support/Finance/OtaFinanceDemoScenario.php` | Finance demo dataset |

### Views & assets

| Path | Role |
|------|------|
| `resources/views/layouts/frontend.blade.php` | Public desktop + `ota-public.css` `?v=` |
| `resources/views/layouts/mobile-app.blade.php` | Mobile shell + shared `ota-mobile-app` `?v=` |
| `resources/views/layouts/dashboard.blade.php` | Admin/agent/staff shell |
| `resources/views/mobile/` | Mobile shell pages |
| `resources/views/frontend/flights/results.blade.php` | **Desktop results — avoid unless asked** |
| `public/css/ota-public.css` | Public CSS (OTA Public SFTP profile) |
| `public/css/ota-mobile-app.css`, `public/js/ota-mobile-app.js` | Mobile assets (manual public upload) |

### Tests & QA artifacts

| Path | Role |
|------|------|
| `tests/` | PHPUnit |
| `test/e2e/` | Playwright walkthrough / visual QA |
| `UI_test/README.md` | Responsive audit commands |
| `UI_test/.auth/` | Playwright storage states (local) |
| `docs/production-cron-smtp-notifications.md` | Cron/queue/mail deploy |

### Deploy profiles (conceptual)

| Profile | Uploads |
|---------|---------|
| **OTA App - Laravel** | `app/`, `routes/`, `config/`, `resources/views/` — `public/` **ignored** |
| **OTA Public - Live Web Root** | `public/css/`, `public/js/` — manual, `uploadOnSave: false` |

---

## 5. Deployment Rules

1. **No bulk sync** — single-file SFTP unless user explicitly approves folder sync.
2. **Turn off `uploadOnSave`** except during a controlled edit pass; set back to `false` when done.
3. **Never upload via App profile for `public/`** — use OTA Public profile for CSS/JS.
4. **Download latest from server** before editing live-touched files (user workflow).
5. **SSH clears after upload** (on server, app directory):
   - Blade/views: `php artisan view:clear`
   - Routes: `php artisan route:clear` (or `route:cache` if prod uses route cache)
   - PHP logic/config: `php artisan cache:clear` if behavior stale
   - Avoid `config:cache` unless ops approves
6. **Cache bust in same change:**
   - `ota-public.css` → bump `?v=` in `layouts/frontend.blade.php`
   - `ota-mobile-app.css` / `.js` → bump shared `?v=` in `layouts/mobile-app.blade.php`
   - `ota-design-system.css` → bump in `layouts/frontend.blade.php` (see `sftpsummary.md`)
7. **Do not edit** `.env`, `vendor/`, `node_modules/`, `storage/` caches without approval.
8. **No live Sabre booking/ticketing/cancel send** unless sprint explicitly approves gated commands.
9. **End every implementation** with: files changed, files to upload, SSH commands, test steps, rollback.
10. **Sprint workflow:** per-sprint automated tests only; defer full cross-portal manual QA until sprint series complete.

---

## 6. Cursor Prompt Style (use next)

Copy and adapt this template:

```
Task: [one sentence goal]

Scope:
- Sprint: [e.g. M7B / Finance-Reports-16 / bugfix]
- Surfaces: [public | customer | agent | admin | staff | mobile shell only]
- In scope files: [max 3 paths unless approved]
- Out of scope: [Sabre live calls | ticketing | wallet mutations | unrelated portals]

Rules:
- Read SPEC.md, AGENTS.md, and skim summary.md for touched modules.
- Smallest safe change only; ask before editing >3 files.
- Do not edit resources/views/frontend/flights/results.blade.php unless I explicitly ask.
- No secrets in code/chat; no .env commits.
- Update summary.md Changelog if behavior/API changes.
- SFTP: list exact upload paths + Artisan clears; public CSS via OTA Public profile.

Verification:
- php artisan test --filter=[TestName]
- [npm run ui:audit:responsive | php artisan serve + local URL only]

Output format: Understanding → Relevant files → Plan → Changes → Verification → Notes/risks
```

**Good habits**

- Name the **route** or **route name** (`agent.wallet.show`) when reporting bugs.
- Say **desktop vs mobile shell** (`MobileViewPreference`, `/mobile-view`).
- For finance: require `OtaFinanceDemoScenario` / `tests/Feature/Finance` per finance-reports-qa rule.
- For production QA: QA-only, no file edits, use `qa.*@ota.haseebasif.com` + server `OTA_QA_PORTAL_PASSWORD` via env (never paste password).

---

## 7. Risks / Blockers

| Risk | Mitigation |
|------|------------|
| **Live server coupling** | SFTP to production; mistakes affect real site — single-file uploads + clears |
| **Sabre complexity** | Many env-gated code paths; default = inspect/dry-run; no guessing contracts |
| **Schema drift prod vs local** | Finance/agency fixes used `Schema::hasColumn()` / `restrictedSelectColumns()` — check production columns before new queries |
| **Mock supplier removed** | Tests must use Sabre/Duffel fakes or certification commands in `local`/`testing` |
| **Mobile/desktop dual UI** | Easy to fix wrong shell; check `ota-mobile.php` and `MobileViewPreference` |
| **QA credentials on server only** | `OTA_QA_PORTAL_PASSWORD` not in repo; rotate/inactivate QA users after testing |
| **sftp.json contains secrets** | Never commit or echo; use SSH keys/password manager separately from docs |
| **Playwright vs manual QA** | Responsive audit can pass while UX issues remain — schedule final manual pass |
| **Ticketing / cancel / PNR send** | Gated by multiple env flags; wrongful enablement = supplier/financial risk |
| **Duplicate `OTA_QA_PORTAL_PASSWORD` lines** | M8G noted possible duplicate `.env` lines — keep one clean entry on server |

### QA account rollback (non-destructive)

```bash
cd /home/u654883295/domains/haseebasif.com/ota_app
php artisan tinker --execute="
foreach (['qa.customer@ota.haseebasif.com','qa.agent@ota.haseebasif.com','qa.agent.staff@ota.haseebasif.com'] as \$e) {
  \$u = App\Models\User::where('email', \$e)->first();
  if (\$u) { \$u->status = App\Enums\UserAccountStatus::Inactive; \$u->save(); echo \"inactive: \$e\n\"; }
}
"
```

---

## Testing Commands (quick reference)

### PHP / Laravel

```bash
composer install
php artisan optimize:clear
php artisan migrate
php artisan test
php artisan test --filter=Finance
php artisan test --filter=Ledger
php artisan test tests/Feature/Ui/MobileViewPreferenceTest.php
vendor/bin/pint --dirty
```

### Node / E2E

```bash
npm install
npm run build
npm run dev

# Local app first:
php artisan serve --host=127.0.0.1 --port=8000

npm run e2e:desktop
npm run e2e:mobile
npm run e2e:ui-qa
npm run ui:audit:responsive
```

### UI audit env (local)

| Variable | Default |
|----------|---------|
| `LOCAL_OTA_URL` | `http://127.0.0.1:8000` |
| `OTA_AUDIT_PASSWORD` | `password` (local seeder) |
| `OTA_AUDIT_AGENT_STAFF_RESTRICTED_EMAIL` | optional |
| `OTA_AUDIT_AGENT_STAFF_FULL_EMAIL` | optional |

Never point audits at production unless explicitly approved (`OTA_AUDIT_ALLOW_REMOTE=1`).

### Production log sanity (SSH — patterns only)

```bash
cd /home/u654883295/domains/haseebasif.com/ota_app
php artisan queue:failed
tail -n 250 storage/logs/laravel.log | grep -E "production.ERROR|View|Undefined|Route|Exception|Too Many|429|Throttle|AuthorizationException|403"
```

### Finance sprints (extra)

```bash
php artisan test tests/Feature/Finance
php artisan db:seed --class=OtaFinanceDemoSeeder
```

### Sabre diagnostics (local/testing — examples)

```bash
php artisan sabre:certify-pnr --booking={id} --dry-run
php artisan sabre:inspect-pnr-retrieve --booking={id}
php artisan sabre:sync-pnr-itinerary --booking={id} --dry-run
php artisan ledger:verify-balances
php artisan agent-wallets:audit
```

---

## Maintenance

- Refresh this file after major sprint series or production QA milestones.
- Prefer `summary.md` Changelog for **code-level** module history; use this file for **handoff and process** context.
