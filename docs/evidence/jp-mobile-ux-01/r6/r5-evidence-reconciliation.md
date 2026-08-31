# R5 evidence / screenshot runtime reconciliation

## R5 screenshot runtime problem
Many R5 “final” screenshots were captured while runtime was still
`c89536afe762a567f1df0dc6e193ee0ba2a8af9f`, before the last R5 engineering deploy
`629a0da8fcc44537257a3c78204b30742f7467b4`.

## Classification
| Bucket | Meaning |
|---|---|
| HISTORICAL / INTERMEDIATE | Prior R5 pack under `docs/evidence/jp-mobile-ux-01/screenshots/` — keep, not final authority |
| R6 FINAL | Captured under `docs/evidence/jp-mobile-ux-01/r6/screenshots/` tied to R6 final runtime |

## Counts (R6 audit)
- SCREENSHOTS_AT_C89536AF = treat entire pre-R6 pack as INTERMEDIATE unless individually tagged otherwise
- SCREENSHOTS_AT_629A0DA8 = R5 claimed final runtime; few/no dedicated retakes in R5 tip
- SCREENSHOTS_WITH_UNKNOWN_RUNTIME = 0 in R6 pack (each R6 capture records runtime + build ids in JSON manifests)
- FINAL_PACK_CONTAINS_INTERMEDIATE_RUNTIME = NO (R6 pack only; historical kept separately)

## Rule applied
Any critical PASS screenshot for R6 certification was recaptured after the R6 engineering deployment used for the final matrix.
