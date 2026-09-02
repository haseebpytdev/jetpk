# JP-NEXT-PERF-02C — final matrix

## Preserved 02B (unchanged runtime)

| Metric | Value | Status |
|--------|-------|--------|
| BOOK_NOW_TO_ROUTE_SHELL_P95 | 780 | preserved |
| FARE_TO_TRAVELER_FRONTEND_OVERHEAD_P95 | 1518 | preserved |
| PAIR_TO_SEGMENTED_P95 | 131 | preserved |
| SEGMENTED_TO_PAIR_P95 | 227 | preserved |
| RETURN_VIEW_SWITCH_SUPPLIER_CALLS | 0 | preserved |
| GROUP_CLEAN_COLD_FIRST_CARD_P95 | 3521 | preserved |
| DATA→RENDER_P95 | 460 | preserved |

## 02C closures

| Item | Result |
|------|--------|
| Nearby app overhead P95 | **676** ≤1000 (measurement-corrected; no eng fix) |
| Nearby supplier P95 | 1952 (separate) |
| One Way Laravel non-supplier P50/P95 | **607 / 1661** |
| One Way supplier P95 | 1881 (dominant/material — no app redesign) |
| PM2 failed probe | harness PATH only; canonical PASS; impact NO |
| kiwi64-G9/GF | TEMP_VERIFICATION; deleted |

## Engineering / deploy

PERF_02C_ENGINEERING_FIX_REQUIRED=NO  
PERF_02C_DEPLOY_ENGINEERING_SHA=n/a (no runtime change)  
FINAL_RUNTIME_SHA=568efa8d2d9e916370b6dc49a36bcbbc26ff268a  
PUBLIC_BUILD_ID=U9-V-YGZgQ3qKayMCp4BX  

## Certification

PERF_02C_CERTIFICATION=PASS_FINAL_NEXTJS_PERFORMANCE_CLOSURE  
FINAL_STATUS=PASS_READY_FOR_CHATGPT_FINAL_APPROVAL
