# OWNER RETEST — Admin management matrix (W2-36)

OWNER_UAT_WAVE_2=REOPENED_OWNER_RETEST_GAPS
ADMIN_FULL_MANAGEMENT_SYSTEM=NO
ADMIN_REQUIRED_MANAGEMENT_GAPS=3
ADMIN_READ_ONLY_PLACEHOLDERS=0
ADMIN_FAKE_OPERATIONAL_PAGES=0

SSH_CLASSIFICATION=SSH_SERVER_REJECTED_KEY
REMOTE_HEAD_AT_LOOP_START=98aa95389bd6e9862128ba84ebbc79d6256f4fda
PRODUCTION_DEPLOYED=NO
PRODUCTION_BROWSER=FAIL
OLS_HASH=UNVERIFIED

Counts (code on branch; not production-verified):

- FULL_MANAGEMENT: Dashboard KPIs, CMS Pages, Media Library, Homepage Page Settings, My Profile, Organization, Users, Staff, Agents, Agent Applications, Page Settings, Roles catalogue (account-type; mutations on Users/Staff)
- SAFETY_CONTROLLED: Markups, API Connections, Bookings/Execution/Cancellations/Tickets/Payments/Deposits/Commissions/Support
- READ_ONLY_BY_DESIGN: Audit, System Health, Reports (filter/export), PNRs host view
- NOT_APPLICABLE: CMS Banners, CMS Notices (no CmsBanner/CmsNotice tables; public site uses pages + homepage Page Settings)
- BLOCKED: production deploy (SSH_SERVER_REJECTED_KEY)

Remaining required gaps: Go-live checklist management vs read, Execution/Support OTA parity confirmation, production verification of money/nav/connections.

SABRE_GDS_SUPPORTED=YES (SabreGdsTicketingService installed)
SABRE_NDC_SUPPORTED=YES (Offer search/price + Order create adapters installed)
SABRE_NDC_ENABLED=connection setting default false
UI: NDC = integrated, default off; not inferred from provider==Sabre alone

Media Library: implemented in 98aa9538 (list/upload/remove JSON + Next panel). Not production verified.
