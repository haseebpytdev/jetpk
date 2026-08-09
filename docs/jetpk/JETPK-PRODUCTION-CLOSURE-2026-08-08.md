# JetPakistan Production Closure — 2026-08-08

**Result:** `FULL_OTA_OPERATIONAL_ACCEPTANCE=PASS_WITH_CONTROLLED_SUPPLIER_AND_COMMERCIAL_BOUNDARIES`

Permanent engineering record for the JetPakistan Next.js public portal production migration and operational acceptance closure. This document contains only sanitized operational information. No passwords, OTP values, cookies, XSRF tokens, API keys, supplier credentials, reset tokens, or raw diagnostic logs are stored here.

---

## A. Architecture and route ownership

| Layer | Process / owner | Production bind |
|-------|-----------------|-----------------|
| Public Next.js | PM2 `jetpk-public-frontend` | `127.0.0.1:3010` |
| Dashboard Next.js | PM2 `jetpk-dashboard` | `127.0.0.1:3001` |
| Dashboard static assets | Isolated namespace `/dashboard-next/_next/*` | RELEASE-02A |
| Laravel application | OpenLiteSpeed + PHP 8.3 | Public vhost + private listener `127.0.0.1:8088` (SSR bootstrap) |
| Browser → Laravel | Same-origin `/laravel` bridge via OLS proxy contexts | Public site only; no cross-origin browser API |
| Queue / scheduler | Laravel workers on application host | Verified healthy at closure |
| Database | MariaDB on application host | Verified healthy at closure |

**Route ownership principles**

- Public marketing, auth shell, and booking UX: Public Next.js.
- Authenticated customer/agent/staff/admin portals: Dashboard Next.js with Laravel session/API behind `/laravel`.
- Supplier integrations, CMS persistence, mail, and business rules: Laravel only.
- SSR session bootstrap reads private Laravel (`:8088`) server-side; browser never calls `:8088` directly.

---

## B. Completed migration scope

| Phase | Status |
|-------|--------|
| RELEASE-02A dashboard asset namespace | **DEPLOYED + VERIFIED** |
| Wave-1 Public Next cutover | **DEPLOYED + VERIFIED** |
| Wave-2 auth / Customer / Agent cutover | **DEPLOYED + VERIFIED** |
| Same-origin `/laravel` bridge | **DEPLOYED + VERIFIED** |
| SSR session correction | **DEPLOYED + VERIFIED** |
| CSRF / XSRF end-to-end | **DEPLOYED + VERIFIED** |
| POST logout + session invalidation | **DEPLOYED + VERIFIED** |
| Homepage CMS / media binding | **DEPLOYED + VERIFIED** |
| Database branding / logo binding | **DEPLOYED + VERIFIED** |
| Role-aware profile dropdown (desktop + compact) | **DEPLOYED + VERIFIED** |
| Global typography consistency | **DEPLOYED + VERIFIED** |
| Browser-origin OLS mutation fix | **DEPLOYED + VERIFIED** (human Platform Admin login confirmed post-fix) |

---

## C. Authentication closure

| Gate | Result |
|------|--------|
| Customer browser auth E2E | **PASS** (login → OTP → portal → logout) |
| Agent browser auth E2E | **PASS** (login → OTP → portal → logout) |
| Platform Admin browser login | **PASS** (login → OTP → admin dashboard; human confirmed) |
| Login browser transport (`Origin` + `/laravel`) | **PASS** |
| Password reset browser transport | **PASS** |
| OTP flow | **PASS** |
| Logout / session invalidation | **PASS** |

### Password reset evidence (nuanced)

| Field | Result |
|-------|--------|
| `PASSWORD_RESET_APPLICATION_FLOW` | **PASS** (reset POST 200, privacy-safe response, reset DB record created) |
| `PASSWORD_RESET_MAIL_PATH` | **EXECUTED_WITHOUT_EXCEPTION** |
| `PASSWORD_RESET_MAILBOX_DELIVERY` | **NOT_INDEPENDENTLY_CONFIRMED** (mailbox / reset-link receipt not verified; no explicit SMTP acceptance line retained) |

This nuance does **not** block source closure.

---

## D. OTA operations

| Area | Result |
|------|--------|
| Sabre live flight search | **PASS** |
| Safe booking boundary (validation / pre-PNR) | **PASS** |
| Safe payment boundary (validation only) | **PASS** |
| Groups safe boundary (revalidation / pre-book) | **PASS** |
| Support validation boundary | **PASS** |
| Browser-origin mutation gate | **PASS** (`Origin` present → request reaches Laravel → app-level response) |
| Full production regression matrix | **PASS** (`failures=0`) |
| Core production regression | **PASS** |

---

## E. Supplier policy

| Supplier | Production state |
|----------|------------------|
| **Sabre** | Active; live search operational |
| **PIA NDC** | Enabled; air shopping healthy |
| **IATI** | `INTENTIONALLY_INACTIVE` — acceptance deferred by business policy |
| **Al Haider** | Lifecycle code `READY_FOR_CONTROLLED_INITIAL_ISSUANCE`; `ALHAIDER_TOKEN_PRESENT=no`; `ALHAIDER_TOKEN_GENERATION_ENABLED=no`; initial token issuance **deferred** to controlled supplier activation phase; durable encrypted token store + group sync/revalidation code verified |
| **One API** | Credentials available; production test **deferred** to dedicated future phase |

---

## F. CMS and public content

| Gate | Result |
|------|--------|
| Homepage hero media binding | **PASS** |
| Database brand logo binding | **PASS** |
| CMS section media capability | **PASS** |
| CMS media item ID stability | **PASS** |
| Route media production population | **PENDING_CONTENT_UPLOAD** (capability present; no production assets yet) |
| Destination media production population | **PENDING_CONTENT_UPLOAD** (capability present; no production assets yet) |
| CMS section completeness | **PASS_CAPABILITY_CONTENT_PENDING** |
| Featured deals | **DISABLED_BY_CMS_CONFIG** |

Content population is an editorial task via `/admin/page-settings/home`, not a code defect.

---

## G. Infrastructure and OLS baselines

| Config | SHA256 | Role |
|--------|--------|------|
| Global OLS (`httpd_config.conf`) | `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c` | **Current authoritative global baseline** |
| Vhost (`vhconf.conf`) | `8da510a8f911d8d711658abd8a110b04309d6295cf513f9f7dce4efdd794a42a` | **Current authoritative JetPakistan vhost baseline** |
| Vhost pre-fix (historical) | `b78cda3ff907a8065ed92cdaf59427c54bd11f34c5f0217963900e147f321012` | Retained for audit trail |

**OLS origin fix:** Replaced `RewriteRule [P]` proxy for `/laravel` with explicit vhost-level proxy contexts so same-origin browser `POST` requests with `Origin: https://jetpakistan.pk` reach Laravel instead of receiving an empty OLS 400.

**Backup path (OLS origin fix):** `/root/jetpk-ols-origin-fix-20260809T131541Z`

**Additional evidence-backed server backups (earlier cutovers):**

```
/root/jetpk-release-02a-backup-20260808T133824
/root/jetpk-dashboard-cutover-fixed-20260808T135451
/root/jetpk-public-next-wave1-20260808T141921
/root/jetpk-laravel-bridge-20260808T145253
/root/jetpk-wave2-cutover-20260808T150100
/root/jetpk-ssr-session-deploy-20260808T153240
```

---

## H. Validation summary

| Check | Result |
|-------|--------|
| Deployed source parity | **PASS** (39 intentional production files; local SHA256 == production) |
| PM2 `jetpk-public-frontend` | **online** |
| PM2 `jetpk-dashboard` | **online** |
| Private Laravel `:8088` | **healthy** |
| Public HTTPS 80/443 | **healthy** |
| Database | **healthy** |
| Queue / scheduler | **healthy** |
| Unexpected regression failures | **0** |

---

## I. Explicit future items

- Route and destination CMS image population (editorial, via admin page settings).
- Al Haider controlled initial token issuance (human-approved supplier activation).
- One API dedicated production testing phase.
- JP-CMS-02 (CMS Page Builder) — not started.
- Screenshot protection — deferred to final production-closing stage if still on roadmap.
- OTP demo fixed-code patch — intentionally retained for demo accounts.

---

## J. Git hygiene note

Temporary private closure tracking under `docs/private/jetpk-production-closure/` was removed before the clean intentional source commit. This document is the durable engineering closure record.

**Closure commit scope:** intentional production source (Laravel + Public Next), legitimate regression/unit tests, and this permanent summary only. No private probes, manifests, or temporary diagnostic artifacts are retained in repository history.

---

## Final acceptance ledger (sanitized)

```
FULL_OTA_OPERATIONAL_ACCEPTANCE=PASS_WITH_CONTROLLED_SUPPLIER_AND_COMMERCIAL_BOUNDARIES
PUBLIC_NEXT=PASS
DASHBOARD_NEXT=PASS
PRIVATE_LARAVEL=PASS
CUSTOMER_BROWSER_AUTH_E2E=PASS
AGENT_BROWSER_AUTH_E2E=PASS
PLATFORM_ADMIN_BROWSER_LOGIN=PASS
LOGIN_BROWSER_TRANSPORT=PASS
PASSWORD_RESET_BROWSER_TRANSPORT=PASS
BROWSER_ORIGIN_MUTATION_GATE=PASS
HOMEPAGE_HERO_MEDIA_BINDING=PASS
DATABASE_BRAND_LOGO_BINDING=PASS
CMS_SECTION_MEDIA_CAPABILITY=PASS
CMS_MEDIA_ITEM_ID_STABILITY=PASS
ROUTE_MEDIA_PRODUCTION_POPULATION=PENDING_CONTENT_UPLOAD
DESTINATION_MEDIA_PRODUCTION_POPULATION=PENDING_CONTENT_UPLOAD
FLIGHT_SEARCH_LIVE_BACKEND=PASS
ALHAIDER_TOKEN_PRESENT=no
ALHAIDER_TOKEN_GENERATION_ENABLED=no
ALHAIDER_INITIAL_ACTIVATION=DEFERRED_TO_CONTROLLED_SUPPLIER_ACTIVATION_PHASE
IATI_PRODUCTION_STATE=INTENTIONALLY_INACTIVE
ONE_API_PRODUCTION_TEST=DEFERRED
ALL_DEPLOYED_SOURCE_PARITY=PASS
CORE_PRODUCTION_REGRESSION=PASS
FINAL_GIT_HYGIENE=PASS
```
