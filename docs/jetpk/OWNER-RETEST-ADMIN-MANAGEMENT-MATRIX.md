# OWNER RETEST — Admin management matrix (W2 Owner Retest V2)

OWNER_UAT_WAVE_2=REOPENED_OWNER_RETEST_V2_FINDINGS
ADMIN_FULL_MANAGEMENT_SYSTEM=NO
OWNER_RETEST_V2=FAIL
ADMIN_REQUIRED_MANAGEMENT_GAPS=>0
JP_REL_01=PROHIBITED
OTP_RESTORE=PROHIBITED
QA_USER_SUSPEND=PROHIBITED

LAST_EXTERNALLY_VERIFIED_REMOTE_HEAD=d14f454e961bafa0cf478e5a3879ff892a6ddeac
LATEST_ENGINEERING_SHA=LOCAL_UNCOMMITTED_THEN_THIS_COMMIT
PRODUCTION_BUILD_ID=wCv83IB_v-tlTUR4XVckJ
REOPENED_AFTER=Owner production retest invalidated PASS_READY_FOR_OWNER_RETEST_V2

TRACKED_WORKTREE_CLEAN=NO
FULL_WORKTREE_CLEAN=NO
PROTECTED_TMP_FILES_PRESERVED=YES
RBAC_ROLE_PERMISSION_MANAGEMENT=HARD_STOP_PENDING_OWNER_SCHEMA_APPROVAL

Do not set ADMIN_FULL_MANAGEMENT_SYSTEM=YES.
Do not set OWNER_UAT_WAVE_2=PASS_READY_FOR_OWNER_RETEST.

## Gate status (no PASS labels without production evidence)

| Gate | Status |
|---|---|
| ADMIN_FINANCIAL_PKR | OPEN — quote-time PKR from pricing_components; hold persist no longer overlays supplier USD; rows keep original ISO; KPI uses snapshot only. Production new-booking proof still required. |
| MARKUP_BUSINESS_RULE_BUILDER | OPEN — Apply-to modes, airport/airline selectors, English preview. No production markup mutation for automated proof. |
| SETTINGS_SOURCE_OF_TRUTH | OPEN — Org Profile writes agency settings; Current Values read same agency. Production save→reload proof still required. |
| NOTIFICATION_SETTINGS_MANAGEMENT | OPEN — enable/email/dashboard/severity/delivery/roles persist in existing JSON/meta. Failed KPI operational-only. Production proof still required. |
| SUPPLIER_REGISTRY_TRUTH | OPEN — registry states exist on connections and supplier rows. Production state matrix still required. |
| SUPPLIER_MANAGEMENT | OPEN — business display name edit without credential rotation; PKR booking value pipeline shared. Production proof still required. |
| API_CONNECTION_FULL_MANAGEMENT | OPEN — Manage tabs + name/environment/masked credentials. Status preserve on name-only save. Production proof still required. |
| CMS_FULL_MANAGEMENT | OPEN — live cms_pages; structured data-jp-block reorder without raw HTML as primary UX. Schema not applied. |
| CMS_PREVIEW_PUBLISH | OPEN — draft/publish + in-editor viewport preview. Production proof still required. |
| MEDIA_LIBRARY | OPEN — upload/preview/copy URL/remove. Production proof still required. |
| USERS_MANAGEMENT | OPEN — list/create/invite/activate/suspend exist. Production lifecycle proof still required. |
| STAFF_MANAGEMENT | OPEN — staff create + permission editor exist. Production proof still required. |
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

Commit `36534b58764fbb523fa1127eab9908407306ba2e` was a fixture Next page-builder, not Laravel live CMS. Live persistence is `cms_pages.content` (HTML longText) with `data-jp-block` sections. No CMS schema migration applied.


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
