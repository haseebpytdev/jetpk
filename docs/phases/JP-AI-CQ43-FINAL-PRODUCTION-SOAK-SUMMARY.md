# JP-AI-CQ43-FINAL-PRODUCTION-SOAK — Phase Summary

## Phase name
CQ43 FINAL — Production Long-Conversation / Multi-Search Soak

## Branch
`work/jp-ai-cq43-final-production-soak`

## Objective
Certify production Ask JetPakistan continuity over ~40-turn primary conversation plus isolated rate-limit/browser controls on SHA `e5640c103b40c90a6a34e5f668ccf0865ffbc391`. Evidence/harness only — no application code changes.

## Included scope
- Preserve `docs/evidence/jp-ai-cq43-r1-production-reuat/`
- In-proc primary soak + fresh wapis / human rate / burst / polling / bare-name probe
- Browser ≥15 turns with client-parse watch
- Evidence pack + PR (no merge)

## Excluded scope
- Application code changes
- Permanent Qwen enablement
- Iframe enablement
- Supplier/booking/payment mutations
- Full soak defect fixing

## Findings
1. In-proc travel continuity after CQ43-R1: **PASS** (progressive, corrections, multi-search, OJ, Roman Urdu, detours, handoff/resume, safety zeros).
2. DESTINATION_LED_PROMPT_ORDER=**PARTIAL** (date asked before origin; state OK).
3. Rate limiter: instant assertRateLimit probe **PASS** at turn 31; slow LAB full-chat burst may not trip 30/min due to 60s decay.
4. Browser: **reproducible** SERVER_200_WIDGET_OMIT / poll-race residual → continuity not unconditional PASS.
5. BARE_NAME_LEAD_NON_REGRESSION=**FAIL** (name stage not reached on confirm-style path).

## Final status
CQ43_LONG_CONVERSATION_GATE=**FAIL**  
PERMANENT_QWEN_OWNER_DECISION=**HOLD**  
IFRAME_PILOT=**HOLD**

## Evidence path
`docs/evidence/jp-ai-cq43-final-soak/`

## Rollback
N/A (no production mutation this phase beyond read-only/in-proc UAT).
