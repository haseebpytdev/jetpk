# OWNER RETEST — Admin management matrix (W2-36)

OWNER_UAT_WAVE_2=REOPENED_OWNER_RETEST_GAPS
ADMIN_FULL_MANAGEMENT_SYSTEM=NO
ADMIN_REQUIRED_MANAGEMENT_GAPS=2
ADMIN_READ_ONLY_PLACEHOLDERS=0
ADMIN_FAKE_OPERATIONAL_PAGES=0
ADMIN_AMBIGUOUS_ACTIONS=0

SSH_AGENT_AUTH_RESTORED=PASS
SSH_CURSOR_AUTH=PASS
SFTP_AUTH=PASS
REMOTE_HEAD=a8a7c527
PRODUCTION_DEPLOYED=PARTIAL
PRODUCTION_BROWSER=PARTIAL
OLS_HASH=612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c
OLS=MATCH

JETPK_PHP_CLI=/usr/bin/php (8.3.6, PDO without pdo_mysql)
JETPK_RUNTIME_PHP=/usr/local/lsws/lsphp83/bin/lsphp (8.3.31)
JETPK_PHP_PDO_MYSQL_BLOCKER=NO
artisan optimize:clear via lsphp artisan = exit 0

Authenticated Admin shell: https://jetpakistan.pk/admin/dashboard (QA platform admin). Unauthenticated /admin/dashboard 404 is not a failure.

Money (sampled booking WL96PKN9):
- booking.currency=PKR
- fareBreakdown.currency=USD, fare=623.73
- converted_total_pkr / customer_total_pkr / displayed_total_pkr = absent
- hold converted_total_pkr = null
- presenter = USD 624.00 (fareBreakdown.currency)
- No Amount unavailable
- Reports showed Rs. 590.00 on at least one report row
- Do not relabel this USD fare as PKR

Nav: primary `aria-current=page` is the module link. Footer Contact Support uses accent styling and must not be counted as a second primary nav item.

Agent applications: parse error in DashboardAgentApplicationsReadService repaired in a8a7c527.

Homepage structured controls live on CMS sections module (`HomepageSettingsPanel`), not the CMS index.

Counts:
- FULL_MANAGEMENT: Dashboard KPIs, CMS Pages, Media Library, Homepage Page Settings, Profile, Organization, Users, Staff, Agents, Roles catalogue, Go-live validators
- SAFETY_CONTROLLED: Markups, API Connections, Bookings, Execution, Cancellations, Tickets, Payments, Deposits, Commissions, Support
- READ_ONLY_BY_DESIGN: Audit, System Health, Reports, PNRs
- NOT_APPLICABLE: CMS Banners, CMS Notices
- BLOCKED: none

Remaining gaps:
1. Owner PKR KPI cannot be declared PASS without booking-time PKR snapshots (inventing FX is forbidden).
2. Re-verify Agent Applications + CMS sections after a8a7c527 dashboard rebuild.
