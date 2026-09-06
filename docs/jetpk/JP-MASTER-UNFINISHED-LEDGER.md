# JP-MASTER-UNFINISHED LEDGER

Phase: `JP-MASTER-UNFINISHED-CLOSURE-10`  
Branch: `phase/jp-master-unfinished-closure-10`  
Opened because 09B remaining gates were incomplete.  
`MASTER_FINAL_STATUS` must not be PASS while any `OPEN` / `MISSED_OPEN` / `STALE_REQUIRES_REVERIFY` row remains.

Allowed statuses only: `VERIFIED_DONE` | `OPEN` | `MISSED_OPEN` | `STALE_REQUIRES_REVERIFY` | `BLOCKED_SAFETY` | `OWNER_HOLD` | `INTENTIONAL_DEFER`

## Active master classification (this phase)

| Gate | Status |
|---|---|
| JP09B_TRAVELER_TIMING | PROVISIONAL_PASS (historical 09B N30; not re-certified after 5s authority change) |
| OFFER_FRESHNESS_SAFETY | OPEN until production N30 + authority proof after deploy |
| FRESH_EXTERNAL_DECOMPOSITION | PARTIAL (09B field was a range, not exclusive-interval P95) |
| CMS_MEDIA_LIFECYCLE | PASS_REQUIRES_REGRESSION_CARRY_FORWARD |
| CMS_FRONTEND_REGRESSION | PARTIAL until homepage browser run against current production |
| ASK_JETPAKISTAN_PRODUCTION | STALE_REQUIRES_REVERIFY |
| ADMIN_COMPANY_PROFILE | OPEN_MISSED until production workflow proof |
| HISTORICAL_UNFINISHED_RECONCILIATION | OPEN |

## Historical Owner-UAT management items

| Item | Classification | Historical source | Current source | Current production path | Proof notes |
|---|---|---|---|---|---|
| ADMIN FINANCIAL PKR | STALE_REQUIRES_REVERIFY | finance-reports phases | `app/Support/Finance`, dashboard finance | Admin finance routes | Do not mutate live money; read-only reverify remaining |
| MARKUP BUSINESS RULE BUILDER | STALE_REQUIRES_REVERIFY | markup sprints | markup services + admin UI | Admin markup | Commercial mutation prohibited during this phase |
| SETTINGS SOURCE OF TRUTH | STALE_REQUIRES_REVERIFY | settings hub | `AdminSettingsHubController`, dashboard settings | `/admin/settings` | |
| NOTIFICATION MANAGEMENT | STALE_REQUIRES_REVERIFY | notification phases | notification services + admin | Admin notifications | |
| FAILED NOTIFICATIONS CLASSIFICATION | STALE_REQUIRES_REVERIFY | Owner UAT: 84 failed | failed-notification classifiers | Admin failed notifications | Must distinguish QA noise vs live incidents; no email blast |
| SUPPLIER REGISTRY TRUTH | STALE_REQUIRES_REVERIFY | supplier registry phases | `SupplierConnection` + admin registry | Admin suppliers | Read-only |
| SUPPLIER BUSINESS MANAGEMENT | STALE_REQUIRES_REVERIFY | supplier business UI | admin supplier screens | Admin suppliers | |
| API CONNECTION FULL MANAGEMENT | STALE_REQUIRES_REVERIFY | connection CRUD | `SupplierConnection` controllers | Admin connections | No live credential mutation in this phase |
| CMS FULL MANAGEMENT | STALE_REQUIRES_REVERIFY | CMS page builder phases | CMS pages/blocks + homepage | Admin CMS | 09B proved homepage media lifecycle only |
| CMS PREVIEW/PUBLISH | STALE_REQUIRES_REVERIFY | homepage draft/publish tests | homepage publish pipeline | Admin homepage CMS | |
| MEDIA LIBRARY | STALE_REQUIRES_REVERIFY | JP-MASTER-CLOSURE-09B | media upload/replace/destroy | Admin media | 09B QA asset destroyed; no leftover commercial mutation |
| USERS MANAGEMENT | STALE_REQUIRES_REVERIFY | users admin | users controllers + dashboard | Admin users | |
| STAFF MANAGEMENT | STALE_REQUIRES_REVERIFY | staff admin | staff controllers | Admin staff | |
| RBAC ROLE/PERMISSION MANAGEMENT | STALE_REQUIRES_REVERIFY | RBAC phases | `StaffPermission`, roles | Admin RBAC | |
| CROSS-PORTAL RBAC | STALE_REQUIRES_REVERIFY | portal gates | portal middleware | Admin/Agent/Customer | |
| AGENCY ISOLATION | STALE_REQUIRES_REVERIFY | agency scoping | agency guards | All portals | |
| PASSWORD RESET PUBLIC URL | STALE_REQUIRES_REVERIFY | auth phases | public reset routes | `/forgot-password` | |
| DEPOSIT/PAYMENT/COMMISSION MANAGEMENT | STALE_REQUIRES_REVERIFY | finance/agent wallet | payment + commission services | Admin finance | No live payment |
| DASHBOARD OPERATIONAL ALERTS | STALE_REQUIRES_REVERIFY | ops alerts | dashboard alerts | Dashboard | |
| ADMIN COMPANY PROFILE / BRANDING | MISSED_OPEN | dashboard `OrganizationProfileForm` | `dashboard/features/settings/components/organization-profile-form.tsx` | Admin organization profile | Workflow not in 09B closure |
| ASK JETPAKISTAN PUBLIC FAB | STALE_REQUIRES_REVERIFY | JP-AI-ASSIST-02B `docs/evidence/jp-ai-assist-02b/public-activation.md` | `AskJetPakistanChat`, `PublicShell`, `AiAssistantEligibility` | `https://jetpakistan.pk` + `/api/public/config` | Historical PASS assumed `OTA_AI_ASSISTANT_MODE=public`; Owner reports FAB not visible |
| SELECTED OFFER AUTHORITY 5S | OPEN | this phase | `SelectedOfferAuthority`, `SabreOfferFreshness::revalidationValiditySeconds` | Traveler GET / Book Now | Search TTL remains 1800s |

## CMS vs 09B homepage media

09B certified homepage media lifecycle + ISR contract. Generic Pages/Builder (create/edit/reorder/hide/SEO/draft preview/publish) is **not** automatically `VERIFIED_DONE` from 09B media proof.

## Email

`EMAIL_ENGINEERING=CLOSED_PENDING_CLIENT_UAT`  
Do not convert to `VERIFIED_DONE` without Owner acceptance. No new Gmail matrix in this phase unless a new email defect is in scope.

## Final-closing deferred (must remain visible)

| Item | Status |
|---|---|
| SCREENSHOT_PROTECTION | INTENTIONAL_DEFER |
| MOFA | OWNER_HOLD |
| CHATWOOT | OWNER_HOLD |
| HISTORICAL_GIT_PURGE | OWNER_HOLD |
| CUSTOMER_TICKET_ARTIFACT | BLOCKED_SAFETY (`BLOCKED_NO_SAFE_LIVE_DOCUMENT`) |
| Sabre cancellation gates | INTENTIONAL_DEFER (intentionally preserved) |

## Repository / tech debt

| Item | Status |
|---|---|
| BookingController decomposition | INTENTIONAL_DEFER / P2_OPEN |
| FlightController decomposition | INTENTIONAL_DEFER / P2_OPEN |
| Playwright config consolidation | INTENTIONAL_DEFER / P2_OPEN |
| AGENTS / `.cursor/rules` reconciliation | STALE_REQUIRES_REVERIFY |
| summary/context debt | P2_OPEN |
| duplicate responsibilities / dead-code proof | P2_OPEN |

Do not mix major refactor into P0 functional fixes.

## Count snapshot (update when rows change)

These counts are for **ledger rows in this file**, not test counts.

- HISTORICAL_ITEMS_TOTAL=32 (management table 22 + CMS note folded into CMS rows + email + deferred 6 + debt 6; exact row tally in tables above)
- VERIFIED_DONE_COUNT=0 (no row promoted without current production proof in this pass)
- OPEN_COUNT=1 (5s offer authority pending production cert)
- MISSED_OPEN_COUNT=1 (company profile)
- STALE_REQUIRES_REVERIFY_COUNT=20+
- BLOCKED_SAFETY_COUNT=1
- OWNER_HOLD_COUNT=3
- INTENTIONAL_DEFER_COUNT=4+
