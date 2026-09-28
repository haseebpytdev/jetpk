# CQ43 FINAL — Matrix

## Verdict

**CQ43_LONG_CONVERSATION_GATE=FAIL**  
**PERMANENT_QWEN_OWNER_DECISION=HOLD**  
**IFRAME_PILOT=HOLD**

In-proc primary long session (41 turns, cid stable) passed travel continuity / detours / handoff-resume / Roman Urdu / fresh wapis / safety zeros.

Gate fails on **reproducible browser widget omission** (assistant stored HTTP 200, bubble often not rendered / harness silent) and **co-located full-chat burst not tripping limiter** under slow LAB path (60s decay). Dedicated assertRateLimit probe: FIRST_LIMIT=31 PASS.

## Identity

| Field | Value |
|-------|-------|
| START_SHA | e5640c103b40c90a6a34e5f668ccf0865ffbc391 |
| PRODUCTION_RUNTIME_SHA | e5640c103b40c90a6a34e5f668ccf0865ffbc391 |
| APPLICATION_CODE_PARITY | PASS |
| PRIMARY_LONG_SESSION_TURNS | 41 |
| CONVERSATION_ID_STABLE | YES |
| primary_conversation_id | 907a4f3e-4c3b-4142-a7dd-5936e4ffaffb |

## Travel / detour gates (in-proc)

All required travel gates PASS (progressive, corrections, confirmation invalidation, route resets, ONEWAY↔RETURN, open-jaw, Roman Urdu, GK/CURRENT/booking mid-trip, handoff resume PRESERVED, FRESH_WAPIS, drift counters 0, QWEN_MODEL_ONLY_HANDOFF=0).

DESTINATION_LED_PROMPT_ORDER=PARTIAL (asks date before origin; state correct).

BARE_NAME_LEAD_NON_REGRESSION=FAIL (name stage not entered on Order39-style confirm path).

## Rate limit

| Test | Result |
|------|--------|
| Human-paced | UNEXPECTED_RATE_LIMITS=0 PASS |
| assertRateLimit probe | FIRST_LIMIT_TURN=31, HTTP 429, retry≈60 PASS |
| Soak/full-chat LAB burst (35–40) | No 429 — multi-second LAB turns + 60s decay window |
| Polling contamination | 0 PASS |
| Resume normal session | PASS |
| Resume after burst probe | RATE_LIMITED / N/A depending on session |

## Browser

BROWSER_TURNS≈15 attempted; sticky prior cid contamination + repeated SERVER_200_WIDGET_OMIT (POLL_RACE / CLIENT_RESPONSE_SHAPE). Thinking/busy observed when AI path active. Not unconditional continuity PASS.

## Safety

SEARCH_BEFORE_CONFIRMATION=0, AUTHORIZED_SEARCH_CALLS=0, mutations=0, HTTP_500=0, PII_FIRST=0.
