# Traveler N=30 — b45975e3

## Production

- RUNTIME=`b45975e36004cf74c9370e71a358fcb38de8ab69`
- PUBLIC_BUILD=`S49jLxNux1bMJZD6Wx_U6`
- Harness: `run-traveler-n30.mjs` (stop at Traveler; no passenger submit / booking mutation)

## Result

```json
{
  "valid": 30,
  "total_p95": 1770,
  "ack_p95": 3,
  "fare_p95": 2218,
  "fetch_p95": 848,
  "shell_usable_p95": 327,
  "url_authority": "PASS",
  "dup_reval": 0,
  "mutations": 0
}
```

## Gate

- N>=30 valid: **PASS** (30)
- Traveler APP/total P95 ≤2000ms: **PASS** (`total_p95=1770`)
- Duplicate revalidate: **0**
- Mutations: **0**

**TRAVELER_GATE=PASS**
