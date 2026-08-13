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
LATEST_ENGINEERING_SHA=0860c212
LATEST_DOCS_SHA=pending
REMOTE_HEAD=0860c212
PRODUCTION_DEPLOYED_STATE=PHP money/report files deployed; Dashboard BUILD_ID=AaH4udV7uE4WVSXUAmiKw
OLS_HASH=612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c
OLS=MATCH
JETPK_PHP_PDO_MYSQL_BLOCKER=NO

Money policy (deployed 0860c212):
- PKR KPI uses PKR presented totals only
- USD fares are not relabeled as Rs.
- Reports use fare_currency_iso when a single fare currency exists
- Mixed books: Rs. PKR total + Non-PKR excluded delta

Authenticated Admin walkthrough earlier this day: PASS for signed-in shell (19 pages).
Later re-login attempt landed on public login/access-denied (session/soft failure). Do not treat as OLS/SSH failure.

Remaining Admin gaps:
1. Re-establish Admin session and re-verify Agent Applications + CMS /cms/sections structured homepage after a8a7c527/0860c212.
2. Confirm dashboard/report labels on a fresh Admin session (USD vs Rs. policy).
