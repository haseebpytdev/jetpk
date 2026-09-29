# Feature authority matrix — JetPakistan dashboard full recovery

**Captured:** 2026-09-29  
**START_REMOTE_MAIN:** `3de07cdb9cbb5236e8e4f37e8c2487bacb4cb492` (`jetpk/main`)  
**RECOVERY_BRANCH:** `work/jetpk-dashboard-full-recovery-20260929`  
**ROOT CAUSE SUMMARY:** Owner-uat / owner-retest-v3 Admin **write** surfaces (API Connections Next hub, branding/notification JSON, RBAC write, CMS builder writes) live on phase branches that are **NOT ancestors of main**. Current main Next Admin/Staff shell is intentionally **read-only**. Blade mutation surfaces still exist under `/admin/*` but live Next nav does not bridge to them. Production dashboard BUILD_ID is also stale (Sep 22) vs Laravel SHA Sep 29.

**Classification key:** A source · B route · C renderer · D stale build · E API · F RBAC · G data · H navigation · I asset · J schema

| Feature | Portal | Expected route | Current route | Current renderer | Source exists? | API exists? | Persistence exists? | Historical authority SHA | Historical files | Current status | Root cause | Recovery action | Regression risk |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Admin Overview | Admin | `/admin/dashboard` | `/admin/dashboard` | Next | yes | GET `/api/dashboard/overview` | yes | `82ed9d61`, `27a1ed9f` | `dashboard/app/[portal]/dashboard/page.tsx` | Present (read) | D (stale build) | Rebuild/redeploy dashboard | Low |
| Booking Management | Admin | `/admin/dashboard/bookings` + Blade `/admin/bookings` | Next list + Blade detail | Next+Blade | yes | GET dashboard bookings; Blade mutations | yes | `d8c5f4fa` | `dashboard/features/bookings/**`, `BookingManagementController` | Partial (Next read; Blade mutate) | H | Nav bridge to Blade detail/ops; keep Next list | Med |
| Notifications / Communications | Admin | Blade `/admin/settings/communications*` | Missing from Next nav | Blade | yes | Blade | yes | owner-uat phase | `AgencyNotificationSettingController`, communications blades | Orphaned from Next shell | H | Nav → Settings hub + Communications | Low |
| Users / RBAC | Admin | `/admin/dashboard/users` | Same | Next read | yes | GET users/roles/permissions | yes | `a6f732fe`, phase `6d019160` (writes NOT on main) | Next users + Blade `users/*` | Partial | H + source loss of Next writes | Bridge Blade users; Batch C optional write port | Med |
| Customers | Admin | `/admin/dashboard/customers` + Blade | Same | Next+Blade | yes | GET + Blade | yes | `ee15d09d` | customers features + controller | Present | D | Redeploy | Low |
| Agents | Admin | `/admin/dashboard/agents` + Blade | Same | Next+Blade | yes | GET + Blade | yes | `82ed9d61` | agents pages | Present | D | Redeploy | Low |
| Staff management | Admin | Blade `/admin/staff` | Not in Next nav | Blade | yes | Blade | yes | `82ed9d61` | `admin/staff.blade.php` | Orphaned | H | Nav bridge `/admin/staff` | Low |
| Customer Queries | Admin | `/admin/customer-queries` | Route OK; not in Next nav | Blade | yes | Blade only | yes | `f4bab4ea` | `CustomerQueryController`, views | Orphaned | H | Nav bridge | Low |
| Settings hub | Admin | `/admin/settings` | Next `/admin/dashboard/settings` (read-only) | Wrong primary | yes | Blade hub | yes | AdminSettingsHub | `AdminSettingsHubController` | Misrouted | H/C | Point Admin Settings nav to Blade hub | Med |
| Company Profile / Branding | Admin | `/admin/settings/branding` | Not in Next nav | Blade | yes | Blade PATCH | yes | `f919e419`, `08cb61c4` | AgencyBranding* | Orphaned | H | Nav bridge | **High** (public brand) |
| Logo / Favicon authority | Admin→Public | Branding → public-config | Chain present | Laravel+Next | yes | `/api/public/content/config` | yes | `9261c79e`, `b9d8dccc` | branding resolvers | Present if branding reachable | H | Bridge branding; do not overwrite prod logo | **High** |
| CMS pages | Admin | `/admin/cms-pages` | Next `/cms` read stubs | Next read + Blade write | yes | Blade CRUD; Next GET cms | yes | `dd325c30`, `dbbd3dfd` | CmsPageController | Partial | H | Nav → Blade cms-pages | **High** |
| Homepage CMS | Admin | `/admin/settings/homepage` | Not in Next nav | Blade | yes | Blade | yes | `dbbd3dfd` | AgencyHomepageController | Orphaned | H | Nav bridge; golden protect | **Critical** |
| Managed pages / Page settings | Admin | `/admin/page-settings` | Not in Next nav | Blade | yes | Blade | yes | page-settings routes | ClientPageSettings* | Orphaned | H | Nav bridge | **High** |
| SEO / AEO / GEO | Admin | `/admin/seo/*` | Not in Next nav | Blade | yes | Blade | yes | `bff174fa`, `f1de1b26` | SeoManagement* | Orphaned | H | Nav bridge | **High** |
| OTP / Security | Admin | `/admin/settings/login-otp` | Not in Next nav | Blade | yes | Blade | yes | settings routes | LoginOtpSettingsController | Orphaned | H | Nav via hub + direct | Med |
| AI settings | Admin | `/admin/settings/ai-assistant` | Not in Next nav | Blade | yes | Blade | yes | AiAssistantStatusController | Blade settings | Orphaned | H | Nav bridge; **do not change AI app logic** | Med |
| Supplier/API Settings | Admin | `/admin/api-settings` | Next `/suppliers` read-only | Blade write exists | yes | Blade CRUD + test | yes | `ee15d09d`, phase `47c6bbd3` (Next hub NOT on main) | SupplierConnection* | Orphaned / incomplete Next | H + A (Next hub) | Batch A Blade bridge; Batch C optional Next hub port | **High** |
| Supplier Connections | Admin | same as API settings | same | Blade | yes | yes | yes | SupplierConnection model | same | Orphaned | H | Bridge | **High** |
| Groups admin | Admin | `/admin/group-ticketing` | Not in Next nav | Blade | yes | Blade | yes | group controllers | blades | Orphaned | H | Nav via Settings hub | Med |
| Admin Profile | Admin | historical Next `/profile` | Missing on main | — | phase only | phase | yes | phase profile page | not on main | Missing Next page | A | Batch C or Blade profile if exists | Low |
| Go-live | Admin | Next `/system/go-live` (phase) | Blade `/admin/go-live-checklist` | Blade | Blade yes; Next no | Blade | config | phase go-live page | Blade checklist | Blade OK; Next absent | A intentional | Bridge Blade; do not fake `systemGoLive` API | Low |
| Customer Dashboard | Customer | `/customer/dashboard` | Same | Public Next | yes | Customer portal JSON | yes | `fc05518e` | `frontend/app/customer/**` | Present on main | D? smoke | Smoke only Batch D | Low |
| Agent Dashboard | Agent | `/agent/dashboard` | Same | Public Next | yes | Agent portal JSON | yes | `d548c676` | `frontend/app/agent/**` | Present on main | D? smoke | Smoke only | Low |
| Staff Dashboard | Staff | `/staff/dashboard` | Same | Next | yes | GET session | yes | `82ed9d61` | portal tree | Present read-only ops | D | Redeploy; no admin-only Blade bridges | Low |
| RBAC isolation | All | server policies | policies present | Laravel | yes | yes | yes | `a6f732fe` | policies | Needs live matrix | F verify | Test ladder | Med |
| Public Homepage | Public | `/` | `/` | Public Next | yes | public content API | yes | golden recovery | frontend public | **PROTECTED** | — | No redesign; golden=0 | Critical protect |
| Ask JetPakistan | Public | FAB/widget | Present | Public+AI | yes | AI APIs (current main) | yes | CQ series on main | AI runtime | **Do not downgrade** | — | Smoke only | Med |

## Batch plan (approved)

| Batch | Action |
|---|---|
| **A** | Nav bridges: extend `BackOfficeCapabilitiesPresenter` + sidebar `target=laravel` for Blade hubs (Settings, API Settings, Branding, Homepage, CMS, SEO, Customer Queries, OTP, AI, Staff, Markups, Group ticketing, Communications). Rebuild/redeploy dashboard. |
| **B** | Leave mutation UX on Blade; no owner-uat wholesale port. |
| **C** | Optional later: port Next API Connections hub / write UIs file-by-file if Blade UX remains owner-blocking. |
| **D** | Customer/Agent smoke only unless matrix proves missing pages. |

## Intentionally not restored (yet)

| Feature | Reason |
|---|---|
| Full owner-uat Next write tree merge | superseded / not on main; cherry-pick risk to public |
| Fake `DASHBOARD_API_ROUTES.systemGoLive` | would be fake constant; Blade go-live is authority |
| Demo/fixture-as-production CMS | security / data integrity |
| Phase-only scripts that rotate QA passwords in prod | security risk |
