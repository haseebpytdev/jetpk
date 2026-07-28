# Phase 7 — Regression baseline (BOM handling)

## Raw HEAD

- Detached HEAD `b155b10` worktree: PHPUnit bootstrap can fail on **UTF-8 BOM** at start of `database/seeders/OtaFoundationSeeder.php`.
- Record: unmodified HEAD is **not** PHPUnit-startable without encoding normalization.

## BOM-normalized baseline (worktree only)

Procedure (do **not** modify main working tree):

1. `git worktree add C:\Users\khadi\ota-jetpk-oneapi-baseline HEAD`
2. `composer install` (create `database/database.sqlite` if post-install requires it)
3. Record SHA-256 of `OtaFoundationSeeder.php` before change
4. Remove BOM bytes only in worktree copy
5. Run targeted tests (SupplierConnectionCrud, PiaNdcAdminOptionPnr, IATI exact files)
6. Label results **HEAD + temporary BOM-only test-environment normalization**
7. `git worktree remove --force C:\Users\khadi\ota-jetpk-oneapi-baseline`

## Current tree (Phase 7)

| Test | Result | Classification |
|------|--------|----------------|
| `SupplierConnectionCrudTest` (agency admin) | 403 | pre-existing RBAC (not One API) |
| `PiaNdcAdminOptionPnrTest::test_auto_create_updates_booking_while_unpaid` | fail | pre-existing / unrelated |
| `vendor/bin/phpunit --filter=OneApi` | 76 pass | One API phase branch |

Full BOM-normalized comparison **not re-executed end-to-end** in this pass; follow procedure above before attributing PIA/CRUD failures.
