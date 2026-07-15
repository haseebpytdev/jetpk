# JetPK V1 Client UI Completion Roadmap

**Work order:** `JETPK-V1-CLIENT-UI-COMPLETION-ROADMAP-1`  
**Goal:** Make JetPakistan (`jetpk`) a separately deployable client UI package on the shared OTA backend.

## Principles

| Principle | Rule |
|-----------|------|
| Master OTA | Live testing platform — features ship on Master first |
| Shared backend | Common modules, services, suppliers unchanged |
| JetPK isolation | Full UI layer, branding, content, assets, theme files under JetPK paths only |
| Integration flow | Master → QA → JetPK theme adoption |
| Dev CP | Module/plan toggles later (registry exists; client profile seeds modules) |
| Deploy boundary | No other client branding/files required for JetPK deployment |

## Current state (2026-07-03)

| Area | Status |
|------|--------|
| Client profile (DB) | `jetpk` via `ota:seed-jetpakistan-client-profile` |
| Client ops folder | `clients/jetpk/` (this roadmap) |
| Theme views | 19 Blade files — home, about, support, lookup, support submitted + shell |
| Theme assets | 9 CSS/JS under `public/themes/frontend/jetpakistan/` |
| Client assets | `public/client-assets/jetpk-assets/` — scaffolded; upload logo/favicon |
| Components | 14 `x-jp.*` + 6 new library stubs (empty-state, input, modal, table, payment-summary, booking-timeline) |
| Parity routes | 209 GET/HEAD under `/jetpk/*` |
| Dedicated JP page views | ~5 of ~25+ public pages |
| Admin JetPK theme | Registered; 0 views (uses default-admin until dashboard phase) |

Prior phases: `JETPK-CLIENT-PREVIEW-RUNTIME-1` → `HOME-FOUNDATION` → `FULL-PUBLIC-INTEGRATION-1` → `COMPONENT-INTEGRATION-2`.

---

## Work order 1 — Complete JetPK public UI package

### Sprint 1A — Foundation polish (current)

- [x] Roadmap + deployment checklists (`docs/jetpk/`)
- [x] `clients/jetpk/` ops profile
- [x] `jetpk-assets` scaffold + upload README
- [x] Component library stubs (empty-state, input, modal, table, payment-summary, booking-timeline)
- [ ] Homepage final polish (hero spacing, section reveal QA, mobile drawer)
- [ ] Search box final QA (Return/One-way/Multi-city, groups tab, airport autocomplete, date picker, pax/cabin)
- [ ] Upload real logo/favicon/banners to `jetpk-assets`

### Sprint 1B — Static / CMS pages

| Page | Route | Theme view | Status |
|------|-------|------------|--------|
| About | `/jetpk/about-us` | `frontend/about.blade.php` | Done |
| Support | `/jetpk/support` | `frontend/support.blade.php` | Done |
| Support submitted | `/jetpk/support/submitted` | `frontend/support/submitted.blade.php` | Done |
| Booking lookup | `/jetpk/lookup-booking` | `frontend/booking/lookup.blade.php` | Done |
| CMS/policy | `/jetpk/pages/{slug}` | Legacy + JP shell | Needs themed wrapper |
| Request demo | `/jetpk/request-demo` | Legacy + JP shell | Needs themed view |
| Payment result | `/jetpk/payment/*` | Legacy + JP shell | Needs themed views |

### Sprint 1C — Auth & agent registration

Auth uses `client_layout('auth')` → JetPakistan auth shell + Master form markup + `theme.css` ota-* compatibility.

| Page | Status | Next |
|------|--------|------|
| Login | Shell done | Migrate forms to `x-jp.*` |
| Register | Shell done | Migrate forms to `x-jp.*` |
| Forgot/reset password | Shell done | Migrate forms to `x-jp.*` |
| Verify email | Shell done | Themed empty/error states |
| Agent registration (3 pages) | Legacy content | Themed views + `x-jp.*` |

### Sprint 1D — Flight search & booking

| Page | Controller view | JP override needed |
|------|-----------------|-------------------|
| Results | `frontend/flights/results` | Yes — result cards, filters, empty/loading |
| Details | `frontend/flights/details` | Yes |
| Return options | `frontend/flights/return-options` | Yes |
| Passengers | `frontend/booking/passengers` | Yes |
| Review | `frontend/booking/review` | Yes — payment summary component |
| Confirmation | `frontend/booking/confirmation` | Yes — booking timeline |
| Guest access | guest booking views | Yes |

Pattern: `@extends(client_layout('frontend'))` → dedicated `themes/frontend/jetpakistan/frontend/...` via `client_view()` in controllers (same as Home/Support).

### Sprint 1E — Group ticketing

| Page | Status |
|------|--------|
| Group search | Legacy + JP shell |
| Package detail | Legacy + JP shell |
| Umrah groups | Hidden in JP nav; route exists for parity |
| Remaining group flow pages | Themed views in Sprint 1E |

### Sprint 1F — Error / empty / loading states

- Global: `jp-loader` in layout (done)
- Per-page: `x-jp.empty-state` for no results, no bookings, form errors
- Flight search: loading skeleton in results
- 404/503: optional JetPK error pages (defer until deploy)

---

## Work order 2 — JetPK reusable component library

Location: `resources/views/components/jp/` + styles in theme CSS.

| Component | File | Status |
|-----------|------|--------|
| Button | `button.blade.php` | Done |
| Input / form group | `form-group.blade.php`, `input.blade.php` | Done / new |
| Card | `card.blade.php` | Done |
| Alert | `alert.blade.php` | Done |
| Chip | `chip.blade.php` | Done |
| Page hero | `page-hero.blade.php` | Done |
| Fare / route / dest / group / trust cards | `*-card.blade.php` | Done |
| Search controls | Homepage uses Master `ota-hero-flight-search` + `search.css` overrides | Done |
| Airport autocomplete skin | `search-overrides.css` | QA in 1A |
| Passenger selector skin | `search-overrides.css` | QA in 1A |
| Date picker skin | `search-overrides.css` | QA in 1A |
| Result card | `result-card.blade.php` | Sprint 1D |
| Booking timeline | `booking-timeline.blade.php` | Stub — Sprint 1D |
| Payment summary | `payment-summary.blade.php` | Stub — Sprint 1D |
| Table | `table.blade.php` | Stub — Sprint 1D |
| Modal | `modal.blade.php` | Stub — Sprint 1D |
| Empty state | `empty-state.blade.php` | Stub — Sprint 1F |
| Icon | `icon.blade.php` | Done |

See [docs/jetpk/component-library-status.md](jetpk/component-library-status.md).

---

## Work order 3 — Package separation audit

JetPK standalone deploy must include only:

1. **Common backend** — full Laravel app minus other-client UI (see [common-backend-inventory.md](jetpk/common-backend-inventory.md))
2. **JetPK theme views** — `resources/views/themes/frontend/jetpakistan/**`
3. **JetPK components** — `resources/views/components/jp/**`
4. **JetPK public assets** — `public/themes/frontend/jetpakistan/**`, `public/client-assets/jetpk-assets/**`
5. **Client profile** — `clients/jetpk/` + DB seed + `.env` with `OTA_CLIENT_SLUG=jetpk`
6. **Module config** — `clients/jetpk/modules.json` → `OTA_MODULE_*`

**Must NOT deploy / expose for JetPK-only:**

- `clients/haseeb-master/` or other client ops folders (if added)
- `public/client-assets/{other-slug}/`
- Other frontend themes used only by other clients (optional trim on dedicated server)
- Master-only preview routes (optional: disable `CLIENT_ROUTE_PARITY_ENABLED` on production JetPK server)

Full audit: [docs/jetpk/package-separation-audit.md](jetpk/package-separation-audit.md).

---

## Work order 4 — Dev CP readiness (module toggles)

Two systems (documented in [module-toggle-checklist.md](jetpk/module-toggle-checklist.md)):

| Layer | Controls | JetPK initial |
|-------|----------|---------------|
| **Client profile modules** | `clients/jetpk/modules.json` → `OTA_MODULE_*` | Flights-focused; hotels/visa off |
| **Platform modules** | Dev CP → Platform Module Control | Map product areas to registry keys |

Product area → platform module mapping (for later Dev CP plans):

| Product area | Platform module keys (examples) |
|--------------|--------------------------------|
| Flights | `public_flight_search`, `customer_checkout`, `supplier_search` |
| Groups | `group_ticketing`, `al_haider_group_ticketing` (client module) |
| Umrah | `umrah_groups` (if registered) |
| Hotels | `hotels` (client + platform when wired) |
| Tours | CMS / static until module exists |
| Agent portal | `agent_portal`, `agent_wallet` |
| Customer portal | `customer_account` |
| Admin/staff | `admin_panel`, `staff_panel` (client modules) |
| Suppliers | `sabre_gds`, `duffel`, `supplier_booking` |
| Payments | `payment_gateway`, `payment_proofs` |
| Reports | `finance_reports`, reporting modules |

JetPK production should run with `OTA_MODULE_DEV_CP=false` unless platform operator access is required.

---

## Work order 5 — JetPK dashboard phase (after public UI)

Deferred until Work order 1 is consistent across public pages.

| Portal | Theme target | Current |
|--------|--------------|---------|
| Customer | `themes/customer/jetpakistan/` (new) | `default-customer` |
| Agent | `themes/agent/jetpakistan/` (new) | `default-agent` |
| Admin | `themes/admin/jetpakistan/` (registered) | `default-admin` |
| Staff | `themes/staff/jetpakistan/` (new) | `default-staff` |

Use same `tokens.css` / `x-jp.*` components; extend `client_layout()` for each area.

---

## Work order 6 — Export / deployment checklist

All artifacts under **`docs/jetpk/`**:

| Document | Purpose |
|----------|---------|
| [README.md](jetpk/README.md) | Index |
| [file-inventory.md](jetpk/file-inventory.md) | JetPK-only file list |
| [common-backend-inventory.md](jetpk/common-backend-inventory.md) | Shared backend required |
| [public-asset-inventory.md](jetpk/public-asset-inventory.md) | Theme + client assets |
| [seed-profile-checklist.md](jetpk/seed-profile-checklist.md) | DB + JSON profile |
| [env-checklist.md](jetpk/env-checklist.md) | Production `.env` |
| [module-toggle-checklist.md](jetpk/module-toggle-checklist.md) | Module/plan matrix |
| [sftp-deployment-checklist.md](jetpk/sftp-deployment-checklist.md) | Upload procedure |
| [rollback-checklist.md](jetpk/rollback-checklist.md) | Rollback steps |
| [package-separation-audit.md](jetpk/package-separation-audit.md) | Deploy boundary |
| [component-library-status.md](jetpk/component-library-status.md) | Component tracker |

Also see [docs/new-client-deployment-checklist.md](new-client-deployment-checklist.md).

---

## Verification commands

```bash
php artisan ota:seed-jetpakistan-client-profile
php artisan ota:client-preview-runtime-status --client=jetpk
php artisan ota:client-route-parity-status --client=jetpk --target=jetpk
php artisan test --filter=ClientPreviewRoutingTest
php artisan test --filter=ClientAssetResolverTest
php artisan ota:route-page-health-audit --all   # pre-deploy gate
```

Manual QA (master workspace):

1. `/jetpk` → `/jetpk/home` — homepage, search, branding
2. `/jetpk/about-us`, `/jetpk/support`, `/jetpk/lookup-booking`
3. `/jetpk/login`, `/jetpk/register`
4. `/jetpk/flights/results` (search from home)
5. `/jetpk/groups/search`

---

## Sprint execution order

```
ROADMAP-1 (this doc) → 1A polish → 1B CMS → 1C auth → 1D flights/booking → 1E groups → 1F states
                     → component library completion in parallel with 1D–1F
                     → dashboard phase (WO-5) after public UI sign-off
                     → standalone JetPK server cutover using docs/jetpk/* checklists
```
