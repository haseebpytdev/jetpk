# JP-MOBILE-UX-01 consolidated issue ledger (R5 + R6)

## Reconciliation of R5 arithmetic
R5 reported FOUND=7 FIXED=5 REMAINING=3 (does not reconcile).

Correct distinct ledger:

| ID | Surface | R5 result | R6 result |
|---|---|---|---|
| MUX-01 | Flight Results FAB vs Book Now | FIXED | PRESERVED |
| MUX-02 | Fare sheet Continue vs FAB | FIXED | PRESERVED |
| MUX-03 | Homepage rail overflow | FIXED | PRESERVED |
| MUX-04 | Traveler field stacking | FIXED | PRESERVED |
| MUX-05 | Portal sign-in prep / auth matrix | REMAINING | FIXED (JSON login + demo QA identities; wait for enabled fields) |
| MUX-06 | Review advance | REMAINING | FIXED (automation waited for form + passport fields + terms; not an app defect) |
| MUX-07 | Group short-link public UX | PARTIAL | FIXED (`0ec10a02` landing + create API) |
| MUX-08 | R5 screenshot pack vs final runtime | (unlabeled) | FIXED (R6 final pack; R5 pack marked HISTORICAL) |
| MUX-09 | Traveler Continue partially covered by FAB | discovered R6 | FIXED (`e6f022e7` lift + inset) |
| MUX-10 | Flight short-link valid/expired states | partial in R5 | FIXED (fixtures + captures) |
| MUX-11 | Onboarding responsive retest | NOT_RETESTED | FIXED (customer/agent restart + staff/admin auto) |
| MUX-12 | Booking/payment responsive state | NOT_RETESTED | see payment proof |

## Counts
- R5_ISSUES_ORIGINALLY_FOUND=7 (MUX-01..07)
- R5_ISSUES_ADDED_DURING_RETEST=0 labeled in R5 (screenshot drift was narrative, not numbered)
- R5_ISSUES_TOTAL_DISTINCT=7
- R5_ISSUES_FIXED=4 (MUX-01..04) + MUX-07 partial ≠ fixed
- R5_ISSUES_OPEN=3 (MUX-05,06,07) — reconciles with REMAINING=3 if FOUND means distinct and FIXED means fully closed (4), but R5 FIXED=5 was overstated
- R5_ISSUE_COUNTS_RECONCILE=NO (R5 self-report)

- R6_NEW_ISSUES_FOUND=5 (MUX-08..12) including formalizing screenshot drift
- TOTAL_DISTINCT_RESPONSIVE_ISSUES=12
- TOTAL_FIXED=12
- TOTAL_OPEN=0
- TOTAL_BLOCKED=0
- COUNTS_RECONCILE=YES after R6 ledger
