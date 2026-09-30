# Feature authority matrix — Next Dashboard recovery (architecture correction)

**Captured:** 2026-09-29  
**NEXT_RECOVERY_START_MAIN:** `3baf878d1874d2cba82bbd6ee8aff3b3fe3b2141`  
**RECOVERY_BRANCH:** `work/jetpk-next-dashboard-recovery-20260929`  
**CANONICAL_DASHBOARD_UI:** `NEXTJS`  
**CANONICAL_DASHBOARD_BACKEND:** `LARAVEL`

## Architecture lock

```text
Browser → Next Dashboard UI → Laravel JSON/API → policies/services → DB
```

Batch A `target=laravel` Blade bridges are **NOT recovered**. Classification `BLADE_BRIDGE` ≠ PASS.

## Classification legend

`NEXT_PRESENT_WORKING` · `NEXT_PRESENT_READ_ONLY` · `NEXT_PRESENT_BROKEN` · `NEXT_LOST` · `NEXT_HISTORICAL_NOT_PORTED` · `BLADE_ONLY_LEGACY` · `BACKEND_PRESENT_NO_NEXT_UI` · `OBSOLETE`

| Feature | Portal | Canonical Next route | Current Next | Historical Next | Historical SHA | Laravel API | Mutation? | Current nav | Expected nav | Status | Recovery | QA E2E | Prod |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| API Connections | Admin | `/admin/dashboard/api-connections` | hub + workspace | phase hub | `47c6bbd3` | Admin `?format=json` | YES | Next | Next | NEXT_PRESENT_WORKING | P0 done | PENDING | PENDING |
| Suppliers read | Admin | `/admin/dashboard/suppliers` | read-only | same | main | api-dashboard GET | no | Next | Next | NEXT_PRESENT_READ_ONLY | keep | PENDING | — |
| Company Profile | Admin | `/settings/general` | OrganizationProfileForm live | org profile form | `bf5e9cdb` | Branding Admin JSON | YES | Next | Next | NEXT_PRESENT_WORKING | P1 done | PENDING | PENDING |
| Settings | Admin | `/settings/*` | general live org + local preview | partial live | phase | Admin JSON | partial | mixed | Next | NEXT_PRESENT_READ_ONLY | continue | PENDING | — |
| Homepage CMS | Admin | `/cms/sections` | HomepageSettingsPanel | homepage panel | `0ebb2278` | page-settings JSON | YES | Next | Next | NEXT_PRESENT_WORKING | homepage done | PENDING | PENDING |
| CMS Pages | Admin | `/cms/pages` | read shell | write panels | phase | CmsPage Admin JSON | YES | Blade Batch A | Next | NEXT_PRESENT_READ_ONLY | P2 continue | PENDING | — |
| SEO | Admin | TBD Next SEO | none | none dedicated | Blade SEO | admin-seo | YES | Blade Batch A | Next | BLADE_ONLY_LEGACY | later | PENDING | — |
| Notifications | Admin | `/settings/notifications` + failures | preview | live + failures | phase | notification-events JSON | YES | Blade Batch A | Next | NEXT_PRESENT_READ_ONLY | P3 | PENDING | — |
| Customer Queries | Admin | support/queries Next | mocked support | live support | phase | CustomerQuery + support tickets | YES | Blade Batch A | Next | BACKEND_PRESENT_NO_NEXT_UI | P6 | PENDING | — |
| Bookings list | Admin | `/bookings` | present | present | main | api-dashboard GET + ops | partial | Next | Next | NEXT_PRESENT_WORKING | verify | PENDING | — |
| Booking detail | Admin | `/bookings/[id]` | missing | present | phase | Admin notes/contact | YES | — | Next | NEXT_LOST | P5 | PENDING | — |
| Users writes | Admin | `/users` | activate/suspend only | create/invite | phase | Admin users | YES | Next | Next | NEXT_PRESENT_READ_ONLY | P4 | PENDING | — |
| Roles RBAC write | Admin | `/users/roles` | read | rbac-write-api | phase | api-dashboard POST | YES | Next | Next | NEXT_PRESENT_READ_ONLY | P7 | PENDING | — |
| Staff page | Admin | `/staff` | missing | present | phase | users scope | — | Blade Batch A | Next | NEXT_LOST | with P4 | PENDING | — |
| Profile | Admin | `/profile` | missing | present | phase | /profile JSON | YES | — | Next | NEXT_LOST | later | PENDING | — |
| Go-live | Admin | `/system/go-live` or Blade redirect→Next | missing | present | phase | Blade checklist | no | Blade Batch A | Next or truthful | NEXT_LOST | later | PENDING | — |
| Customer portal | Customer | `/customer/*` | frontend Next | same | `fc05518e` | customer JSON | YES | — | Next | NEXT_PRESENT_WORKING | E2E | PENDING | — |
| Agent portal | Agent | `/agent/*` | frontend Next | same | `d548c676` | agent JSON | YES | — | Next | NEXT_PRESENT_WORKING | E2E | PENDING | — |
| Staff portal | Staff | `/staff/dashboard/*` | Next read ops | same | main | api-dashboard | partial | Next | Next | NEXT_PRESENT_READ_ONLY | E2E | PENDING | — |

## Batch A reclassification

All Batch A `target=laravel` entries for CMS Pages, SEO, Customer Queries, OTP, AI, Settings Hub, Staff, Communications, Markups, Group Ticketing, Go-live remain **NOT RECOVERED** until Next modules land.

**Recovered on this branch (Next nav + JSON):** API Connections, Company Profile, Homepage CMS (`/cms/sections`).

**PR:** https://github.com/haseebpytdev/jetpk/pull/57  
**HEAD:** `1ded0322` (plus pending matrix doc)  
**FINAL_STATUS:** `PARTIAL`
