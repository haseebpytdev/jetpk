# Phase 10 — 24-case matrix evidence

## PHPUnit (current tree)

| Command | Tests | Assertions | Result |
|---------|-------|------------|--------|
| `vendor/bin/phpunit tests/Feature/Suppliers/OneApiMatrixTwentyFourCasesTest.php` | 25 | 122 | **PASS** |
| `vendor/bin/phpunit tests/Feature/Console/OneApiTestMatrixCommandTest.php` | 4 | 9 | **PASS** |

`OneApiMatrixTwentyFourCasesTest` proves:

- 24 unique workbook case IDs (`test_registry_contains_twenty_four_unique_case_ids`)
- Per-row fixture lifecycle (`test_each_workbook_case_passes_fixture_lifecycle` × 24) including PNR/TID/session/settlement assertions in runner
- No external network (`Http::fake` in matrix tests)

`OneApiTestMatrixCommandTest` proves:

- 24-row CSV export (`matrix_exports_twenty_four_fixture_rows`)
- `--case` single row (`matrix_case_filter_runs_single_row`)
- Invalid case non-zero exit (`invalid_matrix_case_returns_non_zero`)
- `--dry-run` (`dry_run_does_not_create_success_booking_row`)

## CLI fixture matrix

PHPUnit drives the command with a factory `SupplierConnection` and writes CSV under `storage/app/one-api-matrix-test/`. Manual CLI against an empty local DB without a seeded connection is **not** used as evidence.

## Network

All matrix evidence uses `Http::fake` and fixture SOAP transport scope — **0 live supplier calls**.
