# Phase 13 — BOM self-containment audit

## Question

Phase 12 recorded **UTF-8 BOM stripping on baseline PHP** before PHPUnit, without listing every file or proving no extra source edits. Phase 13 re-audits against base `b155b10d0b9c5984c645d6aba473d746415cd2e9` and unified patch `storage/app/one-api-phase-12-unified-review.patch` (SHA-256 `703403D03F745C9F2674BA7F35914B1053702EABF9572A2E403C17B3ED5C2574`).

## Evidence reviewed

| Source | Finding |
|--------|---------|
| `docs/integrations/one-api/phase-12-unified-patch-audit.md` | Claims worktree-only BOM strip; no file list |
| `docs/integrations/one-api/phase-7-regression-baseline.md` | Names `database/seeders/OtaFoundationSeeder.php` as PHPUnit bootstrap fatal |
| `storage/app/one-api-git-diff-name-status.txt` (Phase 3) | Main tree had seeder diff BOM-only vs `b155b10` |
| `storage/app/bom-scan-b155b10-git-archive.tsv` | Full **24** tracked `.php` paths with UTF-8 BOM at `b155b10` in git objects |
| Fresh worktree `C:\Users\khadi\ota-jetpk-oneapi-phase13-check` | Strict `git apply --check` + `git apply`; **no manual BOM edits** |
| `storage/app/one-api-phase-13-phpunit.log` | Registry + full One API suite passed without post-apply source edits |

## UTF-8 BOM at base `b155b10` (git object scan)

Scan method: `git archive b155b10` → enumerate `.php` paths starting with bytes `EF BB BF`.

| Path | SHA-256 (with BOM) | First bytes (hex) | In unified patch? | Classification |
|------|-------------------|-------------------|-------------------|----------------|
| `database/seeders/OtaFoundationSeeder.php` | `AE09EFF9A6BC077B1AC76AFD59AE92587BA777053E7AA39134E5E4BF1ABE3988` | `ef bb bf 3c 3f 70` | **Yes** (BOM-only hunk) | **A** |
| `app/Support/Client/ClientPublicWebrootPath.php` | `c1da2a5f…` | `efbbbf` | Yes (modified) | **A** |
| `app/Support/Emails/JetpkEmailBrandingResolver.php` | `12e4cf88…` | `efbbbf` | Yes (modified) | **A** |
| `config/ota-brand.php` | `f1875f79…` | `efbbbf` | Yes (modified) | **A** |
| `config/ota-client.php` | `9eb43f20…` | `efbbbf` | Yes (modified) | **A** |
| `config/ota-ui.php` | `fe9ee2ef…` | `efbbbf` | Yes (modified) | **A** |
| `config/ota_client.php` | `5927ea5c…` | `efbbbf` | Yes (modified) | **A** |
| `resources/views/dashboard/admin/api-settings/form.blade.php` | `7f50c7cf…` | `efbbbf` | Yes (modified) | **A** |
| `resources/views/dashboard/admin/bookings/partials/detail-body.blade.php` | `252adb09…` | `efbbbf` | Yes (modified) | **A** |
| `resources/views/dashboard/admin/bookings/show.blade.php` | `e938b542…` | `efbbbf` | Yes (modified) | **A** |
| `resources/views/dashboard/admin/index.blade.php` | `aa8aa257…` | `efbbbf` | Yes (modified) | **A** |
| `resources/views/dashboard/admin/markups/form.blade.php` | `1070784c…` | `efbbbf` | Yes (modified) | **A** |
| `resources/views/dashboard/admin/partials/agent-preview-body.blade.php` | `42d9a48c…` | `efbbbf` | Yes (modified) | **A** |
| `resources/views/dashboard/admin/partials/agents-table-rows.blade.php` | `aace4f21…` | `efbbbf` | Yes (modified) | **A** |
| `resources/views/dashboard/admin/users/form.blade.php` | `68d1824f…` | `efbbbf` | Yes (modified) | **A** |
| `resources/views/dashboard/support/_message.blade.php` | `92d82495…` | `efbbbf` | Yes (modified) | **A** |
| `resources/views/dashboard/support/_thread.blade.php` | `7290cd67…` | `efbbbf` | Yes (modified) | **A** |
| `resources/views/frontend/booking/partials/confirmation-body.blade.php` | `025c422a…` | `efbbbf` | Yes (modified) | **A** |
| `resources/views/frontend/booking/partials/passenger-details-body.blade.php` | `f61032b3…` | `efbbbf` | Yes (modified) | **A** |
| `resources/views/frontend/booking/partials/review-body.blade.php` | `dd2ef026…` | `efbbbf` | Yes (modified) | **A** |
| `resources/views/frontend/flights/partials/results-page.blade.php` | `7da01336…` | `efbbbf` | Yes (modified) | **A** |
| `resources/views/frontend/partials/tournest-home-main.blade.php` | `91c87ac0…` | `efbbbf` | Yes (modified) | **A** |
| `resources/views/layouts/frontend.blade.php` | `0a82d051…` | `efbbbf` | Yes (modified) | **A** |
| `tests/Feature/Phase22StagingPrepTest.php` | `1f121e8a…` | `efbbbf` | Yes (modified) | **A** |

**Category F (unknown/blocking): none.**

### Seeder normalization detail (only byte-only hunk in patch)

| | Value |
|--|--------|
| Tracked at `b155b10` | Yes |
| Represented in unified patch | Yes (`index 437d900..069cfe6`) |
| Original first bytes | `EF BB BF 3C 3F 70` (`﻿<?php`) |
| Patched first bytes | `3C 3F 70 68 70` (`<?php`) |
| Original SHA-256 (git blob) | `AE09EFF9A6BC077B1AC76AFD59AE92587BA777053E7AA39134E5E4BF1ABE3988` |
| Normalized SHA-256 (no BOM) | `3332D53EFF8ABF2D7F072CF57536F303E9309140DE1555E39BD4C5E84C2A10BB` |
| Semantic content changed | **No** (BOM removal only) |

## Phase 12 “manual BOM strip” vs Phase 13

| Claim | Phase 13 result |
|-------|-----------------|
| PHPUnit required undocumented source normalization | **Not on Windows fresh worktree after strict patch apply.** `OneApiAcceptanceRegistryIntegrityTest` and full `--filter=OneApi` passed with **no** manual BOM edits. |
| Normalization before/after patch | Phase 12 docs do not timestamp; Phase 13 order was **patch only**, then `composer install`, then PHPUnit. |
| Why Phase 12 thought strip was needed | Likely **older worktree procedure** (Phase 7 baseline doc) and/or **pre-unified-patch** state before seeder BOM hunk was included at patch line ~8494. |
| Main mixed tree (`C:\Users\khadi\ota-jetpk`) | Seeder already BOM-stripped locally (same `437d900..069cfe6` hunk as patch); unrelated to One API feature code. |
| Windows checkout quirk | Fresh `b155b10` checkout can already present seeder **without** BOM on disk while git index blob still has BOM → `git status` shows BOM-only diff **before** apply; unified patch aligns tree with intended commit content. |

## Other BOM files (not stripped by patch)

Twenty-three additional paths still carry BOM in **pre-patch** git objects but receive **One API semantic hunks** in the unified patch; PHPUnit did not require separate BOM-only edits for them in Phase 13.

## Self-containment verdict

**One API unified patch is independently self-contained** for commit and for Phase 13 verification, including the **BOM-only** `OtaFoundationSeeder.php` hunk. No separate baseline-hygiene prerequisite is required for PHPUnit on the tested path.

Optional review artifact `storage/app/one-api-phase-13-baseline-bom-hygiene.patch` duplicates only the seeder hunk for documentation; **do not apply in addition to the unified patch.**
