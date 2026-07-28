# Phase 10 — BOM-normalized regression baseline

Label: **Committed HEAD with BOM-only test-run normalization** (worktree `C:\Users\khadi\ota-jetpk-oneapi-baseline`, detached `b155b10`).

## OtaFoundationSeeder.php

| Check | Raw committed HEAD (worktree) | Current working tree |
|-------|------------------------------|----------------------|
| SHA-256 | `3332D53EFF8ABF2D7F072CF57536F303E9309140DE1555E39BD4C5E84C2A10BB` | `3332D53EFF8ABF2D7F072CF57536F303E9309140DE1555E39BD4C5E84C2A10BB` |
| First bytes (hex) | `3C 3F 70` (`<?p`) | `3C 3F 70` |
| UTF-8 BOM | **Absent** | **Absent** |

No BOM strip was required; normalized baseline equals raw HEAD for this file.

## Key regressions (three-way)

| Test | BOM baseline (b155b10) | Current tree | Classification |
|------|------------------------|--------------|----------------|
| `SupplierConnectionCrudTest::test_agency_admin_can_view_api_settings` | **403** | **403** (23/26 suite failures — platform-admin policy) | **Pre-existing after BOM normalization** — not One API |
| `IatiIntegrationTest` | **3/3 pass** | **3/3 pass** | Unrelated stable |
| `PiaNdcAdminOptionPnrTest::test_auto_create_updates_booking_while_unpaid` | **fail** | **fail** | **Pre-existing at HEAD** — not introduced by One API |
| `SabreGdsLiveScenarioRunnerTest` | (not re-run on baseline; no One API on HEAD) | **27/27 pass** | Fixture-safe Sabre |
| `vendor/bin/phpunit --filter=OneApi` | N/A (One API not on HEAD) | **129/129 pass, 0 risky** | One API branch scope |

## One API involvement

Baseline HEAD does not contain One API application code. SupplierConnection and PIA failures reproduce on **both** baseline and current tree → **not One API regressions**.

Worktree removed after evidence capture.
