# Live production verification — JP-UX-PORTAL-PERF-01-R2

**Host:** https://jetpakistan.pk  
**FINAL_ENGINEERING_SHA:** `7c923e3294910fc122a4776bb13d9146c5e36559`  
**DEPLOYED_RUNTIME_SHA:** `7c923e3294910fc122a4776bb13d9146c5e36559`  
**PUBLIC_BUILD_ID:** `aq1S4pcjSXLbIEboahxkQ`  
**LIVE_SOURCE_DRIFT:** 0  
**OLS:** PASS  

## Closed / green (not reopened)

Footer, flight-number removal, OW/pair/segmented cards, return view modal authoritative, progressive search, customer dashboard + bookings, IDOR local contract, PIA NDC AUTH classification.

## R2 live gates

| Gate | Result |
|------|--------|
| RETURN_DETAILS_COMPLETE | PASS |
| CARD_DETAILS_PARITY | PASS |
| STOP_COUNT_MISMATCH | 0 |
| TRAVELER_LOADING_JERK | 0 |
| TRAVELER_STABLE_RENDER | PASS |
| BOOK_NOW_HANDOFF_MEASURED | YES (p50 usable 6687ms) |
| CHANGE_FLIGHT | PASS |
| BFCACHE_HISTORY | PASS |
| GROUP_DETAIL_PERFORMANCE_REGRESSION | RESOLVED |
| PERFORMANCE_CLOSEOUT | PASS |
| REVIEW_TRAVELER_COMPACT | BLOCKED — no safe live review without passenger submit / synthetic data |
| CUSTOMER_BOOKING_DETAIL | BLOCKED_NO_AUTHORIZED_EXISTING_CUSTOMER_BOOKING_FOR_LIVE_DETAIL_UAT |
| NEARBY_DATES | NOT_FOUND_UI_ON_RESULTS (control not located in harness; not forced) |

## Safety

No synthetic live passengers, no Al-Haider create/cancel/token, no Sabre PNR, no payment, no ticket.  
`ALHAIDER_BOOKING_ENABLED=false` preserved on deploy gate snapshot.

## Engineering commits this wave

| SHA | Purpose |
|-----|---------|
| `99270933` | Seed booking drawer from card + group SSR |
| `e4d36539` | Warm-start revalidation from card offer |
| `a638b678` / `7c923e32` | Soft-nav `router.push` passenger handoff |

No remaining PARTIAL / NOT_RETESTED_LIVE / CODE_FIXED / SHOT_MISSING for **core** gates except genuine external blockers above.
