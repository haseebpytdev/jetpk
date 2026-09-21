# AI Clear Rate Limit Policy

`POST /api/public/ai/clear` uses `throttle:10,1` (10 requests per minute).

Harness certification uses **pre-case UI clear only**. On HTTP 429:

1. Read `Retry-After` (fallback 61s if absent)
2. Wait `min(Retry-After + 1, 75)` seconds
3. Retry clear **once**
4. Abort certification run if still throttled

Product throttle is unchanged. Bounded retries are **not** LS case retries.
