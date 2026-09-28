# CQ43-R2 Baseline

- REPOSITORY: haseebpytdev/jetpk
- PRODUCTION APPLICATION SHA (pre-R2): `e5640c103b40c90a6a34e5f668ccf0865ffbc391`
- EVIDENCE PR #41 merged (squash): `cdb4637c83aa927be759fb512ba7868e8bc1d296`
- APPLICATION BRANCH: `work/jp-ai-cq43-r2-client-lead-closure`
- START_SHA (branch base / post-#41 main): `cdb4637c83aa927be759fb512ba7868e8bc1d296`

## Blocking residuals addressed

1. SERVER_200_WIDGET_OMIT — client body-based assistant dedup
2. BARE_NAME_LEAD_NON_REGRESSION — pending confirm before lead FSM
3. DESTINATION_LED_PROMPT_ORDER — clarifyTravelMessage date-before-origin

## Out of scope

- No travel architecture redesign
- No Qwen model strategy change
- Permanent Qwen HOLD
- Iframe HOLD
- No deploy after evidence merge or app PR
