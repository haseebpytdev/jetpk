# Platform Module Control (8-series handover)

Sprint **8M–8R** adds deployment module registry, Developer CP planned states, route guards, backend hard stops, provider-specific filtering, and deployment presets. This document is the **8R** QA checklist, production upload list, rollback plan, and known limitations.

**Not in scope:** restoring `/admin/platform/modules` (must stay **404**). Platform admins must **not** access `/dev/cp` without a Developer CP session.

---

## Architecture (short)

| Layer | Mechanism |
|-------|-----------|
| Registry | `PlatformModuleRegistry` — module keys, dependencies, protected flags, presets |
| Persistence | `platform_module_settings` via `PlatformModuleSettingsService` |
| Nav | `PlatformModuleGate::visible()` |
| Routes | `platform.module:{key}` middleware → `EnsurePlatformModuleRouteEnabled` |
| Services | `PlatformModuleEnforcer` — exceptions + safe blocked messages |
| Dev CP | `/dev/cp/modules` — planned toggles, presets, reset, emergency reset |

Protected modules (cannot be disabled via Dev CP): `admin_portal`, `platform_module_control`, `developer_control_panel`.

Env flags (e.g. `SABRE_TICKETING_ENABLED`) remain **additional** gates; DB module state is AND, not a replacement.

---

## Automated tests (8R)

Run after any deploy touching this area:

```bash
php artisan test --filter=PlatformModule
php artisan test --filter=DeveloperControlPanel
php artisan test --filter=PlatformModuleControl
vendor/bin/pint --dirty
```

**8R run (2026-06-03):**

| Filter | Result |
|--------|--------|
| `PlatformModule` | 159 passed |
| `DeveloperControlPanel` | 17 passed |
| `PlatformModuleControl` | 4 passed |

**Broader suites (informational only — not 8-series gates):**

| Filter | Result | Note |
|--------|--------|------|
| `Agent` | 370/434 passed | Many failures are unrelated admin/agent UI tests; filter is overly broad |
| `Customer` | 122/130 passed | Mostly auth/email-verification expectations, not module control |
| `Admin` | See CI/local | Large suite; run selectively before release |

Full manual browser QA (desktop/tablet/mobile across portals) is **required** after upload; see checklist below. Do **not** run live supplier booking or ticketing on production unless explicitly approved.

---

## Manual QA checklist

Use **local/staging** only. Toggle modules in Dev CP, then verify behavior.

### Developer CP access

- [ ] `OTA_DEVELOPER_CP_ENABLED=true` and allowlisted developer email can log in at `/dev/cp/login`
- [ ] Logout clears developer session
- [ ] `/dev/cp` and `/dev/cp/modules` load for developer session
- [ ] Platform admin (`platform_admin`) **without** dev session → redirected to `/dev/cp/login` on `/dev/cp/modules`
- [ ] `GET /admin/platform/modules` → **404**

### Module registry UI

- [ ] Preset cards show dependency valid/invalid badge
- [ ] Preset cards show planned on/off counts
- [ ] Apply preset → flash mentions modules planned on/off
- [ ] Manual save with invalid dependency → errors, no partial corrupt state
- [ ] Protected modules cannot be set to Disabled
- [ ] **Reset to registry defaults** removes DB overrides
- [ ] **Emergency reset** clears all overrides

### Presets (smoke)

- [ ] **Search only** — public results work; checkout/passengers **403**; supplier booking/ticketing blocked in services
- [ ] **B2B only** — agent search OK; customer checkout off
- [ ] **B2C only** — customer checkout OK; agent portal nav hidden / routes blocked
- [ ] **No supplier booking** — create supplier booking fails safely (no supplier HTTP)
- [ ] **No ticketing** — issue ticket blocked; env Sabre ticketing flag still respected

### Nav hiding (8I)

- [ ] Planned off module → sidebar link hidden in affected portal (admin/agent/customer/staff)

### Route guards (8J)

- [ ] `public_flight_search` off → `/flights/results` **403** (friendly page)
- [ ] `customer_checkout` off → passengers/review **403**; confirmation for existing booking still readable
- [ ] `agent_deposits` off → agent deposit create **403**
- [ ] `api_settings` off → admin API settings routes **403** (no credential leakage on disabled page)

### Backend hard stops (8M–8P)

- [ ] `payment_proofs` off → guest/customer/agent proof POST blocked; no file stored
- [ ] `agent_deposits` off → deposit submit/approve/reject blocked
- [ ] `agent_wallet` off → wallet mutation blocked
- [ ] `supplier_search` off → no adapter search call; empty/safe results
- [ ] `duffel_supplier` / `sabre_gds` / `sabre_ndc` off → matching provider excluded (search/validation/booking/ticketing)
- [ ] `supplier_booking` off → router returns safe failure; no supplier HTTP
- [ ] `ticketing` off → `issueTickets` blocked; `SABRE_TICKETING_ENABLED=false` still blocks Sabre

### Portals (layout smoke — no live Sabre/Duffel book/ticket)

- [ ] Public home + search (desktop + mobile width)
- [ ] Customer portal dashboard
- [ ] Agent portal dashboard
- [ ] Agent staff (if enabled)
- [ ] Staff portal
- [ ] Admin dashboard + bookings list

---

## Production upload list (8M–8Q cumulative)

Upload **single files** via SFTP (OTA App profile). Do not sync `public/` via app profile.

### Core platform

```
app/Support/Platform/PlatformModuleRegistry.php
app/Support/Platform/PlatformModuleGate.php
app/Support/Platform/PlatformModuleEnforcer.php
app/Exceptions/PlatformModuleDisabledException.php
app/Http/Middleware/EnsurePlatformModuleRouteEnabled.php
app/Services/Platform/PlatformModuleSettingsService.php
app/Http/Controllers/Developer/PlatformModuleControlController.php
app/Http/Requests/Developer/UpdatePlatformModuleSettingsRequest.php
app/Http/Requests/Developer/ApplyPlatformModulePresetRequest.php
bootstrap/app.php
```

### 8M — Payment / wallet / deposits

```
app/Services/Payments/BookingPaymentService.php
app/Services/Agent/AgentWalletService.php
app/Services/Finance/ManualWalletAdjustmentService.php
app/Http/Controllers/Agent/AgentDepositController.php
routes/agent.php
routes/admin.php
```

### 8N — Search / checkout

```
routes/web.php
app/Services/FlightSearch/FlightSearchService.php
app/Services/Suppliers/OfferValidationService.php
```

### 8O — Supplier booking / ticketing

```
app/Services/Booking/BookingProviderRouter.php
app/Services/Suppliers/SupplierBookingService.php
app/Services/Suppliers/TicketingService.php
app/Services/Suppliers/Sabre/SabrePnrItinerarySyncService.php
```

### 8P — Provider polish

```
app/Services/Booking/BookingProviderRouter.php
app/Services/Suppliers/SupplierBookingService.php
app/Services/Suppliers/TicketingService.php
app/Services/FlightSearch/FlightSearchService.php
app/Services/Suppliers/OfferValidationService.php
resources/views/developer/platform-modules/index.blade.php
```

### 8Q — Presets

```
app/Support/Platform/PlatformModuleRegistry.php
app/Services/Platform/PlatformModuleSettingsService.php
app/Http/Controllers/Developer/PlatformModuleControlController.php
resources/views/developer/platform-modules/index.blade.php
```

### Views / nav (if not already on server from 8G–8J)

```
resources/views/developer/platform-modules/index.blade.php
resources/views/errors/module-disabled.blade.php
```

(Adjust any layout partials that call `PlatformModuleGate::visible()` if changed in your branch.)

### Docs (optional on server)

```
docs/platform-modules.md
summary.md
```

---

## Server commands after upload

```bash
php artisan route:clear
php artisan cache:clear
php artisan view:clear
```

If routes are cached in production:

```bash
php artisan route:cache
```

---

## Rollback plan

### Application rollback

1. Restore the previous versions of the files listed above from git or server backup (newest to oldest: 8Q → 8P → 8O → 8N → 8M → core).
2. Run `php artisan route:clear`, `php artisan cache:clear`, `php artisan view:clear`.
3. Re-test admin login and one public search URL.

### Planned module state rollback (no file rollback)

1. Dev CP → **Emergency reset** (clears all `platform_module_settings` rows; reverts to registry defaults).
2. Or **Reset to registry defaults** (same effect for overrides).
3. `php artisan cache:clear` on server if behavior does not update.

### Database rollback (only if needed)

```sql
-- Clears planned overrides only; does not drop tables
DELETE FROM platform_module_settings;
DELETE FROM platform_module_setting_changes;
```

Then `php artisan cache:clear`.

---

## Per-sprint file summary

| Sprint | Focus | Primary paths |
|--------|--------|----------------|
| **8M** | Payment proofs, agent deposits/wallet | `BookingPaymentService`, `AgentWalletService`, `ManualWalletAdjustmentService`, `AgentDepositController`, `routes/agent.php` |
| **8N** | Public search, checkout routes + search services | `routes/web.php`, `FlightSearchService`, `OfferValidationService` |
| **8O** | Supplier booking, PNR, ticketing | `BookingProviderRouter`, `SupplierBookingService`, `TicketingService`, `SabrePnrItinerarySyncService` |
| **8P** | Sabre GDS/NDC + Duffel consistency | `PlatformModuleEnforcer`, search/validation/booking/ticketing services, Dev CP blade |
| **8Q** | Deployment presets | `PlatformModuleRegistry`, `PlatformModuleSettingsService`, Dev CP controller/blade |

Earlier sprints **8G–8L** (persistence, nav, route middleware, enforcer infrastructure) should already be on the server if Dev CP module control is in use.

---

## Known limitations

1. **Planned vs enforced:** Dev CP “planned” states drive nav, route middleware, and wired service hard stops. Not every route in the app has `platform.module` middleware yet.
2. **Sabre NDC on search offers:** Offer filtering uses `distribution_channel` on offer arrays; normalized DTO `toArray()` may not always include channel until shop/display merge paths set it.
3. **Developer CP:** Access is env + `developer_users` session, not `developer_control_panel` DB toggle (registry note).
4. **Env vs DB:** Sabre booking/ticketing live HTTP remains gated by env; module off adds a safe block before adapters.
5. **Admin manual payment flows:** Admin record/verify/reject payment (non-proof upload) intentionally not blocked by `payment_proofs` off.

---

## Remaining risks

- Uploading only a subset of 8M–8Q files → partial enforcement or PHP errors.
- Forgetting `view:clear` after Blade changes → stale Dev CP UI.
- Applying **Maintenance lite** or **Search only** on production without stakeholder sign-off → customer/agent checkout disabled.
- Broad PHPUnit filters (`Agent`, `Admin`) are poor regression signals for this feature; use `PlatformModule*` tests.

---

## Recommendations after 8-series

1. Complete **full manual QA** (checklist above) on staging before production toggles.
2. Consider incremental `platform.module` middleware on any high-risk routes still only service-guarded.
3. Add `distribution_channel` to `NormalizedFlightOfferData::toArray()` if NDC search filtering must be airtight at the adapter boundary.
4. Operational runbook: document which preset to apply per deployment profile (B2B partner vs B2C site).

---

## Related tests (local)

```
tests/Feature/Platform/PlatformModulePaymentWalletHardStopTest.php
tests/Feature/Platform/PlatformModuleSearchCheckoutHardStopTest.php
tests/Feature/Platform/PlatformModuleSupplierTicketingHardStopTest.php
tests/Feature/Platform/PlatformModuleProviderControlsTest.php
tests/Feature/Platform/PlatformModulePresetTest.php
tests/Feature/Platform/PlatformModuleHandoverTest.php
tests/Feature/Platform/PlatformModuleRouteGuardTest.php
tests/Feature/Platform/PlatformModuleNavigationVisibilityTest.php
tests/Feature/Developer/PlatformModuleSettingsPersistenceTest.php
tests/Unit/Support/Platform/PlatformModuleEnforcerTest.php
```
