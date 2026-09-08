# Stale fare contract — Closure-05

## Authority

| Field | Value |
|---|---|
| `FARE_CACHE_TTL` | `jetpk_homepage.fare_freshness_hours` (default **30 hours**) |
| `FRESHNESS_AUTHORITY` | `JetpkHomepageFareDisplay::isFresh()` compares `fare_refreshed_at` against TTL |
| `LAST_REFRESH` | `_fare_cache.routes|destinations[*].fare_refreshed_at` (ISO-8601) |
| `FAILED_REFRESH_BEHAVIOR` | Cache may retain previous valid fare metadata with `fare_status=failed`; **public display does not use stale amounts** when `allow_stale_fare_display=false` |
| `STALE_PRICE_PUBLICLY_VISIBLE` | **NO** — stale or missing fresh cache resolves to neutral label |

## Public label contract

- Fresh valid cache with positive `resolved_fare`: show formatted PKR label (e.g. `PKR 54,000`).
- No fresh valid cache: show **`Check fare`** via `JetpkHomepageFareDisplay::neutralAvailabilityLabel()`.
- Config default: `allow_stale_fare_display=false` (`config/jetpk_homepage.php`).

## Regression tests

| Case | Test |
|---|---|
| Fresh cache | `JetpkHomepageFareDisplayStaleLabelTest::test_fresh_cache_labels_as_success_cheapest` |
| Expired cache | `JetpkHomepageFareDisplayStaleLabelTest::test_stale_cache_not_labeled_current_when_stale_display_disabled` |
| Neutral label | `JetpkHomepageFareDisplayStaleLabelTest::test_neutral_label_is_check_fare` |
| Failed refresh preserves cache but not display | `JetpkHomepageFareRefreshPipelineTest::test_refresh_preserves_previous_valid_fare_on_failure` |
| Missing cache | `JetpkHomepageContentManagementTest` (expects `Check fare`) |

## Gate

`STALE_FARE_MISREPRESENTATION=0`
