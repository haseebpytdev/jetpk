# Session continuity (R2 live)

| Flag | Value |
|---|---|
| CAPTCHA_AND_SEARCH_SAME_SESSION_REQUIRED | **YES** (preserved + used) |
| RESULT_AND_DOCUMENT_SAME_SESSION_REQUIRED | **YES** |
| MOFA_RESULT_REQUIRES_SESSION | **YES** |

## Proof

| Probe | Outcome |
|---|---|
| Authenticated success path | POST search → 302 → `GET /Home/PrintedUmrahVisa` 200 HTML |
| `GET /Home/PrintedUmrahVisa` with no cookies | **302** to site root |
| `GET /Home/PrintedUmrahVisa` with fresh session but **no** prior successful search | **302** (`Object moved`) — no visa HTML |

Therefore document retrieval is **not** a stable public URL; it requires the MOFA search session established by the successful inquire.
