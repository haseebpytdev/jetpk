# Retry audit

| Field | Value |
|---|---|
| REVALIDATION_RETRY_COUNT | 0 same-payload empty retries (classify once → rematch) |
| RECOVERY_SEARCH_RETRY_COUNT | 1 bounded search rematch |
| REVALIDATION_BACKOFF | none (no artificial sleep) |
| RECOVERY_BACKOFF | none |
| EMPTY_RESPONSE_UNNECESSARY_RETRY | 0 |

Warm Start may share one in-flight revalidate promise with Continue (deduped by fare identity key).
