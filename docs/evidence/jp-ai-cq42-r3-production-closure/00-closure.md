# CQ42-R3 — Production closure (merge, deploy, canary, hotfixes, final smoke)

Date: 2026-09-27  
Production: https://jetpakistan.pk/  
Host: 185.215.166.176 (`pkjetp`)

## Verdict

**CQ42_R3_PRODUCTION_CLOSURE=PASS**

**CQ43_LONG_CONVERSATION_GATE=READY**

PR34 (`03be054d`) plus hotfixes `1efd8307` and `83f24c14` are deployed. Config parity matches expected AI flags. Canary residuals that failed on the PR34-only runtime (`DATED_RETURN_CONFIRMATION`, `RETURN_ONLY_ON_DATE_FOLLOWUP`) are cleared by hotfix2 + retry4 + final-smoke on runtime SHA `83f24c14`.

Do not treat CQ43 as complete — long-conversation UAT was not executed in this closure. CQ43 may start.

## SHAs

| Field | Value |
|---|---|
| PR34 merge (squash into main) | `03be054d59ddfe0c412368437162009b09c0b7b7` |
| Hotfix #35 (dated return confirm despite Qwen clarify) | `1efd8307` |
| Hotfix #36 (demote open-jaw on return-on follow-up) | `83f24c145407fe16008efcb96cdea1b2ac34bfcf` |
| `.jetpk-runtime-sha` (verified) | `83f24c145407fe16008efcb96cdea1b2ac34bfcf` |
| `storage/app/deploy-sha.txt` | `83f24c145407fe16008efcb96cdea1b2ac34bfcf` |
| RUNTIME_SHA_MATCH | YES |

## Config parity (post-deploy)

From `config-parity.txt` / `closure-summary.txt`:

```
conversational_enabled=true  (ota.ai_assistant.conversational_enabled)
semantic_planner_enabled=true  (ota.ai_assistant.semantic_planner_enabled)
semantic_composer_enabled=false  (ota.ai_assistant.semantic_composer_enabled)
ai_embed.enabled=false
```

**CONFIG_PARITY=PASS**

## Gate matrix

### A. Live canary (`canary-out/SUMMARY.json`) — runtime `03be054d` (pre-hotfix)

| Gate | Result |
|---|---|
| GK_REQUIRED_HOLDOUTS | PASS (8/8) |
| GK_EMPTY_MESSAGE_RESILIENCE | PASS |
| GK_RETRY_BOUNDED | PASS |
| GK_INVALID_JSON_RESIDUAL | 0 |
| WAPIS_ROUTE / WAPAS_ROUTE | PASS |
| SPELLING_ALIAS_ROUTE | PASS |
| HELP_FIRST_ALIAS_TRAVEL | PASS |
| BARE_NAME_LEAD_NON_REGRESSION | PASS |
| QWEN_SUPPORT_ROUTE_HIJACK | 0 |
| QWEN_BOOKING_ROUTE_HIJACK | 0 |
| WAPIS_CONTAMINATED_STATE | PASS |
| MODEL_CANNOT_INVENT_SECOND_LEG | PASS |
| HYBRID_RETURN_CUE | PASS |
| RETURN_WITHOUT_DATE_CLARIFIES | PASS |
| RETURN_DATE_REQUIRED | PASS |
| DATED_RETURN_CONFIRMATION | **FAIL** |
| RETURN_ON_DATE_PARSE | PASS |
| SEMANTIC_RETURN_ON_DATE | PASS |
| HYBRID_RETURN_ON_DATE | PASS |
| RETURN_ONLY_ON_DATE_FOLLOWUP | **FAIL** |
| OPEN_JAW / ORDER39 / RELATIONAL / ROMAN_URDU | PASS |
| BOOKING / HANDOFF / CURRENT / HIGH_RISK | PASS |
| CQ42_R3_PRODUCTION_CLOSURE (canary-only) | **FAIL** |
| CQ43_LONG_CONVERSATION_GATE (canary-only) | NOT_READY |

### B. Hotfix retry4 (`retry4-stdout.txt` + `retry4-out/`) — runtime `83f24c14`

| Case | Result |
|---|---|
| follow_setup → clarify return date | PASS |
| follow_return_on → confirm LHE-DXB 2026-10-10 / 2026-10-15 | PASS (SAME_CID=YES) |
| follow_return_bare → confirm same dates | PASS (SAME_CID2=YES) |
| ret_dated → confirm both dates | PASS |
| ret_on_dated → confirm both dates | PASS |

**RETRY4_RETURN_GATES=PASS** (clears canary `DATED_RETURN_CONFIRMATION` + `RETURN_ONLY_ON_DATE_FOLLOWUP`)

### C. Final smoke (`final-smoke-stdout.txt` + `final-smoke-out/`) — runtime `83f24c14`

Script: `server/09-final-smoke.php`  
Env: `CQ42R3_SMOKE_OUT=/tmp/cq42r3-final-smoke`  
PHP: `/usr/local/lsws/lsphp83/bin/lsphp`

| Case | status | confirm | route / notes | Gate |
|---|---|---|---|---|
| wapas | clarify | false | DXB-LHE | PASS |
| spell | clarify | false | DXB-LHE | PASS |
| ret_tomorrow | clarify | false | asks return date | PASS |
| ret_dated | confirm | true | LHE-DXB 10→15 Oct | PASS |
| ret_on | confirm | true | LHE-DXB 10→15 Oct | PASS |
| oj | clarify | false | open-jaw / MED-LHE | PASS |
| order39 | confirm | true | 2 adults one-way | PASS |
| gk | ok | false | gravity (QWEN_OPEN_DOMAIN) | PASS |
| stock | ok | false | QWEN_OPEN_DOMAIN | PASS |
| handoff | waiting_for_human | false | support queue | PASS |
| booking | clarify | false | asks ref+email/phone | PASS |
| btc | ok | false | no live market source | PASS |
| follow_setup | clarify | false | LHE-DXB asks return | PASS |
| follow_on | confirm | true | return 2026-10-15 | PASS |

All cases: `search=0`, `empty=false`.

**FINAL_SMOKE=PASS**

## Overall production closure gates

| Gate | Source | Result |
|---|---|---|
| Protected AI deploy (PR34) | closure-summary / deploy-log | PASS |
| Hotfix1 deploy `1efd8307` | hotfix-deploy-log | PASS |
| Hotfix2 deploy `83f24c14` | hotfix2-deploy-log | PASS |
| Runtime SHA match | `.jetpk-runtime-sha` | PASS |
| Config parity | config-parity.txt | PASS |
| Canary non-return gates | canary-out/SUMMARY.json | PASS |
| Dated return + return-on follow-up | retry4 + final-smoke | PASS |
| Final smoke suite | final-smoke-out | PASS |
| **CQ42_R3_PRODUCTION_CLOSURE** | all of above | **PASS** |
| **CQ43_LONG_CONVERSATION_GATE** | not executed | **READY** |

## Evidence paths

- `canary-out/SUMMARY.json`, `canary-stdout.txt`
- `retry4-out/`, `retry4-stdout.txt`
- `final-smoke-out/`, `final-smoke-stdout.txt`
- `server/09-final-smoke.php`
- `config-parity.txt`, `closure-summary.txt`
- `hotfix-deploy-log.txt`, `hotfix2-deploy-log.txt`

## Safety (observed)

- No supplier booking/ticket/cancel/refund/payment mutations
- Final smoke `AI_FLIGHT_SEARCH_READ_CALLS=0` on all turns
- Localhost orchestrator probe only

## Next

- CQ43 long-conversation gate may begin (separate phase).
- PERMANENT_QWEN / IFRAME_PILOT remain held until CQ43 acceptance.