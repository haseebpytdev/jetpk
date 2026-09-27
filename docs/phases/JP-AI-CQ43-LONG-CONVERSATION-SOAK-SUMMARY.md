# JP-AI-CQ43-LONG-CONVERSATION-SOAK — SUMMARY

## Phase
JP-AI-CQ43-LONG-CONVERSATION-SOAK

## Branch
`work/jp-ai-cq43-long-conversation-soak`

## Objective
Diagnostic production UAT of long/multi-search continuity + rate-limit soak. No application patches.

## Baseline
- START_MAIN_SHA=`b64588a3fb0d4cafa075898b0495133199412de2`
- PRODUCTION_RUNTIME_SHA=`83f24c145407fe16008efcb96cdea1b2ac34bfcf`
- APPLICATION_CODE_PARITY=PASS (post-runtime commits are CQ42-R3 evidence/docs only)

## Included
- In-proc soak harness `server/10-long-soak-inproc.php`
- Sessions A / Roman Urdu / detours / long 49-turn / human rate / burst / post-limit / polling
- Browser widget sample on https://jetpakistan.pk/
- Evidence under `docs/evidence/jp-ai-cq43-long-conversation-soak/`

## Excluded
- Application code changes
- Controlled search execution (`SKIPPED_BY_UAT_POLICY`)
- CQ43 residual implementation fixes

## Verdict
**CQ43_LONG_CONVERSATION_GATE=FAIL**

Primary residual: HELP-FIRST lead capture hijacks incomplete progressive travel, producing LHE-LHE open-jaw drift and destination corrections misread as lead names. Rate-limit / long-session stability / multi-search route reset mostly PASS.

**PERMANENT_QWEN_OWNER_DECISION=HOLD**  
**IFRAME_PILOT=HOLD**

## Evidence path
`docs/evidence/jp-ai-cq43-long-conversation-soak/`

## Status
READY_FOR_REVIEW=YES (diagnostic)  
READY_FOR_MERGE=NO (unless evidence-only PR approved)  
READY_FOR_DEPLOY=NO  
PRODUCTION_VERIFIED=NO (continuity FAIL)
