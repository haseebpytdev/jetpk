# SABRE-GDS-PUBLIC-CREATE-PNR-CANONICAL-PATH-PROOF-AND-ADMIN-ROUTE-AUTH-404-FORENSICS-17B

## Status

Engineering closure for code/tests/diagnostics in this pass. **Production browser 404** classified below; **not** a Blade dashboard defect.

## Workstream A — `runPublicReviewDryRun` call graph

```
POST booking.review (BookingController@review)
  → revalidateCheckoutBeforeConfirmation($booking)
  → [Sabre branch] guards: freshness, soft block, duplicate lock, offer refresh
  → SabreBookingService::runPublicReviewDryRun($booking)
       → SabreGdsAutoPnrContextCompletionService::completeForBooking
       → optional offer refresh
       → SabreOperationalPnrReadiness / SabreVerifiedAutoPnrReadiness
       → SabreGdsPnrCreateStrategySelector::selectForBooking
       → certified route gate (config)
       → mergePublicReviewSabreSnapshotFromBooking (server meta, not POST body)
       → createBooking($snapshot, passengerDataFromBooking, $booking->id, $createOptions)
            → prepareBookingPayload → buildLiveBookingEnvelope → transport when mayPerformLiveSabreBookingCall()
       → finalizePublicCheckoutSabreStorage + attempt/outcome meta
  → BookingService::submitBookingRequest (status transition / confirmation redirect)
```

### Answers

| Question | Answer |
|----------|--------|
| Real Create PNR in production? | **Only when** `suppliers.sabre.booking_enabled` **and** `suppliers.sabre.booking_live_call_enabled` are true (`mayPerformLiveSabreBookingCall()`), plus strategy/readiness/certified-route gates pass. |
| Why “DryRun”? | **Legacy name** — method runs full validation, strategy selection, and `createBooking()`; live HTTP is config-gated inside `createBooking()`. |
| Confirmation without PNR? | **Yes** — checkout can complete locally (`submitBookingRequest`) with `live_call_attempted=false`, needs_review, or dry_run outcomes. |
| Same canonical path as QA? | Public path uses **`createBooking()`** (same builder/transport/classifier stack as admin `createSupplierBooking` after routing). |
| Agent diverge? | **No** — agent uses same `booking.review` POST after `AgentBookingContext` session. |

## Workstream B — `/admin` themed 404

### Expected app behavior (verified in tests)

| Request state | Result |
|---------------|--------|
| Guest `GET /admin` | **302 → login** (not 404) |
| Platform admin | **200** + `ota-dash-overview` |
| Customer / agent / staff | **403** “Access restricted” (themed 403, not 404) |
| `GET /admin/home` | **404** (reserved; not admin dashboard) |

### Browser “Page not found” (JetPakistan 404 shell)

Uses `themes/frontend/jetpakistan/errors/404.blade.php` — produced when Laravel returns **HTTP 404** (`ClientErrorResponseResolver`), **not** 401/403.

**Leading hypotheses for production screenshot:**

1. **Route not matched** — stale `route:cache`, deploy mismatch, or web-server rewrite bypassing Laravel for `/admin`.
2. **Wrong URL** — e.g. `/admin/home`, typo, or CDN/static 404 page with JetPK branding.
3. **Not an auth failure** — unauthenticated `/admin` should **redirect to login** per `bootstrap/app.php` + `OperatorAuthTest`; auth failures are **403** themed.

**Not supported:** treating dashboard Blade as root cause without authenticated 200 reproduction.

### Forensic command fix

`ota:admin-dashboard-forensic-diagnostic` now supports `--auth-state=` and establishes **`Auth::guard('web')->setUser()`** / HTTP kernel simulation for `--render-view`. Prior failure was calling `DashboardController@index` **without** auth → `Gate::authorize` → `AuthorizationException`.

## Tests

```text
php artisan test tests/Feature/AdminRouteAuthForensicPhase17BTest.php
php artisan test tests/Feature/SabreGdsPublicCreatePnrCanonicalPathPhase17BTest.php
→ 12 passed, 34 assertions, Http::fake (no live supplier)
```

## Files changed

- `app/Console/Commands/AdminDashboardForensicDiagnosticCommand.php`
- `tests/Feature/AdminRouteAuthForensicPhase17BTest.php` (new)
- `tests/Feature/SabreGdsPublicCreatePnrCanonicalPathPhase17BTest.php` (new)

## Production read-only SSH (prepare only)

```bash
PHP=/opt/alt/php-fpm83/usr/bin/php
project=/home/pkjetp/jetpk_app
cd "$project"

$PHP artisan route:list --name=admin.dashboard -v
$PHP artisan route:list --path=login

$PHP artisan ota:admin-dashboard-forensic-diagnostic --auth-state=guest --correlation=op-guest-$(date +%s)
$PHP artisan ota:admin-dashboard-forensic-diagnostic --auth-state=platform-admin --render-view --correlation=op-admin-$(date +%s)

grep -E 'admin\.dashboard\.forensic_diagnostic' storage/logs/laravel.log | tail -n 20
```

## Permissions note

`index.blade.php` mode **666** on production is overly writable; recommend **644** for deployed views after SFTP (no broad chmod).

## Safety

No live Sabre calls; Bookings 1–3 and attempts 4/5/7/8 untouched.
