# OWNER RETEST — Admin management matrix (W2 Owner Retest V2)

OWNER_UAT_WAVE_2=REOPENED_OWNER_RETEST_V2_FINDINGS
ADMIN_FULL_MANAGEMENT_SYSTEM=NO
OWNER_RETEST_V2=FAIL
ADMIN_REQUIRED_MANAGEMENT_GAPS=>0
JP_REL_01=PROHIBITED
OTP_RESTORE=PROHIBITED
QA_USER_SUSPEND=PROHIBITED

LAST_EXTERNALLY_VERIFIED_DOCS_HEAD=d8c2f4c1
REOPENED_AFTER=Owner production retest invalidated PASS_READY_FOR_OWNER_RETEST_V2
DASHBOARD_BUILD_ID_AFTER_V2_REBUILD=yhj-z7hp1n8rv-ZaOSIyC

Do not set ADMIN_FULL_MANAGEMENT_SYSTEM=YES until Owner production retest actually closes every gate below.

## Module reclassification

| Module | Previous | Current | Notes |
|---|---|---|---|
| Financials / GBV KPI | PASS (USD truthful) | CODE_READY_PENDING_OWNER | KPI is Rs. from booking-time PKR snapshots; legacy Non-PKR excluded with count; row truth stays original ISO |
| Markups | PARTIAL | CODE_READY_PENDING_OWNER | Business Apply-to builder; MarkupRule engine unchanged; no raw JSON for normal Admin |
| Settings | FAIL | CODE_READY_PENDING_OWNER | Organization profile feeds Current Values (email/phone/timezone) |
| Notifications | READ_ONLY | CODE_READY_PENDING_OWNER | Category write path; Failed notifications KPI is operational-only; QA history retained |
| Suppliers | ANALYTICS | IN_PROGRESS | PKR booking value from same money pipeline; registry Pending enum bug fixed to Testing |
| API Connections | PARTIAL | IN_PROGRESS | Manage + provider labels + registry; secrets never shown |
| CMS Pages | FAIL | IN_PROGRESS | Live HTML pages CRUD + duplicate/archive/remove + HTML blocks; fixture page-builder hidden in live |
| Media Library | FAIL (CMS-LIVE-MODULE-UNAVAILABLE) | CODE_READY_PENDING_OWNER | `/cms/assets`; no CMS-LIVE-MODULE-UNAVAILABLE throw |
| Homepage | PARTIAL | IN_PROGRESS | Structured homepage panel + public preview; not a block page-builder |
| Banners / Notices | FAIL | REMOVED_FROM_NAV | Routes now show CMS overview; no domain tables |
| Users | FAIL (2450456559) | CODE_READY_PENDING_OWNER | Detail catalog import + permission transformer; Users list loaded in V2 reverify |
| Staff | OPEN | IN_PROGRESS | Same users directory `scope=staff` |
| Roles & Permissions | READ_ONLY | HARD_STOP | Custom role-matrix persistence needs schema. Staff permission assignment already exists. Do not fake Next persistence. |

## Finding 10 — RBAC schema plan (DO NOT APPLY)

Current model: account-type catalog (`DashboardRoleCatalog`) plus staff permission flags. There is no `roles` / `role_permissions` table for Admin-defined custom roles.

Required before RBAC_ROLE_PERMISSION_MANAGEMENT can be honest:

1. `roles` — id, agency_id nullable, name, slug, is_system, is_protected, created_by, timestamps
2. `role_permissions` — role_id, permission_key, granted, timestamps; unique(role_id, permission_key)
3. `role_user` — role_id, user_id, assigned_by, timestamps
4. Seed protected system roles from the existing catalog
5. Last-admin, self-lockout, and privilege-escalation guards in the write service

Stop here until Owner approves the migration. Do not apply.

## CMS page-builder truth

Commit `36534b58764fbb523fa1127eab9908407306ba2e` was a fixture Next page-builder, not Laravel live CMS. Live persistence is `cms_pages.content` (HTML longText). HTML section blocks are stored in that column. A separate blocks schema is not required for that path.

## Gates still required before any PASS_READY label

ADMIN_FINANCIAL_PKR
MARKUP_BUSINESS_RULE_BUILDER
SETTINGS_SOURCE_OF_TRUTH
NOTIFICATION_SETTINGS_MANAGEMENT
SUPPLIER_REGISTRY_TRUTH
SUPPLIER_MANAGEMENT
API_CONNECTION_FULL_MANAGEMENT
CMS_FULL_MANAGEMENT
CMS_PREVIEW_PUBLISH
MEDIA_LIBRARY
USERS_MANAGEMENT
RBAC_ROLE_PERMISSION_MANAGEMENT
