# JP-AI-PERFORMANCE-MEASUREMENT-CORRECTION-16

Harness-only measurement correction. No product runtime deploy.

## Timing model (`sendMessage`)

| Metric | Definition |
|--------|------------|
| `api_response_ms` | `T_CHAT_RESPONSE_BODY - T_CLICK` |
| `network_response_ms` | `T_CHAT_RESPONSE_HEADERS - T_CLICK` |
| `dom_commit_ms` | `T_DOM_MESSAGE_PRESENT - T_CHAT_RESPONSE_BODY` |
| `user_visible_total_ms` | `T_DOM_MESSAGE_PRESENT - T_CLICK` |
| `interactive_again_ms` | composer ready after API body |

## DOM matching

- Count assistant bubbles before send (`[class*="messageRow"]` excluding `userRow`).
- After API JSON parse, wait for new assistant bubble whose normalized body matches API `message`.
- DOM timeout (default 15s) is a **failure boundary** — not absorbed into latency median.
- Failed samples: `DOM_RESULT=FAIL`, `measurement_valid=false`, excluded from median.

## Setup exclusion

`clearConversation()` returns `setup_clear_ms` and `setup_throttle_wait_ms` separately from turn timings.

## Search metrics

| Metric | Definition |
|--------|------------|
| `SEARCH_EXECUTION_MS` | confirm-turn `api_response_ms` only |
| `FULL_FLOW_AUTOMATED_MS` | first travel message → final confirm response (excludes clear throttle in turn API) |

## Historical reclassification

| Old label | Corrected classification |
|-----------|------------------------|
| COLD ≈47s | `HISTORICAL_HARNESS_TIMEOUT_ARTIFACT` if API ≪ 45s |
| SEARCH ≈286s | `HARNESS_MEASUREMENT_ARTIFACT` (DOM wait + clear throttle stacked) |
