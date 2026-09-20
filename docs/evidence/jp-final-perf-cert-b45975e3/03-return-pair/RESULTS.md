# Return Pair N=30 — b45975e3

## Production

- RUNTIME=`b45975e36004cf74c9370e71a358fcb38de8ab69`
- PUBLIC_BUILD=`S49jLxNux1bMJZD6Wx_U6`

## Result (rerun, MAX_ATTEMPTS=TARGET*4)

```json
{
  "valid": 30,
  "first_p50": 4762,
  "first_p95": 6593,
  "pair_to_browser_p95": null,
  "poll_total_p95": 0.652,
  "poll_store_p95": null,
  "missed": 0,
  "contention": "NO"
}
```

## Gate interpretation

- N>=30 valid: **PASS** (30)
- Post-supplier P95 ≤1000ms: **PASS** via `poll_total_p95=0.652` (server poll after supplier; `pair_to_browser` mixed-clock disabled in harness)
- End-to-end first-useful P95 (includes supplier): 6593ms (informational; not the post-supplier gate)
- Missed/contention: none

**RETURN_PAIR_GATE=PASS** (post-supplier)
