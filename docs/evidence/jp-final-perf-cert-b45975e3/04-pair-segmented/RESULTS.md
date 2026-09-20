# Pair ↔ Segmented — b45975e3

## Production

- RUNTIME=`b45975e36004cf74c9370e71a358fcb38de8ab69`
- PUBLIC_BUILD=`S49jLxNux1bMJZD6Wx_U6`

## Pair (N≥20)

Covered by Return Pair cert (`03-return-pair`):

- valid=30
- `poll_total_p95=0.652ms`
- `RETURN_DUPLICATE_FETCH_COUNT=0`

## Segmented (N=20)

```json
{
  "valid": 20,
  "first_p50": 9631,
  "first_p95": 10996,
  "poll_total_p95": 0.801,
  "missed": 0,
  "contention": "NO",
  "dup": 0
}
```

Harness notes: include `outbound-option-card` in card validity; segmented shell-stall threshold 20s (pair remains 4.5s).

## Gate

- Pair N≥20: **PASS**
- Segmented N≥20: **PASS**
- Post-supplier poll P95 ≤1000ms: **PASS** (pair 0.652 / segmented 0.801)
- Duplicate supplier fetches: **0**

**PAIR_SEGMENTED_GATE=PASS**
