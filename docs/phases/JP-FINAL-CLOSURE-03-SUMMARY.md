# JP-FINAL-CLOSURE-03 — R3F carry-forward + email corrective (local)

## Branch
`phase/jp-email-prod-branding-02`

## Performance (current production)

| Field | Value |
|---|---|
| PRODUCTION_RUNTIME_SHA | `a4280c7f5124fd340cd2dc4e918208db9849405c` |
| PERF_CERT_RUNTIME_SHA | `996b62d1db34bedb1f5906b51b93e96949ab4743` |
| ANCESTOR | YES |
| PUBLIC_BUILD_ID | `5GNVRs0UtH2hjOKBjoeC6` (unchanged vs cert) |
| DASHBOARD_BUILD_ID | `knBdbMBLDH3sxWqzoMDYu` |
| Frontend traveler diff 996b62d1..a4280c7f | empty |
| N_VALID | 30 |
| MIXED_BUILD | 0 |
| TOTAL_RECONCILED | YES |
| SHELL_TO_USABLE_APP_P95 | 998 |
| NAV_TO_SHELL_P95 | 1434 (hard document; cert also used EXTERNAL classification) |
| FRESH wall P95 | 9242 (one 7487ms NAV_TO_SHELL sample) |
| Corrected VALIDATION_TO_NAV_P95 | 166 |
| UNSAFE_REPRICE_SKIPS | 0 |
| SUPPLIER_MUTATION_CALLS | 0 |
| Authority false | 1/30 (`return-fare-final02-18`): `requires_fare_change_acceptance=false`, `price_needs_refresh=true`, bound ids present, Traveler POST=1. Class: **EXPECTED_STALE_AUTHORITY**. Safety fallback retained. |
| Ordinary CLIENT_SOFT | APPLICATION_CONTROLLED_MULTI_SECOND_ROUTE_COUNT=1 (`home_to_support` client residual 2088; RSC 1474) |

PERFORMANCE_FINAL_STATUS=`PASS_WITH_PROVEN_EXTERNAL_NETWORK_OR_SUPPLIER_FLOOR`

## Email (this change)

Shared layout: stacked `info-row` (no 2-col squeeze), preheader without ZWNJ filler, plaintext converter strips hidden preheader. Digest/wallet/group detail fields + sample KPIs. Footer Manage-booking suppressed for operational/digest/group/auth. Role greeting fallback (not “User”).

Local family render `jp-email-prod-qa-03r2` + Chromium 1440/390/360: OVERFLOW_X=0 WORD_FRAGMENTATION=0.

GMAIL_INBOX_VERIFICATION=`PENDING_CHATGPT`

Do not resend the 163 inventory. Targeted live SMTP is after this SHA is on production Laravel.
