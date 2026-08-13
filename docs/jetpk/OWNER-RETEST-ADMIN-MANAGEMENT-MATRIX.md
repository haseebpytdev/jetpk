# OWNER RETEST — Admin management matrix (W2 Owner Retest V3 ready)

OWNER_UAT_WAVE_2=PASS_READY_FOR_OWNER_RETEST_V3
ADMIN_FULL_MANAGEMENT_SYSTEM=YES
ADMIN_REQUIRED_MANAGEMENT_GAPS=0
OWNER_RETEST_V2=ENGINEERING_CLOSED_AWAITING_OWNER_RETEST_V3
JP_REL_01=PROHIBITED
OTP_RESTORE=PROHIBITED
QA_USER_SUSPEND=PROHIBITED

LAST_EXTERNALLY_VERIFIED_REMOTE_HEAD=6d019160ff23d5d8c14fc50d58606b4e52d63925
LATEST_ENGINEERING_SHA=6d019160ff23d5d8c14fc50d58606b4e52d63925
LATEST_DOCS_SHA=f4ea1b93d4a914faef741744c91c36189ff990fa
PRODUCTION_BUILD_ID=7XX2vpVISL5H9S6kjpnqj
PRODUCTION_PHP_SHA=6d019160
OLS_HTTP_CONFIG_SHA256=612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c
OLS_STATE=MATCH
USER_ACCESS_MANAGEMENT_CRUD_TEST=PASS
FULL_ADMIN_REGRESSION=PASS
FULL_REGRESSION=PASS
CROSS_PORTAL_REGRESSION=PASS
FINAL_SOURCE_PARITY=PASS
FINAL_BUILD_RUNTIME=PASS
FINAL_OLS_INTEGRITY=PASS
COMMERCIAL_QA_SIDE_EFFECTS=0
SECRET_EXPOSURE=0
BROKEN_INTERNAL_LINKS=0
UNHANDLED_PRODUCTION_API_ERRORS=0
SAFE_ACTIONABLE_TASKS_REMAINING=0
SAFE_NON_MIGRATION_GAPS_REMAINING=0
RBAC_SCHEMA_APPROVAL_REQUIRED=NO
RBAC_SCHEMA=PASS
RBAC_SEED=PASS
RBAC_BACKFILL=PASS
RBAC_BACKFILL_PERMISSION_DRIFT=0
RBAC_ROLE_PERMISSION_MANAGEMENT=PASS
RBAC_SECURITY_GUARDS=PASS
CROSS_PORTAL_RBAC=PASS
LOCAL_REMOTE_RECONCILIATION=PASS
FINAL_DOCUMENTATION_RECONCILIATION=PASS

TRACKED_WORKTREE_CLEAN=YES
FULL_WORKTREE_CLEAN=NO
PROTECTED_TMP_FILES_PRESERVED=YES

Classification key:

- PASS_ENGINEERING_OWNER_CONFIRMATION_PENDING = product capability complete and safely proven; live commercial mutation intentionally not executed
- SAFETY_CONTROLLED = write path exists; live pricing/money/credential/supplier mutation not executed
- PASS = capability proven in tests and production QA (RBAC writable management)

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
| RBAC_ROLE_PERMISSION_MANAGEMENT | PASS | Additive `roles` / `role_permissions` / `role_user` on MariaDB 10.11.18. Uniqueness: `UNIQUE(scope_key, slug)` with `scope_key=platform` or agency id string (NULL unique is unsafe). Dual-read: new assignments preferred; AccountType + `users.meta.staff_permissions` retained. Migration `2026_08_13_220000_create_rbac_roles_tables` Ran. Seed: 6 system roles, 12 assignments, 61 role_permissions, drift=0, qa_left=0. Next Admin write UI production-proved. |

## Remaining after this engineering close

ADMIN_REQUIRED_MANAGEMENT_GAPS=0  
ADMIN_FULL_MANAGEMENT_SYSTEM=YES  

Other management gates remain `PASS_ENGINEERING_OWNER_CONFIRMATION_PENDING` pending Owner Retest V3 commercial confirmation. RBAC is engineering-PASS.

RBAC production facts:

- Engine: MariaDB 10.11.18-MariaDB-ubu2404 / `jetpk_prod`
- Backup: `/home/pkjetp/backups/rbac-pre-6d019160.sql`
- Rollback: restore dump or drop additive tables only (AccountType + staff meta unchanged)
- Dual-read: ON
- Production UI: create/clone/edit/grant/assign/unassign/delete QA custom roles PASS; protected system delete 403
- Negative: Staff/Agent/Agent Staff/Customer cannot write RBAC (403 / access-denied)

## CMS page-builder truth

Commit `36534b58764fbb523fa1127eab9908407306ba2e` was a fixture Next page-builder, not Laravel live CMS. Live persistence is `cms_pages.content` (HTML longText) with `data-jp-block` sections. No CMS schema migration applied.
