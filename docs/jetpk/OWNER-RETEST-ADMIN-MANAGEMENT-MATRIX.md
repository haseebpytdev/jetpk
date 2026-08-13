# OWNER RETEST — Admin management matrix (W2 Owner Retest V2)

OWNER_UAT_WAVE_2=REOPENED_OWNER_RETEST_V2_FINDINGS
ADMIN_FULL_MANAGEMENT_SYSTEM=NO
OWNER_RETEST_V2=FAIL
ADMIN_REQUIRED_MANAGEMENT_GAPS=>0
JP_REL_01=PROHIBITED
OTP_RESTORE=PROHIBITED
QA_USER_SUSPEND=PROHIBITED

LAST_EXTERNALLY_VERIFIED_REMOTE_HEAD=0de0652bb0077f8f6fe9cf61c17985cd357f9e6a
LATEST_ENGINEERING_SHA=PENDING_THIS_COMMIT
PRODUCTION_BUILD_ID=l1GmGe19AuTVY_mTSFwS4
REOPENED_AFTER=Owner production retest invalidated PASS_READY_FOR_OWNER_RETEST_V2

TRACKED_WORKTREE_CLEAN=PENDING_THIS_COMMIT
FULL_WORKTREE_CLEAN=NO
PROTECTED_TMP_FILES_PRESERVED=YES
RBAC_ROLE_PERMISSION_MANAGEMENT=HARD_STOP_PENDING_OWNER_SCHEMA_APPROVAL

Do not set ADMIN_FULL_MANAGEMENT_SYSTEM=YES.
Do not set OWNER_UAT_WAVE_2=PASS_READY_FOR_OWNER_RETEST or PASS_READY_FOR_OWNER_RETEST_V2.

Classification key:

- PASS_ENGINEERING_OWNER_CONFIRMATION_PENDING = product capability complete and safely proven; live commercial mutation intentionally not executed
- SAFETY_CONTROLLED = write path exists; live pricing/money/credential/supplier mutation not executed
- HARD_STOP = Owner schema approval required

## Gate status

| Gate | Status | Classification |
|---|---|---|
| ADMIN_FINANCIAL_PKR | PASS_ENGINEERING_OWNER_CONFIRMATION_PENDING | Fixture USD/SAR/PKR holds persist commercial PKR; fare keeps supplier ISO; GBV is Rs. snapshot; 10/10 OwnerRetestV2 closure tests + BookingPkrSnapshot unit tests. No live supplier booking. |
| MARKUP_BUSINESS_RULE_BUILDER | PASS_ENGINEERING_OWNER_CONFIRMATION_PENDING | Apply-to modes, origin/destination, English preview, Advanced priority. Inactive JSON create/toggle/delete without applies_to JSON. SAFETY_CONTROLLED: no live pricing mutation. |
| SETTINGS_SOURCE_OF_TRUTH | PASS_ENGINEERING_OWNER_CONFIRMATION_PENDING | Org Profile JSON GET/PATCH round-trip matches Settings Current Values; audit `agency.branding_settings_updated`. Production save already proved earlier this wave. |
| NOTIFICATION_SETTINGS_MANAGEMENT | PASS_ENGINEERING_OWNER_CONFIRMATION_PENDING | Category JSON enable/email/dashboard/severity/delivery/roles. Failures classifier splits CURRENT_OPERATIONAL_FAILURES vs HISTORICAL_QA_FAILURES. No genuine history deleted. |
| SUPPLIER_REGISTRY_TRUTH | PASS_ENGINEERING_OWNER_CONFIRMATION_PENDING | Six-state matrix unit-tested. Production currently shows CONFIGURED_ENABLED for installed providers; that is not a remaining capability gap. |
| SUPPLIER_MANAGEMENT | PASS_ENGINEERING_OWNER_CONFIRMATION_PENDING | List/detail/analytics + business display name. Name-only PATCH preserves credentials, settings, status, and base URL. |
| API_CONNECTION_FULL_MANAGEMENT | PASS_ENGINEERING_OWNER_CONFIRMATION_PENDING | Manage tabs, masked credentials, enable/disable, safe test contract. Credential rotation SAFETY_CONTROLLED (not executed). Name-only update no longer wipes settings/Sabre channels. |
| CMS_FULL_MANAGEMENT | PASS_ENGINEERING_OWNER_CONFIRMATION_PENDING | Pages JSON CRUD, duplicate, archive, approved block catalogue, reorder/hide/duplicate/remove, field configure without raw HTML. Production list may be empty until a QA page is created. |
| CMS_PREVIEW_PUBLISH | PASS_ENGINEERING_OWNER_CONFIRMATION_PENDING | Draft, admin preview, publish, unpublish/archive proven by tests. Homepage remains Page Settings draft/preview/publish. |
| MEDIA_LIBRARY | PASS_ENGINEERING_OWNER_CONFIRMATION_PENDING | Upload/list/preview/copy URL/alt update/remove. `/cms/assets` no longer shows a false empty-filter state over the live panel. |
| USERS_MANAGEMENT | PASS_ENGINEERING_OWNER_CONFIRMATION_PENDING | Create/invite/edit/suspend/activate/reset covered by `UserAccessManagementCrudTest`. Protected Owner-UAT identities not mutated. |
| STAFF_MANAGEMENT | PASS_ENGINEERING_OWNER_CONFIRMATION_PENDING | Create staff + permissions editor covered by existing staff/RBAC feature tests. Protected identities not deactivated. |
| RBAC_ROLE_PERMISSION_MANAGEMENT | HARD_STOP_PENDING_OWNER_SCHEMA_APPROVAL | Do not apply `roles` / `role_permissions` / `role_user` until Owner authorizes. |

## Remaining after this engineering close

SAFE_NON_MIGRATION_GAPS_REMAINING is the production deploy + regression/parity/OLS package, not missing Admin capabilities (except RBAC schema).

RBAC schema plan (DO NOT APPLY) remains:

1. `roles` — id, agency_id nullable, name, slug, is_system, is_protected, created_by, timestamps
2. `role_permissions` — role_id, permission_key, granted, timestamps; unique(role_id, permission_key)
3. `role_user` — role_id, user_id, assigned_by, timestamps
4. Seed protected system roles from `DashboardRoleCatalog`
5. Last-admin, self-lockout, and privilege-escalation guards

## CMS page-builder truth

Commit `36534b58764fbb523fa1127eab9908407306ba2e` was a fixture Next page-builder, not Laravel live CMS. Live persistence is `cms_pages.content` (HTML longText) with `data-jp-block` sections. No CMS schema migration applied.
