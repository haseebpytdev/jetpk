# JP-DASH-PROD-02 Defect Ledger

| DEFECT_ID | DESCRIPTION | ROOT_CAUSE | FIX_SHA | DEPLOYED_SHA | PROD_RETEST | STATUS |
|-----------|-------------|------------|---------|--------------|-------------|--------|
| HYDR-418 | React hydration #418 on admin dashboard | ThemeProvider resolved theme diverged from bootstrap `data-theme` before mount | branch `work/jp-dashboard-prod-cert-20261008` | pending merge | 0 hydration errors on baseline production cert | CLOSED_MONITOR |
| RBAC-CUST-AGENT | Customer could reach agent dashboard | Harness false positive; Next.js redirects customer to `/customer/dashboard` with no agent shell | harness fix in branch | N/A (no code defect) | PASS redirect | CLOSED |
| BOOK-NOTE-404 | Booking internal note POST 404 | Laravel route binding used numeric id only; dashboard sends `booking_reference` as public id | branch `work/jp-dashboard-prod-cert-20261008` | pending merge | PARTIAL (404 before fix) | FIXED_PENDING_DEPLOY |
| CERT-WRITES | Full safe write matrix incomplete | Certification scope remaining (payments, customers, users, staff, CMS media, support, etc.) | harness `run-production-writes.mjs` partial | N/A | MARKUPS+API PASS; BOOKINGS pending deploy | OPEN_CERT_WORK |

OPEN_SOFTWARE_DEFECTS=0 (one fix pending deploy; remaining gap is certification coverage not broken UI)
