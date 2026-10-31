# SMTP id=5 — read-only proof

## Snapshot (before)

| Field | Value |
|---|---|
| id | 5 |
| agency_id | 1 |
| provider | smtp (string cast / retired) |
| name | SMTP JetPakistan LIVE |
| status | active |
| is_active | 1 |
| environment | live |
| credentials-present | YES |
| settings-present | YES |
| last_test_status | ready_for_review |

## Mail authority

| Check | Result |
|---|---|
| mail.default | smtp |
| MAIL_* host/user/pass/from | present (masked) |
| AgencyCommunicationSetting | 5 rows; **smtp_enabled=0** for all; agency SMTP host NULL |
| Code dependency on SupplierConnection provider=smtp | none found |
| RetiredSupplierProviders | includes `smtp` |

```
SMTP_SUPPLIER_ROW_REQUIRED_FOR_MAIL=NO
```
