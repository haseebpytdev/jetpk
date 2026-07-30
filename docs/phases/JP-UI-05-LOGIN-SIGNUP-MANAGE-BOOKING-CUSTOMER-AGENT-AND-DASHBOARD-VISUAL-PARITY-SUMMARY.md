# JP-UI-05 — Login, Signup, Manage Booking, Customer, Agent, and Dashboard Visual Parity

## Phase metadata

| Field | Value |
|-------|-------|
| Phase | JP-UI-05-LOGIN-SIGNUP-MANAGE-BOOKING-CUSTOMER-AGENT-AND-DASHBOARD-VISUAL-PARITY |
| Branch | `phase/jetpk-ui-05-auth-portals-dashboard-visual-parity` |
| Baseline | `6d27f9d` (JP-UI-04A main HEAD) |
| Objective | Achieve mockup-aligned visual parity for auth, booking lookup, customer/agent portals, and admin dashboard shell using shared primitives and a 132-scenario visual matrix |

## Included scope

- **Auth family** — split-screen `AuthPageShell` with `AuthIllustrationPanel`, `AuthFormCard`, benefits panels, and shared form chrome across login, register, agent register, forgot password, OTP, and reset password
- **Session handling** — `LoginSessionNotice` for `?reason=session-expired`; social/OAuth buttons hidden unless Laravel providers are configured
- **Manage booking** — `BookingLookupPage` hero band, lookup card, trust chips; Cloudflare Turnstile preserved; no fake post-lookup actions
- **Portal shells** — shared `PortalShell` primitives in `frontend/features/portal/`; `CustomerDashboardShell` and `AgentDashboardShell` refactored to consume them
- **Dashboard app** — theme bootstrap script, token alignment in `dashboard-shell`, light/dark/system support
- **Visual audit harness** — 132 scenarios (`jp-ui-05-scenarios.ts`); `npm run audit:visual:jp-ui-05` orchestrates frontend (112) + dashboard (20) captures
- **Visual contract documentation** — auth, signup, lookup, customer portal, agent portal, admin shell, complete matrix, acceptance report

## Excluded scope

- Laravel backend changes (none)
- OTP business logic changes (flow unchanged; visual shell parity only)
- OAuth provider enablement (visibility remains Laravel-authoritative)
- Unsupported post-lookup actions (change flight, add baggage, live status)
- Full dashboard feature parity beyond shell/RBAC visual states (JP-OPS)
- Production deployment
- Backup Safe mockup modifications

## Investigation findings

| Area | Finding |
|------|---------|
| Auth layout | Login/register used single-column card; mockups #6/#7 expect split-screen with illustration and benefits |
| Social login | Mockup shows OAuth row; production must hide providers when Laravel does not configure them |
| Session expiry | No visible notice on login when session expired; query param `?reason=session-expired` existed but was not surfaced |
| Manage booking | Mockup #9 expects hero band + lookup card; prior page used simpler header without trust/security framing |
| Turnstile | Lookup Turnstile was authoritative from JP-FE-10; must remain intact through visual refactor |
| Portal shells | Customer and agent dashboards duplicated sidebar/topbar/mobile-drawer patterns independently |
| Agent RBAC | Wallet/ledger/deposit routes require owner role; agent_staff permitted routes differ — needed visual error states |
| Admin dashboard | Separate Next.js app lacked theme bootstrap; shell tokens diverged from frontend `jp-*` scale |
| Visual evidence | JP-UI-01 captured login/register/lookup as light-desktop only; no theme matrix or portal states |

## Root causes

1. **No shared auth shell** — each auth route composed layout independently without illustration/benefits contract.
2. **Portal chrome duplication** — customer and agent shells reimplemented navigation, drawer, and content grid separately.
3. **Dashboard theme gap** — admin app had no `data-theme` bootstrap; risk of flash and token mismatch on hydration.
4. **Lookup page under-designed** — functional form without mockup-aligned hero band and trust framing.
5. **No visual matrix for auth/portals** — prior audits did not cover recovery states, RBAC denials, or dashboard app.

## Exact files changed

### New — `frontend/features/auth/`

| File | Purpose |
|------|---------|
| `components/AuthPageShell.tsx` | Split-screen grid shell (`data-testid="auth-page-shell"`) |
| `components/AuthIllustrationPanel.tsx` | Left illustration + headline + benefits |
| `components/AuthFormPanel.tsx` | Right form column wrapper |
| `components/AuthFormCard.tsx` | Card chrome for forms (`data-testid="auth-form-card"`) |
| `components/AuthBenefits.tsx` | Benefit list with icons |
| `components/AuthBrandHeader.tsx` | Eyebrow/headline pattern |
| `components/AuthFooterLinks.tsx` | Cross-links (login ↔ register ↔ forgot) |
| `components/AuthStatusAlert.tsx` | Inline alert/status pattern |
| `components/AuthErrorState.tsx` | Recoverable error state |
| `components/AuthLoadingState.tsx` | Loading skeleton |
| `components/LoginSessionNotice.tsx` | Session-expired banner (`reason=session-expired`) |
| `components/LoginForm.tsx` | Login form with password visibility toggle |
| `config/auth-benefits.ts` | `LOGIN_BENEFITS`, `SIGNUP_BENEFITS`, `AGENT_SIGNUP_BENEFITS` |
| `index.ts` | Public exports |
| `server/session-fixture.ts` | Deterministic session fixtures for visual audit |

### New — `frontend/features/portal/`

| File | Purpose |
|------|---------|
| `shell/PortalShell.tsx` | `PortalShell`, `PortalSidebar`, `PortalTopbar`, `PortalContent`, `PortalMobileDrawer`, `buildPortalNav` |
| `index.ts` | Public exports |

### Updated — portal shells

| File | Change |
|------|--------|
| `features/customer-dashboard/shell/CustomerDashboardShell.tsx` | Refactored to `PortalShell` primitives |
| `features/agent-dashboard/shell/AgentDashboardShell.tsx` | Refactored to `PortalShell`; capabilities-driven nav |
| `features/agent-dashboard/wallet/WalletOverviewPage.tsx` | Wallet overview visual alignment |

### Updated — auth routes

| File | Change |
|------|--------|
| `app/(auth)/login/page.tsx` | `AuthPageShell` + session notice |
| `app/(auth)/register/page.tsx` | Customer signup split layout |
| `app/(auth)/agent/register/page.tsx` | Agent signup split layout |
| `app/(auth)/forgot-password/page.tsx` | Recovery split layout |
| `app/(auth)/login/otp/page.tsx` | OTP within shared auth chrome (logic unchanged) |

### Updated — manage booking

| File | Change |
|------|--------|
| `features/standard-booking/lookup/BookingLookupPage.tsx` | Hero band, lookup card, trust chips; Turnstile preserved |

### New — dashboard theme

| File | Purpose |
|------|---------|
| `dashboard/lib/theme/constants.ts` | `THEME_STORAGE_KEY` |
| `dashboard/lib/theme/theme-bootstrap-script.ts` | Inline bootstrap for `data-theme` |
| `dashboard/app/layout.tsx` | Bootstrap script injection |
| `dashboard/app/globals.css` | Token alignment |
| `dashboard/layouts/dashboard-shell.tsx` | Shell token alignment |

### Assets

| File | Purpose |
|------|---------|
| `frontend/public/images/auth/auth-illustration.svg` | Auth + lookup hero illustration slot |

### Visual audit harness

| File | Purpose |
|------|---------|
| `tests/visual-audit/jp-ui-05-scenarios.ts` | 132 scenario registry |
| `tests/visual-audit/jp-ui-05-fixtures.ts` | Deterministic API/session mocks |
| `tests/visual-audit/jp-ui-05-helpers.ts` | Theme, viewport, overflow helpers |
| `tests/visual-audit/jp-ui-05-visual-matrix.spec.ts` | Frontend Playwright spec (112 scenarios) |
| `tests/visual-audit/jp-ui-05-dashboard-visual-matrix.spec.ts` | Dashboard Playwright spec (20 scenarios) |
| `playwright.jp-ui-05-dashboard.config.ts` | Dashboard app Playwright config |
| `scripts/capture-jp-ui-05.mjs` | `npm run audit:visual:jp-ui-05` orchestrator |
| `scripts/verify-jp-ui-05-manifest.mjs` | Manifest count/duplicate verifier |
| `package.json` | `audit:visual:jp-ui-05` script |
| `tests/auth.spec.ts` | Auth regression tests |

### Documentation (this phase)

- `docs/phases/JP-UI-05-*-SUMMARY.md` (this file)
- `frontend/docs/visual/AUTH-LOGIN-OTP-RECOVERY-AND-SESSION-VISUAL-CONTRACT.md`
- `frontend/docs/visual/SIGNUP-ACCOUNT-TYPES-VALIDATION-AND-APPROVAL-VISUAL-CONTRACT.md`
- `frontend/docs/visual/MANAGE-BOOKING-TURNSTILE-LOOKUP-AND-ACTION-ELIGIBILITY-VISUAL-CONTRACT.md`
- `frontend/docs/visual/CUSTOMER-PORTAL-NAVIGATION-BOOKINGS-PROFILE-AND-SUPPORT-VISUAL-CONTRACT.md`
- `frontend/docs/visual/AGENT-AGENT-STAFF-WALLET-LEDGER-DEPOSITS-AND-RBAC-VISUAL-CONTRACT.md`
- `frontend/docs/visual/ADMIN-PLATFORM-STAFF-DASHBOARD-SHELL-RBAC-AND-STATE-VISUAL-CONTRACT.md`
- `frontend/docs/visual/JP-UI-05-COMPLETE-AUTH-PORTAL-DASHBOARD-VISUAL-MATRIX.md`
- `frontend/docs/visual/JP-UI-05-MOCKUP-COMPARISON-AND-ACCEPTANCE-REPORT.md`
- Updates to mismatch register, content audit, acceptance criteria, capture guide, roadmap

## Routes changed

No new or removed Next.js routes. Existing routes updated visually:

| Route | Application | Page / shell |
|-------|-------------|--------------|
| `/login` | frontend | Login + session notice |
| `/register` | frontend | Customer registration |
| `/agent/register` | frontend | Agent registration |
| `/forgot-password` | frontend | Password recovery |
| `/login/otp` | frontend | OTP challenge (logic unchanged) |
| `/reset-password/[token]` | frontend | Reset password form |
| `/lookup-booking` | frontend | `BookingLookupPage` |
| `/customer/*` | frontend | `CustomerDashboardShell` |
| `/agent/*` | frontend | `AgentDashboardShell` |
| `/admin/dashboard/*` | dashboard | `dashboard-shell` |
| `/staff/dashboard/*` | dashboard | `dashboard-shell` (RBAC-gated) |

## Database changes

None.

## Backend changes

None. Laravel remains authoritative for:

- OAuth provider configuration (social buttons visibility)
- OTP challenge/verify endpoints (unchanged)
- Booking lookup + Turnstile validation
- Customer/agent/admin API payloads and RBAC
- Session expiry redirects (`?reason=session-expired`)

## Frontend changes

### Auth architecture

- **`AuthPageShell`** — two-column grid: illustration panel (desktop left, mobile below form) + form panel.
- **`AuthIllustrationPanel`** — SVG illustration slot, headline, description, `AuthBenefits` list.
- **`AuthFormCard`** — consistent card chrome, title, description, footer links.
- **Social login** — rendered only when Laravel session/bootstrap exposes configured providers; visual audit asserts `oauth-google`, `oauth-apple`, `oauth-facebook`, `social-login-row` are **forbidden** when unconfigured.
- **Session expired** — `LoginSessionNotice` reads `?reason=session-expired` and shows informational alert above login form.

### Manage booking

- Full-width hero band with illustration and supporting copy.
- Lookup card with booking reference + email fields.
- Trust chips (secure lookup, privacy, support, fast access).
- Turnstile widget preserved (`data-testid="lookup-turnstile"`).
- Post-lookup: only Laravel-eligible actions shown; `lookup-change-flight`, `lookup-add-baggage`, `lookup-live-status`, `lookup-refund-action` forbidden when unsupported.

### Portal architecture

- **`PortalShell`** — topbar + sidebar + mobile drawer + content grid.
- **`buildPortalNav`** — active route highlighting, badge support, drawer close on navigate.
- Customer nav: overview, bookings, payments, invoices, profile, security, support, notifications.
- Agent nav: capabilities-driven from Laravel; wallet/ledger/deposits owner-only.

### Dashboard app

- Theme bootstrap script sets `data-theme` before paint (light/dark/system).
- Shell spacing, borders, and typography aligned to shared `jp-*` tokens.

## Tests executed

| Command | Result |
|---------|--------|
| `npm run typecheck` (frontend) | pass |
| `npm run lint` (frontend) | pass |
| `npm run build` (frontend) | pass (via audit harness) |
| `npm run typecheck` (dashboard) | pass |
| `npm run lint` (dashboard) | pass |
| `npm run build` (dashboard) | pass |
| `npm run audit:visual:jp-ui-05` | **pass** — expected=132 actual=132 passed=132 failed=0 skipped=0 screenshots=132 (962s) |
| `npx playwright test tests/auth.spec.ts tests/booking-lookup-turnstile.spec.ts` | pass (17/17) |
| Laravel tests | Not run — no backend changes |

## Assertion counts

| Suite | Assertions |
|-------|----------:|
| JP-UI-05 visual matrix | 132 scenarios (screenshot + gate checks per scenario) |
| Frontend scenarios | 112 |
| Dashboard scenarios | 20 |
| `forbiddenTestIds` gates | Social OAuth (login), unsupported account types (signup), fake lookup actions (manage), refund when login required |
| Scenario count invariant | `EXPECTED_SCENARIO_COUNT = 132` enforced at module load |

## Screenshots

| Artifact | Location |
|----------|----------|
| Frontend capture manifest | `frontend/.visual-audit/jp-ui-05/capture-manifest.json` (gitignored) |
| Dashboard capture manifest | `dashboard/.visual-audit/jp-ui-05/capture-manifest.json` (gitignored) |
| PNG captures | `frontend/.visual-audit/jp-ui-05/` and `dashboard/.visual-audit/jp-ui-05/` (gitignored) |
| Acceptance report | `frontend/docs/visual/JP-UI-05-MOCKUP-COMPARISON-AND-ACCEPTANCE-REPORT.md` |

132 scenarios: login (20), signup (20), recovery (12), manage (20), customer (20), agent (20), admin (20).

## Responsive verification

| Viewport | Verified families |
|----------|-------------------|
| 1440×900 desktop | Auth, lookup, customer, agent, admin |
| 1024×900 tablet | Auth layout family, manage booking |
| 390×844 mobile | Auth, OTP, customer, agent, admin |
| 320×700 narrow | Auth layout family, manage booking |
| 1024 @ 150% zoom | Auth, customer, agent, admin overview |

- Auth: form column first on mobile; illustration stacks below at `lg` breakpoint.
- Portals: sidebar hidden below `lg`; `PortalMobileDrawer` with escape/overlay close.
- Dashboard: mobile topbar + drawer pattern matches customer/agent portals.

## Accessibility verification

| Check | Status |
|-------|--------|
| Auth form labels associated with inputs | Pass |
| Password show/hide toggle accessible name | Pass |
| Session-expired notice uses `role="alert"` or status pattern | Pass |
| OTP inputs labeled; logic unchanged | Pass |
| Turnstile fallback states announced | Pass (existing) |
| Portal drawer `role="dialog"` + `aria-modal` | Pass |
| `:focus-visible` on auth buttons and nav links | Pass |
| Light and dark theme contrast on auth/portals | Pass (token-based) |
| `prefers-reduced-motion` on auth illustration | Pass |
| No fake unsupported actions exposed to assistive tech | Pass (`forbiddenTestIds`) |

## Known limitations

- Auth illustration uses shared SVG asset; production photograph slot deferred to JP-UI-06.
- Agent approval pending state relies on Laravel messaging; no invented approval timeline copy.
- Admin booking detail may render list/stub depending on route depth; full ops detail deferred to JP-OPS.
- Dashboard feature pages beyond shell chrome are not fully redesigned in this phase.
- Visual audit scores: **132/132 passed** — see `frontend/docs/visual/jp-ui-05-capture-result.json`

## Risks

| Risk | Mitigation |
|------|------------|
| Turnstile regression during lookup redesign | Dedicated turnstile scenarios + existing `booking-lookup-turnstile.spec.ts` |
| OAuth buttons shown when unconfigured | `forbiddenTestIds` gate in visual matrix |
| Dashboard theme flash | Inline bootstrap script before React hydration |
| Agent RBAC leak on wallet routes | `agent-staff-owner-route-forbidden` scenario + Laravel 403 |

## Rollback instructions

1. Checkout baseline: `git checkout 6d27f9d -- frontend/features/auth frontend/features/portal frontend/features/customer-dashboard/shell frontend/features/agent-dashboard/shell frontend/features/standard-booking/lookup frontend/app/\(auth\) dashboard/app dashboard/layouts dashboard/lib/theme frontend/tests/visual-audit/jp-ui-05* frontend/scripts/capture-jp-ui-05.mjs frontend/scripts/verify-jp-ui-05-manifest.mjs`
2. Remove new docs under `docs/phases/JP-UI-05-*` and `frontend/docs/visual/*JP-UI-05*` if reverting documentation.
3. Rebuild frontend and dashboard: `npm run build` in each app directory.
4. Verify auth routes and `/lookup-booking` render with prior layout.

## Commit SHA

| Commit | SHA | Message |
|--------|-----|---------|
| Implementation | `ba3ae9e` | feat(frontend): complete JP-UI-05 auth and portal visual parity |
| Tests | `09904d0` | test(frontend): add JP-UI-05 auth portal dashboard matrix |
| Documentation | `8c19551` | docs(visual): record JP-UI-05 visual closure |
| Merge | TBD | merge: complete JP-UI-05 auth portal dashboard visual parity |

## Final status

**PASS** — 132/132 visual scenarios; auth, lookup, portal, and dashboard shell parity delivered. Ready for JP-UI-06.
