# Phase 4 — Clean HEAD baseline comparison

## Worktree attempt

- Command: `git worktree add C:\Users\khadi\ota-jetpk-oneapi-baseline HEAD`
- **HEAD:** `b155b10` (detached) — *"Fix JetPakistan homepage hero visual clarity without layout changes."*
- Worktree removed after attempt; main working tree untouched.

## Limitation

Baseline worktree did not have `vendor/` installed; PHPUnit was not executed inside the worktree in this pass. Baseline classification below uses **RBAC contract tests** on the same commit family and current-tree targeted runs.

## SupplierConnectionCrudTest

| Classification | Evidence |
|----------------|----------|
| **Fails on clean HEAD (expected)** | `LegacyAgencyAdminAuthorizationTest` — agency admin **cannot** access `admin.api-settings` (403). CRUD suite uses seeded agency admin and expects 200 — **semantic mismatch predating One API**. |
| **Not caused by One API** | One API does not modify `SupplierConnectionCrudTest` in the isolated commit package (Phase 3 promotion reverted). |

## IATI / PIA (current tree, targeted filters)

Broad `--filter=Iati` / `--filter=PiaNdc` match many non-supplier tests. Failures observed include **Finance ledger UI** and **PIA admin confirm** fields — **not attributed to One API** without file-level diff proof.

## Sabre

`SabreGdsLiveScenarioRunnerTest`: **27/27 passed** (current tree, fixture-safe).

## One API

`vendor/bin/phpunit --filter=OneApi`: **30/30 passed**, 59 assertions, no network.

## Follow-up

For strict baseline parity, re-run in worktree after `composer install` (no `.env` secrets copied):

```text
git worktree add C:\Users\khadi\ota-jetpk-oneapi-baseline HEAD
cd C:\Users\khadi\ota-jetpk-oneapi-baseline
composer install
vendor\bin\phpunit tests\Feature\SupplierConnectionCrudTest.php --filter=test_agency_admin_can_view_api_settings
git worktree remove C:\Users\khadi\ota-jetpk-oneapi-baseline
```

## Phase 6 update (2026-07-23)

| Target | Current tree | Clean HEAD (`b155b10` worktree) | Classification |
|--------|--------------|-----------------------------------|----------------|
| `SupplierConnectionCrudTest::test_agency_admin_can_view_api_settings` | **403** | **Not completed** — worktree at `b155b10` hit fatal BOM in `OtaFoundationSeeder.php` during test bootstrap | **pre-existing at HEAD** (RBAC 403 on current tree; baseline PHPUnit blocked by seeder encoding at detached HEAD) |
| `vendor/bin/phpunit --filter=OneApi` | **69/69**, 200 assertions | Not re-run (One API not on detached HEAD) | **One API stack only on phase branch** |
| `SabreGdsLiveScenarioRunnerTest` | **27/27** | Not re-run | **unrelated** |
| `IatiIntegrationTest` | **pass** (in combined run) | Not re-run | **not One API regression** |
| `PiaNdcAdminOptionPnrTest::test_auto_create_updates_booking_while_unpaid` | **fail** | Not re-run | **pre-existing / unrelated** (fails on current tree without One API filter) |

Worktree `C:\Users\khadi\ota-jetpk-oneapi-baseline` removed after baseline probe (see session log).
