# 14 — QA credential rotation

DATE: 2026-10-31  
ACTOR: `jp-dash-03-qa-admin` (platform_admin QA marker)  
REASON: Prior debugging briefly included a QA vault password in a failed GET login URL.

## Actions (no secrets recorded)

1. Generated new password into Windows Credential Manager target `JetPakistan-JP-DASH-03-QA-Admin` (overwrite).
2. Synced LIVE via `php artisan jetpk:dashboard-prod-cert-qa sync-password --role=admin` with password on STDIN only.
3. Command deletes `sessions` rows for that user.
4. Confirmed `Hash::check` PASS for new secret; `must_change_password=NO`.
5. Confirmed browser POST login (SPA form, not query-string) reaches `/admin/dashboard`.
6. Confirmed old vault secret login REJECTED.

## Flags

```
QA_PASSWORD_ROTATED=YES
OLD_PASSWORD_INVALID=YES
NEW_PASSWORD_NOT_COMMITTED=YES
NEW_PASSWORD_NOT_IN_URL=YES
QA_CREDENTIAL_ROTATION=PASS
GIT_PASSWORD_LEAK=NO
EVIDENCE_PASSWORD_LEAK=NO
CURRENT_LOGIN_URL_PASSWORD=NO
OLD_CREDENTIAL_RISK=MITIGATED_BY_ROTATION
```

Historical access/browser logs were not destructively edited.
