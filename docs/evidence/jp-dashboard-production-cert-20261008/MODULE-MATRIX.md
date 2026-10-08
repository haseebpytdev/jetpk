# JP-DASH-PROD-01 Module Matrix

Date: 2026-10-08  
Evidence: `certification-result.json`

| MODULE | VISIBLE_IN_PROD | READ_SOURCE | LIVE_DATA | WRITE_EXPECTED | PROD_WRITE_EXECUTED | RBAC | VISUAL | MOCK/PREVIEW | RESULT |
|--------|-----------------|-------------|-----------|----------------|---------------------|------|--------|--------------|--------|
| Dashboard | yes | laravelLive | yes | no | n/a | PASS | PARTIAL (hydration #418) | NO | PARTIAL |
| Bookings | yes | laravelLive | yes | yes | read PASS / write partial | PASS | PASS | NO | PARTIAL |
| Payments | yes | laravelLive | yes | yes | not executed this pass | PASS | PASS | NO | PARTIAL |
| PNRs | yes | laravelLive | yes | supplier-gated | not executed | PASS | PASS | NO | READ_OK |
| Tickets | yes | laravelLive | yes | supplier-gated | not executed | PASS | PASS | NO | READ_OK |
| Live Operations | yes (`/operations/inbox`) | laravelLive | yes | yes | not executed | PASS | PASS | NO | PARTIAL |
| Cancellations | no top-level route | laravelLive via ops | yes | yes | fixture created | PASS | n/a | NO | HIDDEN_ROUTE |
| Execution | no | n/a | n/a | n/a | n/a | n/a | n/a | n/a | HIDDEN_UNIMPLEMENTED |
| Notification Failures | no | n/a | n/a | n/a | n/a | n/a | n/a | n/a | HIDDEN_UNIMPLEMENTED |
| Customers | yes | laravelLive | yes | yes | not executed | PASS | PASS | NO | PARTIAL |
| Agents | yes | laravelLive | yes | yes | not executed | PASS | PASS | NO | PARTIAL |
| Users | yes | laravelLive | yes | yes | not executed | PASS | PASS | NO | PARTIAL |
| Roles | merged under Users | laravelLive | yes | yes | not executed | PASS | n/a | NO | READ_ONLY_BY_DESIGN |
| Permissions | merged under Users | laravelLive | yes | yes | not executed | PASS | n/a | NO | READ_ONLY_BY_DESIGN |
| Staff | yes | laravelLive | yes | yes | not executed | PASS | PASS | NO | PARTIAL |
| Settings Hub | yes | laravelLive | yes | yes | not executed | PASS | PASS | NO | PARTIAL |
| Suppliers | yes | laravelLive | yes | read-focused | not executed | PASS | PASS | NO | READ_OK |
| API Connections | yes | laravelLive | yes | yes | modal PASS | PASS | PASS | NO | PASS |
| Markups | yes | laravelLive | yes | yes | fixture created | PASS | PASS | NO | PARTIAL |
| Commissions | yes | laravelLive | yes | yes | not executed | PASS | PASS | NO | PARTIAL |
| CMS Pages | yes | laravelLive | yes | yes | not executed | PASS | PASS | NO | PARTIAL |
| CMS Assets | yes | laravelLive | yes | yes | not executed | PASS | PASS | NO | PARTIAL |
| Reports | yes | laravelLive | yes | no | n/a | PASS | PASS | NO | READ_ONLY_BY_DESIGN |
| Audit | yes | laravelLive | yes | no | n/a | PASS | PASS | NO | READ_ONLY_BY_DESIGN |
| Support | yes | laravelLive | yes | yes | fixture created | PASS | PASS | NO | PARTIAL |
| Customer Queries | yes | laravelLive | yes | yes | not executed | PASS | PASS | NO | PARTIAL |
| Group Ticketing | yes | laravelLive | yes | yes | not executed | PASS | PASS | NO | PARTIAL |
| SEO | yes | laravelLive | yes | yes | not executed | PASS | PASS | NO | PARTIAL |
| Go-live | yes | laravelLive | yes | yes | not executed | PASS | PASS | NO | PARTIAL |
