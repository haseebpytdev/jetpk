# OWNER UAT WAVE 2 — Progress Ledger

LAST_UPDATED_UTC: 2026-08-13T09:25:00Z  
BRANCH: `phase/jetpk-owner-uat-wave-2-admin-staff-business-closure`  
REMOTE_HEAD: `a8a7c527`  
WAVE_1_FROZEN: `741f7d370518b5a4f32452851202653d0df9911f`

## STATUS

`OWNER_UAT_WAVE_2` = **REOPENED_OWNER_RETEST_GAPS**

ADMIN_FULL_MANAGEMENT_SYSTEM=NO

SSH_AGENT_AUTH_RESTORED=PASS
SSH_CURSOR_AUTH=PASS
SFTP_AUTH=PASS
OLS=612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c MATCH

JETPK_PHP_PDO_MYSQL_BLOCKER=NO — generic `/usr/bin/php` lacks pdo_mysql; approved `/usr/local/lsws/lsphp83/bin/lsphp artisan optimize:clear` exit 0.

## THIS HEARTBEAT

- Authenticated Admin walkthrough on jetpakistan.pk succeeded (QA platform admin).
- Sampled booking WL96PKN9 has no PKR snapshot; presenter correctly keeps USD 624.00. Do not relabel as PKR.
- Agent Applications production RSC error traced to a PHP parse error in the read service; repaired and redeployed (`a8a7c527`).
- W2-37 not started while Admin PKR snapshot proof and applications retest remain.

## QA AUTH

OTP remains off. OTP_DEMO_* preserved. QA identities active (admin was re-activated from prior suspend; not newly created).


## STATUS

`OWNER_UAT_WAVE_2` = **REOPENED_OWNER_RETEST_GAPS**

ADMIN_FULL_MANAGEMENT_SYSTEM=NO (production verification still required)

SSH_AGENT_AUTH_RESTORED=PASS
SSH_CURSOR_AUTH=PASS
SFTP_AUTH=PASS
OLS=612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c MATCH

## THIS HEARTBEAT

- Owner restored ssh-agent; Cursor verified non-interactive SSH/SFTP. Stale SSH_SERVER_REJECTED_KEY superseded.
- Extracted committed management tar onto `/home/pkjetp/jetpk_app`. Laravel hashes matched local HEAD.
- First production `npm run build` failed: client `ApiConnectionsWorkspace` imported server-only `supplier-service`.
- Follow-up code: browser JSON list for API connections; structured homepage controls; go-live live validators + deep links; support selected ticket + customer-visible reply.
- Not marked PRODUCTION_DEPLOYED until Dashboard build + `jetpk-dashboard` restart succeed.

## QA AUTH

OTP temporary Owner-UAT remains. OTP_DEMO_* preserved. QA identities active. Not restored. Not suspended.
