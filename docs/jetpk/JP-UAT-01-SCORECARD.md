# JP-UAT-01 — Scorecard

Updated: 2026-08-12T07:35:00Z

## Layer separation

| Layer | Score | Notes |
|-------|-------|-------|
| SCRIPTED_DETERMINISTIC_UAT | **92** | Retained; authoritative regression |
| AGENTIC_BLACK_BOX_UAT | **90** | Playwright CLI blind personas |
| AUTHORITATIVE_VERIFIER | PASS | Agrees with agentic outcomes post-F005 |

## Scripted persona scores (unchanged)

| PERSONA | SCORE | P0 | P1 | P2 | P3 | RESULT |
|---------|-------|----|----|----|----|--------|
| Anonymous Traveller | 95 | 0 | 0 | 0 | 0 | PASS |
| Customer | 94 | 0 | 0 | 0 | 1 | PASS |
| Agent | 93 | 0 | 0 | 0 | 0 | PASS |
| Operations Staff | 93 | 0 | 0 | 0 | 0 | PASS |
| Support Operator | 93 | 0 | 0 | 0 | 0 | PASS |
| Finance-capable Staff | 87 | 0 | 0 | 0 | 0 | PASS_OR_NA |
| Platform Admin | 93 | 0 | 0 | 0 | 0 | PASS |
| Full business loop | 93 | 0 | 0 | 0 | 0 | PASS |

## Agentic persona scores

| PERSONA | GOAL | HIDDEN_KNOWLEDGE | CONFIDENCE | SCORE | RESULT |
|---------|------|------------------|------------|-------|--------|
| Anonymous | yes | no | 5 | 94 | PASS |
| Customer | yes | no | 5 | 95 | PASS |
| Agent (post-F005) | yes | no | 4 | 90 | PASS |
| Staff | yes | no | 5 | 94 | PASS |
| Admin | yes | no | 4 | 91 | PASS |
| Exploratory | yes | no | 4 | 88 | PASS |

## Overall

| Metric | Value |
|--------|-------|
| SCRIPTED_BUSINESS_UAT_SCORE | 92 |
| AGENTIC_BLACK_BOX_UAT_SCORE | 90 |
| P0_COUNT | 0 |
| P1_COUNT | 0 open (F005 fixed) |
| P2_COUNT | 0 open |
| P3_COUNT | accepted/documented |
| AGENTIC_PERSONA_EXECUTION | PASS |
| JP_UAT_01 | AUTONOMOUS_BUSINESS_UAT_PASS_AWAITING_OWNER_SANITY_REVIEW |
