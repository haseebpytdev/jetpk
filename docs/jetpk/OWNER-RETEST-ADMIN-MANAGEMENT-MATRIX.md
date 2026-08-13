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

## Module reclassification

| Module | Previous | Current | Notes |
|---|---|---|---|
| Financials / GBV KPI | PASS (USD truthful) | IN_PROGRESS | KPI must be Rs. from PKR snapshots only; legacy USD excluded with count |
| Markups | PARTIAL | IN_PROGRESS | Business Apply-to builder; MarkupRule engine unchanged |
| Settings | FAIL | IN_PROGRESS | Organization profile is source of truth for support/timezone Current Values |
| Notifications | READ_ONLY | IN_PROGRESS | AgencyNotificationSetting write path; QA vs operational failure split |
| Suppliers | ANALYTICS | IN_PROGRESS | PKR booking value from same money pipeline; registry states |
| API Connections | PARTIAL | IN_PROGRESS | Manage + provider labels + registry; secrets never shown |
| CMS Pages | FAIL | IN_PROGRESS | Create/edit/preview/publish via cms_pages; no schema migration |
| Media Library | FAIL (CMS-LIVE-MODULE-UNAVAILABLE) | IN_PROGRESS | Agency media panel; no throw |
| Homepage | PARTIAL | IN_PROGRESS | Same Page Settings panel + public preview link |
| Banners / Notices | FAIL | REMOVED_FROM_NAV | No JetPakistan domain tables; not shown as operational failures |
| Users | FAIL (2450456559) | IN_PROGRESS | Missing DashboardRoleCatalog import on detail |
| Staff | OPEN | OPEN | Same users directory scope=staff |
| Roles & Permissions | READ_ONLY | HARD_STOP_CANDIDATE | Account-type catalog cannot persist custom role matrices without a roles schema. Staff permission assignment already exists. Do not fake Next persistence. |

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
