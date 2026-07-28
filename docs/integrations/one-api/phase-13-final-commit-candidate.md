# Phase 13 — final commit candidate (review-only)

## Commit-candidate worktree

| Item | Value |
|------|--------|
| Worktree | `C:\Users\khadi\ota-jetpk-oneapi-commit-candidate` |
| Base commit | `b155b10d0b9c5984c645d6aba473d746415cd2e9` |
| Prerequisite commit | **None** |
| Unified patch | `storage/app/one-api-phase-12-unified-review.patch` |
| Patch SHA-256 | `703403D03F745C9F2674BA7F35914B1053702EABF9572A2E403C17B3ED5C2574` |
| Patch path count | **167** |
| Intended commit path count | **167** |

### Path breakdown (manifest)

| Category | Count |
|----------|------:|
| Application (`app/`, `public/js`, DTO) | 95 |
| Config / bootstrap | 10 |
| Views / public | 19 |
| Tests + support | 43 |
| **Total** | **167** |

| Scope | Count |
|-------|------:|
| Dedicated One API | 119 |
| Shared registration / router / comm | 23 |
| Mixed | 25 |

### Diff stat (commit-candidate, tracked modifications only)

```
44 files changed, 423 insertions(+), 36 deletions(-)
```

(+ 123 new untracked paths from patch)

### Excluded paths

- `tests/Unit/Booking/BookingProviderRouterTest.php`
- Sabre-only / CMS / `UI_test` / storage evidence / vendor
- Duplicate `one-api-phase-13-baseline-bom-hygiene.patch` (subset of unified patch)

## Strict apply (Phase 13 verification worktree)

| Step | Result |
|------|--------|
| `git apply --check --verbose` (no `--ignore-whitespace`) | **Exit 0** |
| `git apply` | **Exit 0** (2 EOF whitespace warnings) |
| Applied inventory vs 167 patch paths | **Exact match** (`storage/app/one-api-phase-13-applied-paths.txt`) |

## Tests (isolated worktree, SQLite `:memory:` via `phpunit.xml`, no production `.env`)

| Command | Tests | Assertions | Result |
|---------|------:|-----------:|--------|
| `vendor/bin/phpunit --filter=OneApiAcceptanceRegistryIntegrityTest` | 1 | 288 | **Pass** |
| `vendor/bin/phpunit --filter=OneApiAcceptanceRequirementGateTest` | 1 | 1 | **Pass** |
| `vendor/bin/phpunit --filter=OneApiMatrixTwentyFourCasesTest` | 25 | 122 | **Pass** |
| `vendor/bin/phpunit --filter=OneApiTestMatrixCommandTest` | 4 | 9 | **Pass** |
| `vendor/bin/phpunit --filter=OneApiBookingProviderRouterIntegrationTest` | 4 | 13 | **Pass** |
| `vendor/bin/phpunit --filter=OneApi` | 133 | 781 | **Pass** |
| `vendor/bin/phpunit --filter=SabreGdsLiveScenarioRunnerTest` | 27 | 147 | **Pass** |
| `vendor/bin/phpunit tests/Feature/IatiIntegrationTest.php` | 3 | 6 | **Pass** |
| `vendor/bin/phpunit tests/Feature/PiaNdcAdminOptionPnrTest.php` | 9 | 20 | **1 fail** (baseline) |

Log: `storage/app/one-api-phase-13-phpunit.log`

**Network:** fixture-only / mocked supplier paths; **no live supplier calls**.

**Post-test git:** no new tracked source edits beyond patch set (44 modified + 123 new remain as after apply).

## BOM audit summary

See `docs/integrations/one-api/phase-13-bom-self-containment-audit.md`.

- **No undocumented source normalization** required for Phase 13 PHPUnit.
- Unified patch includes **BOM-only** `OtaFoundationSeeder.php` hunk.

## Staging scripts (do not run on main tree)

- `storage/app/one-api-phase-13-stage-all-patch-files.ps1` (167× `git add -- <path>`)
- `storage/app/one-api-phase-13-stage-all-patch-files.sh`
- Manifest: `storage/app/one-api-phase-13-patch-paths.txt`

## Proposed commit

**Subject:**

```
feat(one-api): integrate Air Arabia and FlyJinnah supplier lifecycle
```

**Body:**

```
Add One API supplier adapter, checkout flow, hold/payment orchestration,
fixture-backed test matrix, and admin connection wiring for Air Arabia /
FlyJinnah (FlyJinnah) via the unified One API integration path.

Includes acceptance registry/gate tests, 24-case workflow matrix, booking
router integration test, sanitized XML/JSON fixtures, bootstrap provider
registration, and shared booking-communication idempotency hunks required
by communication matrix tests.

Excludes generic BookingProviderRouterTest and non–One API worktree changes.

Verified on isolated worktree at b155b10 + unified patch (167 paths):
133 One API tests / 781 assertions; Sabre 27/27; IATI 3/3; PIA baseline
failure unchanged (PiaNdcAdminOptionPnrTest::test_auto_create_updates_booking_while_unpaid).

No live supplier calls in verification.
```

## Exact commit command (user approval only — not executed)

```powershell
cd C:\Users\khadi\ota-jetpk-oneapi-commit-candidate
git status --short
git diff --stat
# After review:
# . storage\app\one-api-phase-13-stage-all-patch-files.ps1   # from repo path copy or invoke with full path
git commit -m "$( @'
feat(one-api): integrate Air Arabia and FlyJinnah supplier lifecycle

Add One API supplier adapter, checkout flow, hold/payment orchestration,
fixture-backed test matrix, and admin connection wiring for Air Arabia /
FlyJinnah via the unified One API integration path.

Includes acceptance registry/gate tests, 24-case workflow matrix, booking
router integration test, sanitized fixtures, bootstrap provider registration,
and shared booking-communication idempotency hunks.

Verified: 133 One API tests (781 assertions), Sabre 27/27, IATI 3/3;
PIA baseline failure unchanged. No live supplier calls in verification.

'@ )"
```

## Review commands

```powershell
cd C:\Users\khadi\ota-jetpk-oneapi-commit-candidate
git diff --stat
git diff --name-only
git ls-files --others --exclude-standard
Get-FileHash C:\Users\khadi\ota-jetpk\storage\app\one-api-phase-12-unified-review.patch -Algorithm SHA256
Compare-Object (Get-Content C:\Users\khadi\ota-jetpk\storage\app\one-api-phase-13-applied-paths.txt) (Get-Content C:\Users\khadi\ota-jetpk\storage\app\one-api-phase-13-patch-paths.txt | ForEach-Object { ($_ -split "`t")[0] })
```

## Status

**Ready for user-approved isolated commit** in commit-candidate worktree. **Not deployed.** **Not pushed.** **Nothing staged** in main tree.
