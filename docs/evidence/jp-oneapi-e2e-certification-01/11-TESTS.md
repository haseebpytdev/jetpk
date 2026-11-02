# OneAPI Tests

DATE: 2026-11-02

## Baseline (A0) after date-skew repair

Command: `php artisan test --filter=OneApi --compact`

| Metric | Value |
|--------|-------|
| ONEAPI_BASE_TESTS | 138 |
| ONEAPI_BASE_ASSERTIONS | 804 |
| ONEAPI_BASE_FAILURES | 0 |

Historical expectation matched: ≈138 tests, 0 failures.

## Date skew fix (required for clock 2026-11-02)

Hardcoded Aug/Sep 2026 search dates became past relative to `now()`. Updated to relative `now()->addMonths(2)` in:

- `tests/Unit/Services/Suppliers/OneApi/OneApiAuthAndSearchTest.php`
- `tests/Unit/Services/Suppliers/OneApi/OneApiDormantLiveSearchGateTest.php`
- `tests/Unit/Services/Suppliers/OneApi/OneApiAuthenticationMatrixTest.php`

## Session continuity add-on

Command: `php artisan test --filter=OneApiSessionContinuityCertificationTest --compact`

| Metric | Value |
|--------|-------|
| tests | 5 |
| passed | 5 |
| assertions | 21 |
| failures | 0 |

## Full OneAPI filter after session tests

Command: `php artisan test --filter=OneApi --compact`

| Metric | Value |
|--------|-------|
| ONEAPI_TESTS | 143 |
| ONEAPI_ASSERTIONS | 825 |
| ONEAPI_FAILURES | 0 |
