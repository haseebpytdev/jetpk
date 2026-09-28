# CQ44-PERF-02 — final matrix

## Merge / runtime

| Key | Value |
|---|---|
| PR46_MERGED | YES |
| PR46_MERGE_SHA | b2cb09df44dd089fe1cf1c2c812cbad7ff896c3e |
| MAIN_SHA_AFTER_PR46 | b2cb09df44dd089fe1cf1c2c812cbad7ff896c3e |
| APPLICATION_RUNTIME_SHA | a0e616747a95d632f270a6edafc3f6e8d9230fca |
| APPLICATION_CODE_CHANGED | NO |
| DEPLOY_OCCURRED | NO |

## Metric scope legend

| Scope | Meaning |
|---|---|
| PRIMARY_ONLY | First controlled batch only (explicit n=6 variants; CURRENT n=5 Bitcoin) |
| COMBINED_SAMPLE | PRIMARY_ONLY plus supplemental warm burst probes |

Diagnostic conclusions unchanged. Percentiles are nearest-rank.

## Explicit route

Raw PRIMARY_ONLY latencies (n=6): `7629 9182 9836 10178 13184 13313`

| Key | Scope | Value |
|---|---|---|
| EXPLICIT_ROUTE_SAMPLE_COUNT_PRIMARY | PRIMARY_ONLY | 6 |
| EXPLICIT_ROUTE_SAMPLE_COUNT_COMBINED | COMBINED_SAMPLE | 11 (6 primary + 5 burst Now-Islamabad) |
| EXPLICIT_ROUTE_VALID_RATE | COMBINED_SAMPLE | 8/11 = 0.727 |
| EXPLICIT_ROUTE_INVALID_JSON_RATE | COMBINED_SAMPLE | 3/11 = 0.273 |
| EXPLICIT_ROUTE_INVALID_PLAN_RATE | COMBINED_SAMPLE | 0 |
| PRIMARY_EXPLICIT_ROUTE_P50_MS | PRIMARY_ONLY | 9836 |
| PRIMARY_EXPLICIT_ROUTE_P95_MS | PRIMARY_ONLY | 13313 |
| PRIMARY_EXPLICIT_ROUTE_MAX_MS | PRIMARY_ONLY | 13313 |
| COMBINED_EXPLICIT_ROUTE_P50_MS | COMBINED_SAMPLE | 9182 |
| COMBINED_EXPLICIT_ROUTE_P95_MS | COMBINED_SAMPLE | 13313 |
| EXPLICIT_ROUTE_SERVER_AUTHORITY_COMPLETE | — | YES |
| EXPLICIT_ROUTE_QWEN_REQUIRED | — | NO (semantic); YES today by `no_active_travel` / PERF-01 keep policy |

Prior production control outlier ≈36410ms (outside this diagnostic batch).

## CURRENT

Raw PRIMARY_ONLY latencies (n=5): `3951 4177 4506 5579 6810`

| Key | Scope | Value |
|---|---|---|
| CURRENT_SAMPLE_COUNT_PRIMARY | PRIMARY_ONLY | 5 |
| CURRENT_SAMPLE_COUNT_COMBINED | COMBINED_SAMPLE | 10 (5 primary + 5 burst) |
| CURRENT_VALID_RATE | COMBINED_SAMPLE | 1.0 (this session) |
| CURRENT_INVALID_JSON_RATE | COMBINED_SAMPLE | 0.0 (this session; prior RUN2 intermittent) |
| CURRENT_INVALID_PLAN_RATE | COMBINED_SAMPLE | 0 |
| PRIMARY_CURRENT_P50_MS | PRIMARY_ONLY | 4506 |
| PRIMARY_CURRENT_P95_MS | PRIMARY_ONLY | 6810 |
| PRIMARY_CURRENT_MAX_MS | PRIMARY_ONLY | 6810 |
| COMBINED_CURRENT_P50_MS | COMBINED_SAMPLE | 4177 |
| COMBINED_CURRENT_P95_MS | COMBINED_SAMPLE | 6810 |
| CURRENT_SAFE_REFUSAL_SERVER_AUTHORITY_COMPLETE | — | YES |
| CURRENT_QWEN_REQUIRED | — | NO |

Prior RUN2 Bitcoin invalid_json outlier ≈27176ms (outside this diagnostic batch).

## invalid_json / cost

| Key | Value |
|---|---|
| INVALID_JSON_PRIMARY_CLASS | UNKNOWN (failed chat body not retained); correlated SCHEMA_MISMATCH open_jaw mislabel on valid probes |
| INVALID_JSON_ROOT_CAUSE | Intermittent planner decode fail (ISB A→B); Qwen wait dominates recovery |
| QWEN_WAIT_SHARE_OF_FAILED_REQUEST | 0.988 |
| HYBRID_FALLBACK_COST_MS | ~150 avg on failed explicit |

## Context / telemetry

| Key | Value |
|---|---|
| SYSTEM_PROMPT_CHARS | 2807 |
| SCHEMA_CHARS | 1768 |
| CONVERSATION_CONTEXT_CHARS | 0 (fresh) |
| SHOPPING_STATE_CHARS | 2 |
| TOTAL_REQUEST_CHARS | 3216 |
| QUEUE_LATENCY_AVAILABLE | NO |
| PROMPT_EVAL_AVAILABLE | YES (timings.prompt_ms) |
| GENERATION_LATENCY_AVAILABLE | YES (timings.predicted_ms) |
| TOKEN_TELEMETRY_AVAILABLE | YES (via direct HTTP usage) |
| COLD_START_EFFECT | PARTIAL — relative warm series only; llama already warm |
| CONCURRENCY_EFFECT | N/A (no overlap stress; slots reported 4) |

## Decisions

| Class | Decision |
|---|---|
| EXPLICIT_A_TO_B_ROUTE | A SAFE_DETERMINISTIC_SHORT_CIRCUIT |
| CURRENT_WITHOUT_APPROVED_SOURCE | A SAFE_DETERMINISTIC_SHORT_CIRCUIT |
| DESTINATION_LED | B QWEN_STILL_REQUIRED |
| OPEN_JAW | B QWEN_STILL_REQUIRED |
| GENERAL_KNOWLEDGE | B QWEN_STILL_REQUIRED |

## Safety counters

| Key | Value |
|---|---|
| SEARCH_BEFORE_CONFIRMATION | 0 |
| SUPPLIER_MUTATIONS | 0 |
| BOOKING_MUTATIONS | 0 |
| PAYMENT_MUTATIONS | 0 |
| DIRECT_ONLY_LEAD_RESIDUAL | OPEN_SEPARATE_TRACK |

## Gate

| Key | Value |
|---|---|
| CQ44_PERF_02_DIAGNOSTIC | PASS |
| READY_FOR_IMPLEMENTATION_REVIEW | YES |
| READY_FOR_MERGE | NO |
| READY_FOR_DEPLOY | NO |
| PERF_02_RECOMMENDED_IMPLEMENTATION | CURRENT early bypass + clear explicit A→B deterministic short-circuit (see 09-candidate-decision.md) |
| PR47_EVIDENCE_SCOPE_LABELS_FIXED | YES |
