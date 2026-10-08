# JP-DASH-PROD Defect Ledger

| DEFECT_ID | DESCRIPTION | ROOT_CAUSE | FIX_SHA | DEPLOYED_SHA | PROD_RETEST | STATUS |
|-----------|-------------|------------|---------|--------------|-------------|--------|
| HYDR-418 | React hydration #418 (historical) | ThemeProvider resolved theme could diverge from bootstrap `data-theme` before mount | `744c45f9` | pending | 0 hydration errors on baseline production cert (9e26779) | PREVENTIVE_HARDENING_PENDING_DEPLOY |
| RBAC-CUST-AGENT | Customer agent portal access concern | Harness false positive; Next.js redirects customer to `/customer/dashboard` | harness `15fb0a82` | N/A | PASS redirect, no agent shell | CLOSED |
| BOOK-NOTE-404 | Booking internal note POST 404 | Laravel route binding numeric id only; dashboard sends `booking_reference` | `744c45f9` | **not deployed** | HTTP 404 on `JPQA-20261008-BOOKING` | OPEN_PRODUCTION_DEFECT |
| CERT-WRITES | Safe write matrix incomplete | Certification scope remaining after deploy | PROD-03 | N/A | partial (markups + API create only) | OPEN_CERT_WORK |

OPEN_CODE_DEFECTS=0
OPEN_PRODUCTION_DEFECTS=1
PENDING_PRODUCTION_FIX=booking_reference route binding

Do not claim OPEN_PRODUCTION_DEFECTS=0 until booking-note fix is deployed and verified in production browser with DB persistence proof.
