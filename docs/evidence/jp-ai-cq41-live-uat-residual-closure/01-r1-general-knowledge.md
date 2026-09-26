# CQ41-R1 — General knowledge primary path correction

## Identity

| Field | Value |
|-------|-------|
| PREVIOUS_HEAD | `b7ff6527319b376a004af5de4d38f72269c89992` |
| BRANCH | `work/jp-ai-cq41-live-uat-residual-closure` |
| PR | #31 |
| BASE | `437904f46bdbd471b40d211532ab893b598e0616` |

## Residual #3 correction

**Before (incorrect):** SemanticBrain → OpenDomainResponseService canned facts as primary (composer OFF).

**After (required):**
1. Server classifies safe GENERAL_KNOWLEDGE / casual / out-of-domain
2. `AiConversationalAgent::tryOpenDomainRespond` (Qwen / InferenceProvider) generates the answer
3. `OpenDomainResponseService::fallbackForCategory` only when Qwen unhealthy/invalid
4. `SEMANTIC_COMPOSER_ENABLED` remains **false**

## Current/live gate

- Match arm: CURRENT_UNVERIFIED evaluated **before** general answering
- Router patterns expanded for news today, weather today/right now, bitcoin price now, current stock price
- Invented live values from a mislabeled Qwen plan must not reach the user

## Local PHPUnit

```
77 passed, 597 assertions
```

Suites: CQ41 residual + Order-39 + Brain28 + HybridTravelPipeline + open-jaw smoke + ConversationQuality26AiFirst

## CI documentation accuracy

| Gate | Status |
|------|--------|
| LOCAL_PHPUNIT | 77/77 PASS |
| GITHUB_RELEASE_GUARDS | (re-check after push) |
| GITHUB_PHPUNIT | SKIPPED_NO_VENDOR (do not claim GitHub ran CQ41 PHPUnit) |

## Real Qwen

REAL_QWEN_RUNS=0  
REAL_QWEN_STATUS=UNAVAILABLE (`:3921`)

## Thinking UI

THINKING_LOADING_STATE=CODE_PASS (no established lightweight unit harness for AskJetPakistanChat; visual UAT post-deploy)
