# Phase 12 — Unified patch audit

## Artifact

| Field | Value |
|-------|--------|
| Patch | `storage/app/one-api-phase-12-unified-review.patch` |
| Builder | `storage/app/build-one-api-phase-12-unified-patch.ps1` |
| Base commit | `b155b10d0b9c5984c645d6aba473d746415cd2e9` |
| SHA-256 | `703403D03F745C9F2674BA7F35914B1053702EABF9572A2E403C17B3ED5C2574` |
| Diff paths (`git apply --stat`) | **167** |
| Insertions / deletions | **11018** / **36** |

## Scope vs Phase 11

- Single patch replaces Phase 11 main + providers supplement + post-apply file sync.
- Includes **bootstrap/providers.php** (`OneApiServiceProvider` registration).
- Includes all dedicated runtime paths from `one-api-phase-10-new-files.txt` (reservation read builders/orchestrator, Phase 10 inventory command).
- Includes tests, fixtures, acceptance registry/map, `OneApiBookingProviderRouterIntegrationTest`.
- Includes **`app/Services/Communication/BookingCommunicationService.php`** One API idempotency hunks (required for communication matrix tests).
- **bootstrap/app.php**: One API JSON exception handler only; **CMS `ClientCustomPageRouteRegistrar` hunks excluded**.

## Clean worktree verification

| Step | Result |
|------|--------|
| `git worktree add … b155b10` | OK |
| `git apply --check` (strict, no `--ignore-whitespace`) | **Exit 0** after `git clean -fd` |
| `git apply` | **Exit 0** (whitespace EOF warnings only) |
| Patched-worktree `OneApi` suite (scoped dirs) | **133 tests, 781 assertions, 0 failed** (~705s) |
| Registry integrity | **288 assertions, pass** |
| Acceptance gate | **pass** |
| 24-case matrix | **25 tests, pass** |
| Matrix command | **4 tests, pass** |
| Router integration | **4 tests, 13 assertions, pass** |
| Sabre `SabreGdsLiveScenarioRunnerTest` | **27/27 pass** |
| IATI `IatiIntegrationTest` | **3/3 pass** |
| PIA `PiaNdcAdminOptionPnrTest::test_auto_create_updates_booking_while_unpaid` | **fail** (baseline-consistent) |

## Local-only test normalization (not in patch)

- `composer install` from lock + copied `vendor/` from main repo (same lock).
- UTF-8 BOM stripped on **baseline** PHP files under `b155b10` worktree ( PHPUnit bootstrap fatals otherwise).
- No production `.env`; `phpunit.xml` SQLite `:memory:`.

## Manifest gaps corrected vs Phase 10 deploy list

Added to unified patch (were missing from `one-api-phase-10-deploy-files.txt` only):

- `app/Services/Suppliers/OneApi/Reservation/OneApiReadRequestBuilder.php`
- `app/Services/Suppliers/OneApi/Reservation/OneApiReadResponseParser.php`
- `app/Services/Suppliers/OneApi/Reservation/OneApiReservationReadOrchestrator.php`
- `app/Console/Commands/OneApiPhase10InventoryCommand.php`
- `app/Services/Communication/BookingCommunicationService.php` (shared)
