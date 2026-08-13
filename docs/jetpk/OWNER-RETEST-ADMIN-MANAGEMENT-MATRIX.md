# OWNER RETEST — Admin management matrix (W2-36)

OWNER_UAT_WAVE_2=REOPENED_OWNER_RETEST_GAPS
ADMIN_FULL_MANAGEMENT_SYSTEM=NO
ADMIN_REQUIRED_MANAGEMENT_GAPS=1
ADMIN_READ_ONLY_PLACEHOLDERS=0
ADMIN_FAKE_OPERATIONAL_PAGES=0
ADMIN_AMBIGUOUS_ACTIONS=0

SSH_AGENT_AUTH_RESTORED=PASS
SSH_CURSOR_AUTH=PASS
SFTP_AUTH=PASS
SSH_CLASSIFICATION=SSH_AGENT_AUTH_RESTORED
REMOTE_HEAD=8d79f0c762255f7f0bd6eee1fd659e640110c9cc
PRODUCTION_DEPLOYED=NO
PRODUCTION_BROWSER=FAIL
OLS_HASH=612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c
OLS=MATCH

Counts (code on branch; production deploy of this batch pending after dashboard build):

- FULL_MANAGEMENT: Dashboard KPIs, CMS Pages, Media Library, Homepage Page Settings (structured hero/routes/destinations/deals/support CTA), My Profile, Organization, Users, Staff, Agents, Agent Applications, Roles catalogue (account-type; mutations on Users/Staff), Go-live checklist (live validators + deep links; no fake complete)
- SAFETY_CONTROLLED: Markups, API Connections, Bookings, Execution, Cancellations, Tickets, Payments, Deposits, Commissions, Support
- READ_ONLY_BY_DESIGN: Audit, System Health, Reports (filter/export), PNRs host view
- NOT_APPLICABLE: CMS Banners, CMS Notices (no CmsBanner/CmsNotice tables; public site uses pages + homepage Page Settings)
- BLOCKED: none (SSH restored)

Remaining required gap: production browser verification of the accumulated management batch after a successful Dashboard build/restart.

SABRE_GDS_SUPPORTED=YES (SabreGdsTicketingService installed)
SABRE_NDC_SUPPORTED=YES (Offer search/price + Order create adapters installed)
SABRE_NDC_ENABLED=connection setting default false
UI: NDC = integrated, default off; not inferred from provider==Sabre alone

Roles: JetPakistan uses account-type catalogue + staff_permissions. No Spatie custom-role Create Role entity.

Go-live: validator_with_deep_links. Commercial UAT item stays ok=false by design.
