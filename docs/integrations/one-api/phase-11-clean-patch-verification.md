# Phase 11 — Clean patch verification

## Worktree procedure

| Step | Command / path | Result |
|------|----------------|--------|
| Base commit | `b155b10d0b9c5984c645d6aba473d746415cd2e9` | Detached worktree at `C:\Users\khadi\ota-jetpk-oneapi-patch-check-11` (and `-final` for apply proof) |
| Patch (strict) | `storage/app/one-api-phase-11-review.patch` | Regenerated LF diff vs `b155b10` (20 tracked paths) |
| Patch (legacy) | `storage/app/one-api-phase-10-review.patch` | Strict `git apply --check` **fails** on `routes/web.php` trailing context (CRLF); passes with `--ignore-whitespace` |

## `git apply --check` (clean `b155b10` tree)

| Patch | Strict check | Notes |
|-------|--------------|-------|
| `one-api-phase-11-review.patch` | **Exit 0** — all 20 paths check clean | Recommended review artifact |
| `one-api-phase-10-review.patch` | **Exit 1** — `routes/web.php:112` context mismatch (EOL) | Use Phase 11 patch for clean-room apply |

## Patch application (clean tree)

| Patch | Apply | Notes |
|-------|-------|-------|
| `one-api-phase-11-review.patch` | **Exit 0** — 20/20 hunks applied cleanly | Verified on fresh worktree |
| `one-api-phase-10-review.patch` | **Exit 0** with `--ignore-whitespace` | Same semantic hunks as Phase 11 |

## Full isolated package (post-apply sync)

The `.patch` alone is **not** sufficient for runtime or PHPUnit. After apply, sync from the working tree (same as commit packaging):

1. All paths in `storage/app/one-api-phase-10-deploy-files.txt`
2. All paths in `storage/app/one-api-phase-10-tests.txt`
3. Plus: `bootstrap/providers.php`, `resources/views/dashboard/admin/api-settings/partials/supplier-panels/one_api.blade.php`, `public/js/ota-one-api-checkout.js`, `tests/Feature/Suppliers/OneApiBookingProviderRouterIntegrationTest.php`

### Inventory after apply + sync (representative run)

| Metric | Value |
|--------|--------|
| Modified tracked (vs `b155b10`) | **21** files (`git diff --stat` matches patch + `bootstrap/providers.php`) |
| Untracked new runtime/tests | **~56** paths (One API module + fixtures; aligns with deploy manifest + Phase 11 router test) |
| Unrelated Sabre/CMS/UI_test hunks | **None** in patch path set |
| Vendor docs / credentials | **Absent** |

Compare against `docs/integrations/one-api/phase-10-canonical-file-manifest.md`: shared + dedicated + tests disposition **consistent**; evidence files under `storage/app/one-api-phase-*.txt` remain excluded from deploy.

## Patched worktree PHPUnit (isolated config)

Procedure: `phpunit.xml` (`APP_ENV=testing`, SQLite `:memory:`), `Http::fake()` in router integration tests, fixture transport for supplier calls.

| Run | Result |
|-----|--------|
| Registry integrity (`OneApiAcceptanceRegistryIntegrityTest`) | **Pass** on **current working tree** (288 assertions) — content-identical to patched+synced tree |
| Acceptance gate (`OneApiAcceptanceRequirementGateTest`) | **Pass** (1 assertion) |
| Full `--filter=OneApi` | **133 tests, 781 assertions, 0 failed** (~656s) |
| Patched detached worktree (composer `--no-scripts`) | Long-running local bootstrap; **not re-recorded end-to-end in this session**. Apply + file sync verified; test corpus matches current tree. |

**Network:** No live supplier HTTP in One API, Sabre scenario, IATI, or router integration runs (`Http::fake()` / fixture transport).

## Unrelated-hunk scan

Patch paths limited to One API shared integration (enum, routers, adapters, platform modules, config, checkout routes/views). Grep of patch content: **no** `UI_test`, **no** standalone Sabre scenario command edits, **no** CMS/theme-only files. Incidental mentions of `pia_ndc` / `iati` are registration lines only.

## SHA-256

| Artifact | SHA-256 |
|----------|---------|
| `one-api-phase-11-review.patch` | `A65DADC01B667D0BE3725EB8289A043A570AEF604ED2872668E769C14F3D7E9F` |

## Temporary worktrees

Removed after verification: `oneapi-patch-check`, `oneapi-patch-check-11`, `oneapi-patch-check-final` (when remove succeeded). Remaining auxiliary worktrees unrelated to this phase: `ota-jetpk-baseline-624f3dd`, `oneapi-head-router`.
