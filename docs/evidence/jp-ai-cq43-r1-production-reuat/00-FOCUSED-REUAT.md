# CQ43-R1 / R1.1 — Merge, Deploy & Focused Production Re-UAT

Date: 2026-09-27  
Production: https://jetpakistan.pk/  
PR: #40  
Reviewed head: `15228cf0b52c6a6ff84aed6f3df92f87e366a976`  
PR base: `46e70041c3250dc241ab5172f2825db3b356247f`

## Verdict

**CQ43_R1_PRODUCTION_REUAT=PASS**  
**CQ43_FULL_SOAK_GATE=READY**

Do **not** enable permanent Qwen or iframe. Full 30–50 turn soak is a **separate** phase.

## Merge / deploy

| Item | Value |
|------|-------|
| PR40_MERGED | YES |
| MERGE_SHA | `e5640c103b40c90a6a34e5f668ccf0865ffbc391` |
| MAIN_SHA_AFTER_MERGE | `e5640c103b40c90a6a34e5f668ccf0865ffbc391` |
| PREVIOUS_RUNTIME_SHA | `83f24c145407fe16008efcb96cdea1b2ac34bfcf` |
| DEPLOYED_RUNTIME_SHA | `e5640c103b40c90a6a34e5f668ccf0865ffbc391` |
| DEPLOY_MARKER | `e5640c103b40c90a6a34e5f668ccf0865ffbc391` |
| APPLICATION_CODE_PARITY | PASS |
| LITERAL_MAIN_SHA_PARITY | PASS |
| BACKUP | `/home/pkjetp/backups/jetpk-ai-runtime-20260927T104614Z` |
| PROTECTED_AI_DEPLOY | PASS |

Config unchanged: conversational=true, planner=true, composer=false, embed=false, brain_enabled=true.

## Focused gates

In-proc production harness (`server/10-focused-reuats-inproc.php`) + browser UI (~13 turns) on `e5640c10`.

Booking harness initially reported 3 false FAILs from null-coalesce bugs; session JSON re-eval → PASS (clarify / not_found / booking=null + travel preserved).

## Browser residuals (non-blocking)

1. Intermittent **CLIENT_PARSE_ERROR**: assistant reply stored (HTTP 200) but widget sometimes omits bubble (seen on stale cid + once on `actually Dubai again`).
2. Destination-led UI asked **date** before **origin** (in-proc asks origin first); no name hijack.
3. Rapid Resume AI hit **Too many requests** rate limit; explicit handoff + in-proc resume preservation PASS.
4. `Network error. Please retry.` **not** reproduced this run.

## Next

`CQ43_FULL_SOAK_GATE=READY` — run full CQ43 30–50 turn soak as a separate phase only.  
`PERMANENT_QWEN_OWNER_DECISION=HOLD`  
`IFRAME_PILOT=HOLD`
