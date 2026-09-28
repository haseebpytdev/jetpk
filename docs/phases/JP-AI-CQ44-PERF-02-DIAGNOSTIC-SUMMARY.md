# JP-AI-CQ44-PERF-02 — essential-path tail latency diagnostic

## Status

`CQ44_PERF_02_DIAGNOSTIC=PASS`

Diagnostic only. No application code changes. No deploy. No iframe. No PERF-02 implementation.

## Branch

`work/jp-ai-cq44-perf-02-tail-diagnostic` from main `@ b2cb09df44dd089fe1cf1c2c812cbad7ff896c3e` (PR #46 evidence merge).

## Objective

Classify remaining Qwen tails on:

1. Clear explicit A→B routes (`Now Islamabad to Dubai next Monday` family)
2. CURRENT market requests (`What is Bitcoin's price right now?`)

## Key findings

1. **Explicit A→B server authority is complete before Qwen** for unambiguous city pairs + explicit dates (`ISB→DXB` + `next Monday` → `2026-10-05`). Hybrid builds confirmation without Qwen. Short-circuit is blocked today by `no_active_travel` (fresh session), not missing fields. PERF-01 intentionally kept fresh explicit routes on Qwen.
2. **Qwen is not required for clear A→B** and can harm: 3/6 primary samples `invalid_json` (all Islamabad variants); a valid probe mislabeled the same route as `open_jaw`. Hybrid always recovered correct confirmation. Qwen wait ≈ 98.8% of failed-request latency; hybrid ≈ 150ms.
3. **CURRENT safe refusal is fully server-authoritative** for Bitcoin/market: `CURRENT_UNVERIFIED` + topic `market` + `live_provider=false`. SemanticBrain does not early-bypass CURRENT (unlike GK/CASUAL/OOD), so Qwen still runs 3–7s this session (prior RUN2 up to ~27s on invalid_json) for the same deterministic refusal text.
4. **Do not conflate categories:** gold → `GENERAL_KNOWLEDGE`; USD/PKR → null open-domain; weather → CURRENT/weather.
5. **Ambiguous / dest-led / open-jaw** correctly remain Qwen-required classes.
6. Runtime timings available via llama HTTP `usage`/`timings`; queue metrics endpoint disabled; concurrency not stressed (`CONCURRENCY_EFFECT=N/A`).

## Candidate decisions

| Class | Decision |
|---|---|
| EXPLICIT_A_TO_B_ROUTE | A SAFE_DETERMINISTIC_SHORT_CIRCUIT |
| CURRENT_WITHOUT_APPROVED_SOURCE | A SAFE_DETERMINISTIC_SHORT_CIRCUIT |
| DESTINATION_LED | B QWEN_STILL_REQUIRED |
| OPEN_JAW | B QWEN_STILL_REQUIRED |
| GENERAL_KNOWLEDGE | B QWEN_STILL_REQUIRED |

## Recommended next implementation

1. Early deterministic CURRENT refusal when no approved live source.
2. Fresh clear explicit A→B deterministic confirmation short-circuit (with ambiguity/open-jaw guards).
3. No runtime/model tuning until routing candidates land.

## Evidence

`docs/evidence/jp-ai-cq44-perf-02-tail-diagnostic/`

Harness: `server/10-perf02-tail-inproc.php`, `server/11-perf02-invalid-json-burst.php`

## Safety

SEARCH_BEFORE_CONFIRMATION=0 · SUPPLIER_MUTATIONS=0 · BOOKING_MUTATIONS=0 · PAYMENT_MUTATIONS=0

DIRECT_ONLY_LEAD_RESIDUAL=OPEN_SEPARATE_TRACK

APPLICATION_RUNTIME_SHA remains `a0e616747a95d632f270a6edafc3f6e8d9230fca`.
