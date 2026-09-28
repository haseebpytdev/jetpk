# CQ44-PERF-02 — candidate decisions (no implementation)

One label per class:

| Class | Decision | Rationale |
|---|---|---|
| EXPLICIT_A_TO_B_ROUTE | **A. SAFE_DETERMINISTIC_SHORT_CIRCUIT** | Server resolves unambiguous origin/destination/date before Qwen (`ISB/DXB/2026-10-05`). `deterministicAuthorityComplete` returns `complete=false` only because `no_active_travel` (fresh session) — not because fields are missing. PERF-01 also documents `explicit_route_keep_qwen` when prior travel exists. Hybrid already builds correct confirmation after Qwen fails. Qwen adds no required field and can harm (invalid_json delay; open_jaw mislabel). Ambiguous / dest-led / open-jaw remain out of scope. |
| CURRENT_WITHOUT_APPROVED_SOURCE | **A. SAFE_DETERMINISTIC_SHORT_CIRCUIT** | `classifyOpenDomain` → `CURRENT_UNVERIFIED` + `classifyCurrentTopic` → `market` + `live_provider=false` are all known before Qwen. Product has no approved Bitcoin/market provider. Qwen role is only planner→`currentUnverified` (same deterministic refusal) or invalid_json→same safety text. Waiting 4–27s adds no value. |
| DESTINATION_LED | **B. QWEN_STILL_REQUIRED** | Incomplete OD/date; clarification needed. Do not short-circuit. |
| OPEN_JAW | **B. QWEN_STILL_REQUIRED** | Multi-leg shape; PERF-01.1 already protects prior multi-leg. Do not short-circuit. |
| GENERAL_KNOWLEDGE | **B. QWEN_STILL_REQUIRED** | Answer generation needs open-domain LLM path (already early-bypassed past travel planner for GK category — keep as-is). |

## Explicit-route short-circuit criteria (future gate — not implemented)

Satisfied for clear Pakistan-city A→B + explicit date in this diagnostic:

- origin explicit + unambiguous ✓
- destination explicit + unambiguous ✓
- date explicit + resolved ✓
- trip shape simple one-way ✓
- not open-jaw / multi-leg ✓
- server can build correct confirmation ✓
- Qwen adds no required semantic field ✓

## CURRENT short-circuit criteria (future gate — not implemented)

Satisfied for Bitcoin/market:

- server detects CURRENT ✓
- no approved live source ✓
- correct product behavior = deterministic safe refusal ✓
- Qwen adds no necessary value ✓

## Do not conflate

| Query | Server category |
|---|---|
| Bitcoin price right now | CURRENT_UNVERIFIED / market |
| gold price right now | GENERAL_KNOWLEDGE (not CURRENT regex) |
| USD to PKR right now | null open-domain (not CURRENT) |
| weather in Dubai right now | CURRENT_UNVERIFIED / weather |

## Recommended next implementation (PERF-02 follow-on)

1. Deterministic early bypass for `CURRENT_UNVERIFIED` when no approved provider (mirror GK/CASUAL early path).
2. Deterministic short-circuit for **fresh clear explicit A→B** when `SERVER_CAN_BUILD_VALID_CONFIRMATION=YES` and not open-jaw / not ambiguous (extend beyond refinement-only PERF-01).
3. Keep dest-led, ambiguous airports, open-jaw on Qwen.
4. Optional later: runtime telemetry (`--metrics`) / prompt shrink — secondary to routing short-circuits.

DIRECT_ONLY_LEAD_RESIDUAL=OPEN_SEPARATE_TRACK
