# JP-AI-PERFORMANCE-MEASUREMENT-CORRECTION-16 — Final Report

## SOURCE

| Field | Value |
|-------|-------|
| MAIN | `c54213923aa1a1b4c8b2b1860e4aacd64a8e8fef` |
| PRODUCTION_PRODUCT | `ae30ba88b8cb66c4853a842e644aa1e166f022b8` |
| PERFORMANCE_BRANCH | `work/jp-ai-live-search-performance-16` |
| PR | pending |

## MEASUREMENT

| Field | Value |
|-------|-------|
| HARNESS_ARTIFACT_FIXED | **YES** |
| SETUP_CLEAR_MS (median) | 93,705 (excluded from product latency) |
| SETUP_THROTTLE_MS (median) | 61,000 (excluded; `/clear` 429 backoff) |

## Corrected samples (production, 2026-09-22)

| Category | n | API median | DOM median | Visible median | Range (API) | Failed |
|----------|---|------------|------------|----------------|-------------|--------|
| GENERAL | 5 | 727ms | 3ms | 732ms | 646–751ms | 0 |
| RAG | 5 | 701ms | 4ms | 703ms | 602–783ms | 0 |
| LEAD | 0 | — | — | — | — | 3 (no lead gate; admin already consented) |
| SEARCH confirm | 5 | 1,031ms | 3ms | 1,033ms | 824–3,848ms | 0 |
| FULL_FLOW | 5 | 3,431ms automated | — | — | 3,084–9,397ms | 0 |
| COLD after clear | 3 | 543ms | 2ms | 548ms | 514–791ms | 0 |

**SEARCH_EXECUTION_MS** (confirm POST only): median **1,031ms** (includes live supplier read-only path).

## Historical reclassification

| Metric | Classification |
|--------|----------------|
| HISTORICAL_286S | **HARNESS_MEASUREMENT_ARTIFACT** (45s DOM timeout + clear throttle stacked) |
| HISTORICAL_47S | **HARNESS_MEASUREMENT_ARTIFACT** (same DOM wait; API ~0.5–1s) |

**GENUINE_COLD_START_PROVEN:** NO (API median ~543ms after clear).

## ROOT_CAUSE

| Field | Value |
|-------|-------|
| PRODUCT_BOTTLENECK | NONE_PROVEN |
| PRODUCT_CHANGE_REQUIRED | **NO** |
| DOM propagation | **sub-second** (2–28ms after API) |

## SUPPLIERS

| Field | Value |
|-------|-------|
| ACTIVE | sabre, pia_ndc |
| EXECUTION_MODEL | SERIAL |
| OBSERVED_TOTAL | ~2–3s (prior profile-15 server logs) |
| FUTURE_SERIALIZATION_RISK | YES |

## RESOURCES

| Field | Value |
|-------|-------|
| VPS_RAM_AVAILABLE | ~10.3GB |
| VPS_SWAP | 211MB |
| SYSTEM_LOAD | low |
| RESOURCE_PRESSURE | NO |

## FINAL

| Gate | Status |
|------|--------|
| PERFORMANCE_MEASURED | YES |
| PERFORMANCE_READY | **YES** |
| READ_ONLY_SEARCH_READY | YES |
| SEC06 | PASS |
| UAT05_COMPLETE | YES |
| GENERAL_PUBLIC_ACTIVATION | NOT_AUTHORIZED |
| DEPLOY_MARKER_REAL_DEPLOY_VERIFIED | NO |
