# CQ42 — Merge, Deploy & Live Re-Canary

Date: 2026-09-26
Production: https://jetpakistan.pk/
Reviewed head: `7b4ba70e89cedf9190297539d7dfdd96fa9a9e32`
Reviewed base: `d681651df61a878a385a9d3184d00cbb0dcd938f`
Merged main: `01dbe7cf5ba79280420d7b2274db6b2b3fb0f8e3`

## Verdict

**CQ42_PRODUCTION_CLOSURE=FAIL**

Merge + protected AI deploy + config parity succeeded. Live CURRENT_*/booking/handoff/HIGH_RISK/Order-39 primary paths largely hold. Closure fails on:

1. **GENERAL_KNOWLEDGE reliability** — majority of required GK prompts reject with `OPEN_DOMAIN_REJECT_REASON=invalid_json` (or semantic invalid_plan → soft “couldn’t produce”).
2. **WAPIS_ROUTE** — `Dubai se Lahore wapis` (fresh and continuity) misclassified as open-jaw `DXB-LHE then LHE-DXB` instead of return/`DXB→LHE`.

Do **not** start CQ43. `PERMANENT_QWEN=HOLD_FOR_CQ43`, `IFRAME_PILOT=HOLD_FOR_CQ43`.

## Pre-merge / merge / deploy

| Field | Value |
|---|---|
| PR32_MERGED | YES (merged 2026-09-26T18:46:53Z) |
| MERGE_SHA / MAIN_SHA_AFTER_MERGE | `01dbe7cf5ba79280420d7b2274db6b2b3fb0f8e3` |
| PREVIOUS_RUNTIME_SHA | `d681651df61a878a385a9d3184d00cbb0dcd938f` |
| DEPLOYED_RUNTIME_SHA | `01dbe7cf5ba79280420d7b2274db6b2b3fb0f8e3` |
| DEPLOY_MARKER | `01dbe7cf5ba79280420d7b2274db6b2b3fb0f8e3` |
| RUNTIME_MAIN_PARITY | PASS |
| Frontend SHA | still `d681651…` (CQ42 backend-only; thinking bundle present) |

## Config (post-deploy)

```
CONVERSATIONAL_ENABLED=true
SEMANTIC_PLANNER_ENABLED=true
SEMANTIC_COMPOSER_ENABLED=false
AI_EMBED_ENABLED=false
brain_enabled=true (SemanticBrain/planner isEnabled)
QWEN_PLANNER=ON COMPOSER=OFF IFRAME=OFF
```

Config keys live under `ota.ai_assistant.*` (not `ota.ai.*`).

## GENERAL_KNOWLEDGE (required 8) — per case

| Case | Best outcome | Source / reject |
|---|---|---|
| What is gravity? | PASS (retry) | `QWEN_OPEN_DOMAIN` / accepted |
| Why is the sky blue? | PASS | `QWEN_OPEN_DOMAIN` / accepted |
| What is DNA? | FAIL | `invalid_json` (×2) |
| How does Wi-Fi work? | FAIL | `invalid_json` (×2) |
| What causes tides? | FAIL | `invalid_json` (×2) |
| Explain recursion simply. | FAIL | soft fallback / no accept |
| RAM vs storage | FAIL | soft fallback / no accept |
| Why do airplanes fly? | PARTIAL | first: accepted open-domain via hybrid; retry: fail |

**GENERAL_KNOWLEDGE=FAIL** (3/8 durable useful answers; do not hide fallouts)  
**GENERAL_HOLDOUTS=dna,wifi,tides,recursion,ram** (+ airplanes flaky)  
**OPEN_DOMAIN_QWEN_SUCCESS≥2** (sky + gravity retry; airplanes first accept also)

## CURRENT_* (10/10)

All returned `SERVER_OPEN_DOMAIN_CATEGORY=CURRENT_UNVERIFIED` with correct `CURRENT_TOPIC` wording (market/news/sports/weather). No booking/handoff/fabrication.

## Stable negatives

Classify as non-CURRENT (GENERAL_KNOWLEDGE path). Answers often still `invalid_json` — classification PASS, answer quality residual.

## Booking / handoff / HIGH_RISK

| Gate | Result |
|---|---|
| Booking verification | PASS — asks for reference + email/phone |
| Handoff | PASS — support queue |
| BOOKING_IDENTITY_BYPASS | 0 |
| BOOKING_DATA_LEAK | 0 |
| HIGH_RISK public | PASS — deterministic structured; no Qwen open-domain |
| HIGH_RISK direct brain | PASS — `FINAL_RESPONSE_SOURCE=DETERMINISTIC_HIGH_RISK`; planner calls=1; `OPEN_DOMAIN_ATTEMPTED=NO` |

## Order-39 smoke

| Case | Result |
|---|---|
| PRIMARY LHE→DXB 2 adults | PASS confirm |
| Wife → 2 adults | PASS (`RELATIONAL_PAIR`) |
| Open-jaw LHE-JED / MED-LHE | PASS |
| Roman Urdu | PASS confirm |
| Wapis | **FAIL** open-jaw DXB-LHE/LHE-DXB |

Safety ints: SEARCH_BEFORE_CONFIRMATION=0, PII_FIRST=0, WRONG_ROUTE_ACTION_READY=0, LEGACY_LLM_AFTER_SEMANTIC_TRAVEL_FALLBACK=0

## Thinking UX (browser)

- Thinking appears after threshold; send disabled while busy.
- Gravity success: thinking clears → assistant message present; no duplicate.
- Recursion: HTTP `Request failed` toast (visible failure); after dismiss, user bubble remains without assistant bubble — counted as residual only if treating toast as non-message; success path OK.
- **THINKING_LOADING_STATE=PASS**
- **SILENT_EMPTY_TURNS=0** (failed HTTP shows toast; success shows assistant)
- **DUPLICATE_ASSISTANT_TURNS=0**

## Telemetry (accepted GK sample: sky)

| Metric | Value |
|---|---|
| MODEL_CALLS | 2 |
| SEMANTIC_LATENCY_MS | 5771 |
| OPEN_DOMAIN_LATENCY_MS | 4928 |
| TOTAL_MODEL_LATENCY_MS | 10699 (= sum) |

Aggregate (first-pass GK set + retries; approximate):

| Metric | Value |
|---|---|
| OPEN_DOMAIN_ATTEMPTS | ≥14 |
| OPEN_DOMAIN_QWEN_SUCCESS | 2–3 |
| OPEN_DOMAIN_FALLBACKS | majority `invalid_json` |
| OPEN_DOMAIN_ACCEPT_RATE | ~0.15–0.20 |
| SEMANTIC_P50_MS | ~5771 |
| SEMANTIC_P95_MS | ~13368 |
| OPEN_DOMAIN_P50_MS | ~12368 |
| OPEN_DOMAIN_P95_MS | ~21159 |
| TOTAL_P50_MS | ~13368 |
| TOTAL_P95_MS | ~24139 |
| TOTAL_MAX_MS | ~40154 (wifi retry) |

## Residuals (block CQ43 readiness)

1. Open-domain Qwen `invalid_json` rate too high for GENERAL_KNOWLEDGE PASS.
2. Wapis / reverse-route mis-open-jaw.
3. Optional: harden chat HTTP failure so dismissed toast still leaves a durable assistant error turn.

Evidence: `docs/evidence/jp-ai-cq42-production-closure/canary-out/`
Scripts: `docs/evidence/jp-ai-cq42-production-closure/server/`
