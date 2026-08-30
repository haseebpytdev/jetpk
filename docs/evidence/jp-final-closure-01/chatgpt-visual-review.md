# ChatGPT visual review — JP-FINAL-CLOSURE-01-R5

## Runtime

- DEPLOYED_RUNTIME_SHA=`0221a3f9ff26621289eb3ad61b43e3af00b3ebb3`
- PUBLIC_BUILD_ID=`zxhTMPV_izXxL129p_rnD`
- Card screenshots captured under R5A build `PGOVQaS2ow-r7q2OHoNdo` (same card UI)

## Visual gates

| Shot | Expected | Result |
|---|---|---|
| 01/02 return pair | `pair-return-card` | **PASS** (count=12) |
| 03/04 segmented outbound | `outbound-option-card` | **PASS** |
| 05/06 segmented return | `return-option-card` | **FAIL** (still on results URL) |
| 15–18 customer/agent auth | dashboard not login | **PASS** |
| Staff/admin | `/staff/dashboard` | PARTIAL |

## Performance for reviewers

Best certified instrumented sample (R5A): usable p50≈3.7s vs corrected R4≈15.25s; **p95≈34s ⇒ PERFORMANCE=FAIL**.

## Do not reopen

One-way/pair/segmented **code** parity, Groups JFZZT2DJ/WZBJCK6Z, email hardcode R4=0 unresolved live resolver.
