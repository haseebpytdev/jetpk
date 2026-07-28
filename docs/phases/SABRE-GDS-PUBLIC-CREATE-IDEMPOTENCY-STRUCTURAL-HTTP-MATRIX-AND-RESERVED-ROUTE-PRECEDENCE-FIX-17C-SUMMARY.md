# SABRE-GDS-PUBLIC-CREATE-IDEMPOTENCY-STRUCTURAL-HTTP-MATRIX-AND-RESERVED-ROUTE-PRECEDENCE-FIX-17C — Summary

## Phase name
SABRE-GDS-PUBLIC-CREATE-IDEMPOTENCY-STRUCTURAL-HTTP-MATRIX-AND-RESERVED-ROUTE-PRECEDENCE-FIX-17C

## Branch
`phase/JETPK-HERO-VISUAL-CLARITY-FIX` (working tree; not committed unless requested)

## Objective
Fix production `/admin` shadowing by `client.custom-page.show`, centralize reserved public slugs, prove route/admin auth behavior, document public Sabre create idempotency boundaries, and extend sanitized structural/confirmation tests — **no live Sabre, no production DB mutation**.

## Root cause (production)
- **Declaration (CMS Stage D / commit `0305dec8823228944841382ae51f8be75149f0d4`):** end of `routes/web.php` after `require auth.php`:
  - `Route::get('/{slug}', [ClientManagedPageController::class, 'customShow'])`
  - `->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')`
  - `->name('client.custom-page.show');`
- **Why `/admin` is captured:** `web.php` loads before `bootstrap/app.php` `then` admin routes. The catch-all is registered **before** `admin.dashboard`, and the slug regex **allows** `admin`. Laravel skips later routes once the catch-all matches → `customShow` → CMS 404 shell.
- **Not** missing admin route, Blade 500, or auth-only failure.

## Reserved namespace
Central list: `App\Support\Client\ReservedPublicPath::FIRST_SEGMENT` (admin, agent, staff, customer, account, dashboard, booking, bookings, flights, login, logout, register, password, email, verification, support, contact, api, storage, health, up, dev, devcp, dev-cp, oauth, auth, payment, payments, webhook, webhooks, callbacks, sitemap.xml, robots.txt, plus JetPK static paths). Preview slugs remain in `ReservedClientPreviewSlugs` for `/{clientSlug}` parity.

## Route fix design
1. **`ReservedPublicPath::customPageSlugConstraint()`** — negative lookahead so reserved first segments cannot match `/{slug}`.
2. **`ClientCustomPageRouteRegistrar`** — registers `client.custom-page.show` **after** all system routes in `bootstrap/app.php`; if the route already exists (production `web.php`), **patches** the `slug` `where` constraint.
3. **Controller guard** — `ClientManagedPageReservedSlugs::isReserved()` delegates to `ReservedPublicPath` (defense in depth).
4. Local repo: catch-all **not** duplicated at end of `web.php`; registrar owns registration. **Production:** update existing catch-all `where` to `ReservedPublicPath::customPageSlugConstraint()` (or deploy registrar + remove duplicate).

## Diagnostic APP_URL
`ota:admin-dashboard-forensic-diagnostic` accepts `--simulate-host=jetpakistan.pk` and `--simulate-scheme=https`; applies `URL::forceRootUrl` / `forceScheme` only for `--render-view` HTTP simulation so redirects show live host, not `http://localhost`.

## Public create idempotency (verified)
| Layer | Mechanism |
|--------|-----------|
| Cache lock | `Cache::lock('public-booking-review-submit:{booking_id}', 120)` |
| Session | `PublicBooking::SESSION_BOOKING_ID` |
| Durable | `submitted_at` + `BookingStatus::Draft` gate; `SupplierBookingAttempt` lookup (`create_pnr`, `sabre_public_checkout` source) in `maybeAbortDuplicatePublicSabreBookingSubmit` |
| HTTP proof | `Http::fake()`; `submitted_at` POST → confirmation, **0** HTTP dispatches |
| Live Sabre | Gated by `booking_enabled` **and** `booking_live_call_enabled` |

## Tests (Phase 17C suites)
| Suite | Tests | Assertions |
|--------|-------|------------|
| `ReservedRoutePrecedencePhase17CTest` | 17 | 20 |
| `AdminDashboardRouteResolutionPhase17CTest` | 6 | 14 |
| `ReservedPublicPathPhase17CTest` (Unit) | 10 | 20 |
| `SabrePublicCreateHttpIdempotencyPhase17CTest` | 4 | 11 |
| `SabrePublicCreateStructuralMatrixPhase17CTest` | 3 | 12 |
| `SabrePublicConfirmationOutcomePhase17CTest` | 1 | 2 |
| **Total** | **39** | **79** |

Command: `php artisan test tests/Feature/*Phase17CTest.php tests/Unit/ReservedPublicPathPhase17CTest.php`

## Confirmation UX audit
JetPakistan `confirmation-body` distinguishes PNR present vs pending request; needs-review notice must not claim full confirmation (covered by view test). No UX code change required in this phase.

## Known limitations
- Full HTTP matrix (double-click, two tabs, all itinerary shapes) partially covered; structural tests use existing BFM fixtures + normalizer (not full fake create payload matrix).
- `client_pages` table may be absent locally; custom CMS slug HTTP 200 not exercised (route constraint + admin precedence are).
- Preview `/{clientSlug}` can still win over custom page for arbitrary slugs; reserved segments are protected.

## Production verification (read-only)
```bash
PHP=/opt/alt/php-fpm83/usr/bin/php
APP=/home/pkjetp/jetpk_app
cd $APP
$PHP artisan route:list --name=admin.dashboard
$PHP artisan route:list --name=client.custom-page.show
$PHP artisan tinker --execute="echo app('router')->getRoutes()->match(Illuminate\Http\Request::create('/admin','GET'))->getName();"
curl -sI https://jetpakistan.pk/admin
$PHP artisan ota:admin-dashboard-forensic-diagnostic --auth-state=guest --correlation=17c-guest
$PHP artisan ota:admin-dashboard-forensic-diagnostic --auth-state=platform-admin --user-id=1 --render-view --simulate-host=jetpakistan.pk --simulate-scheme=https --correlation=17c-admin
```

## Rollback
Restore prior `routes/web.php` catch-all `where`, remove `ReservedPublicPath` / registrar bootstrap line, `route:clear`, `config:cache`, `route:cache`.

## Final status
Phase 17C implementation and automated tests **pass** locally. Production deploy pending SFTP of runtime files only.

## Commit SHA
Not committed (user did not request).
