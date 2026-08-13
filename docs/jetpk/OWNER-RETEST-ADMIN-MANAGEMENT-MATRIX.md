# OWNER RETEST — Admin management matrix (W2 Owner Retest V2)

OWNER_UAT_WAVE_2=REOPENED_OWNER_RETEST_V2_FINDINGS
ADMIN_FULL_MANAGEMENT_SYSTEM=NO
OWNER_RETEST_V2=FAIL
ADMIN_REQUIRED_MANAGEMENT_GAPS=>0
JP_REL_01=PROHIBITED
OTP_RESTORE=PROHIBITED
QA_USER_SUSPEND=PROHIBITED

LAST_EXTERNALLY_VERIFIED_REMOTE_HEAD=2b0111a8c07e2d5a2571c8ff6ba388498f1b82f7
REOPENED_AFTER=Owner production retest invalidated PASS_READY_FOR_OWNER_RETEST_V2

Do not set ADMIN_FULL_MANAGEMENT_SYSTEM=YES.
Do not set OWNER_UAT_WAVE_2=PASS_READY_FOR_OWNER_RETEST.

## Gate status (no PASS labels)

| Gate | Status |
|---|---|
| ADMIN_FINANCIAL_PKR | OPEN — GBV is Rs. from PKR snapshots only; legacy USD rows excluded; new holds must not copy USD into PKR |
| MARKUP_BUSINESS_RULE_BUILDER | OPEN — business Apply-to exists; Owner usability proof still required |
| SETTINGS_SOURCE_OF_TRUTH | OPEN — Current Values must read the same agency settings as Organization profile |
| NOTIFICATION_SETTINGS_MANAGEMENT | OPEN — category write path exists; Owner proof still required |
| SUPPLIER_REGISTRY_TRUTH | OPEN — registry states exist; UI must not treat uninstalled adapters as production failures |
| SUPPLIER_MANAGEMENT | OPEN — analytics + registry; business master still limited |
| API_CONNECTION_FULL_MANAGEMENT | OPEN — Manage tabs exist; secrets never shown |
| CMS_FULL_MANAGEMENT | OPEN — live cms_pages HTML CRUD, not the fixture page-builder |
| CMS_PREVIEW_PUBLISH | OPEN — draft/preview/publish on cms_pages; homepage uses Page Settings panel |
| MEDIA_LIBRARY | OPEN — /cms/assets; CMS-LIVE-MODULE-UNAVAILABLE removed |
| USERS_MANAGEMENT | OPEN — Users/Permissions no longer 500; Owner lifecycle proof still required |
| RBAC_ROLE_PERMISSION_MANAGEMENT | HARD_STOP_PENDING_OWNER_SCHEMA_APPROVAL |

## Finding 10 — RBAC schema plan (DO NOT APPLY)

Current model: account-type catalog (`DashboardRoleCatalog`) plus staff permission flags. There is no `roles` / `role_permissions` table for Admin-defined custom roles.

Required before this gate can leave HARD_STOP:

1. `roles` — id, agency_id nullable, name, slug, is_system, is_protected, created_by, timestamps
2. `role_permissions` — role_id, permission_key, granted, timestamps; unique(role_id, permission_key)
3. `role_user` — role_id, user_id, assigned_by, timestamps
4. Seed protected system roles from the existing catalog
5. Last-admin, self-lockout, and privilege-escalation guards in the write service

Return this migration decision to Owner only after every unrelated safe gap is closed.

## CMS page-builder truth

Commit `36534b58764fbb523fa1127eab9908407306ba2e` was a fixture Next page-builder, not Laravel live CMS. Live persistence is `cms_pages.content` (HTML longText).
