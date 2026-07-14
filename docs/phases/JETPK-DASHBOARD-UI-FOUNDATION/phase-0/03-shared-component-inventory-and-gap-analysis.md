# Phase 0 · Document 03 — Shared Component Inventory & Gap Analysis

> **REVISION 1** — Inventory now serves **all** authenticated roles. Two gaps are
> added for the expanded scope: a **permission-denied / access-denied state**
> (required by Agent Staff and by the auth access-denied surface) and
> **auth-entry form primitives** (login/OTP/reset reuse `jp/*` fields). Admin/Staff
> are table- and form-heavy, so the responsive-table (T-1) and form-field (F-1)
> gaps are load-bearing for them.

**Phase:** JETPK-DASHBOARD-UI-FOUNDATION-ROUTE-PARITY-AND-RESPONSIVE-REDESIGN
**Stage:** Phase 0 (audit & architecture) · **Baseline:** `claude/ui-master` @ `6fbfae4`

> **Headline:** the shared UI layer largely **already exists** across three
> namespaces — `jp/*` (branded kit), `dashboard/*` (dashboard widgets), and
> `bookings/detail-*` (booking detail suite) — plus Breeze defaults. The primary
> Phase-0 finding is **duplication across namespaces**, so the redesign's job is
> to pick canonical primitives and dedupe, not build from zero.

---

## 1. Existing component namespaces (`resources/views/components/`)

### 1a. `jp/*` — JetPakistan branded design-system kit (the canonical UI kit)
`alert`, `bene-card`, `booking-timeline`, `brand-logo`, `button`, `card`,
`chip`, `dest-card`, `empty-state`, `fare-card`, `flight-arc`, `form-group`,
`google-sign-in`, `group-card`, `icon`, `input`, `modal`, `page-hero`,
`payment-summary`, `result-card`, `route-card`, `table`, `trust-card`

### 1b. `dashboard/*` — dashboard widgets
`empty-state`, `kpi-stat`, `quick-action`, `section-header`, `status-badge`,
`overview/action-card`, `overview/shortcut-chip`

### 1c. `bookings/detail-*` — booking-detail section suite (already componentized)
`detail-summary-card`, `detail-itinerary`, `detail-passengers-contact`,
`detail-payment-card`, `detail-documents-card`, `detail-pnr-ticketing`,
`detail-timeline`, `detail-cancellation`, `detail-updates`, `detail-help-card`,
`detail-guest-account-cta`, plus checkout/promo/fare helpers
(`checkout-fare-breakdown`, `promo-code-card`, `fare-session-countdown`,
`gateway-payment-status-card`, `payment-documents-panel`, …)

### 1d. `support/*`, `customer/*`, `geo/*`, `time/*`, `themes/*`
`support/ticket-timeline`, `customer/support-status-badge`,
`geo/country-select-options`, `time/local`,
`themes/admin/jetpakistan/components/{empty-state,status-badge}`

### 1e. Breeze defaults (framework baseline)
`text-input`, `input-label`, `input-error`, `primary-button`, `secondary-button`,
`danger-button`, `dropdown`, `dropdown-link`, `modal`, `nav-link`,
`responsive-nav-link`, `application-logo`, `auth-session-status`,
`account-dropdown`, `customer-account-dropdown`, `turnstile`, `planned-hint`

---

## 2. Duplication map (the real Phase-0 problem to resolve)

Multiple components serve the same role across namespaces. The redesign must
choose **one canonical** per row and route all dashboard pages to it (deprecate
the rest in place — do not delete confirmed-used components without verification).

| Primitive | Candidates found | Recommended canonical | Rationale |
|---|---|---|---|
| Empty state | `dashboard/empty-state`, `jp/empty-state`, `themes/admin/jetpakistan/components/empty-state` | `dashboard/empty-state` for dashboards | Dashboard-scoped; keep `jp/empty-state` for public. Fold theme variant in. |
| Status badge | `dashboard/status-badge`, `customer/support-status-badge`, `themes/admin/jetpakistan/components/status-badge` | `dashboard/status-badge` (parameterised by variant) | One badge with status→variant map; keep support-specific mapping as a wrapper. |
| Button | `jp/button`, `primary-button`, `secondary-button`, `danger-button` | `jp/button` (variant prop) | Branded, variant-driven; Breeze buttons are auth-screen legacy. |
| Input | `jp/input`, `text-input` | `jp/input` (+ `jp/form-group`) | Branded field with label/help/validation slots. |
| Modal | `jp/modal`, `modal` | `jp/modal` | Branded; Breeze `modal` is legacy. |
| Table | `jp/table` | `jp/table` (+ responsive wrapper — see gap T-1) | Single source; needs mobile-card mode. |
| Alert | `jp/alert` | `jp/alert` | Single source. |
| Card | `jp/card` | `jp/card` | Single source; specialise via slots. |
| Account dropdown | `account-dropdown`, `customer-account-dropdown` | Merge into one role-aware dropdown | Two variants of the same profile menu. |

**Duplication is expected in a white-label base and is not a defect per se** —
but "one coherent design system" (CLAUDE.md) requires a documented canonical set.
This table is that decision surface for the reviewer.

---

## 3. Coverage vs the phase's required primitive list

Legend: **✅ exists** · **◑ partial / needs extension** · **➕ create**

| Required primitive (from phase brief) | Status | Backed by |
|---|---|---|
| Dashboard shell | ◑ | `ota-dashboard-shell` (customer/agent) + `dashboard.blade.php` (admin/staff) — consolidate |
| Desktop sidebar | ✅ | `layouts/partials/dashboard-sidebar-{customer,agent,staff,admin}` |
| Mobile navigation drawer | ◑ | `mobile-app-bottom-nav` + `mobile-app-top-bar` (bottom-nav pattern, not drawer) |
| Top navigation | ✅ | `layouts/navigation`, portal nav partials |
| Breadcrumbs | ➕ | none found — create (fold into page header) |
| Page header | ✅ | `dashboard/section-header` |
| Profile dropdown | ◑ | `account-dropdown` + `customer-account-dropdown` — merge |
| Notifications | ◑ | `platform.module:notifications` exists; UI surface to confirm |
| KPI cards / stat tiles | ✅ | `dashboard/kpi-stat`, `dashboard/overview/action-card` |
| Tables | ✅ | `jp/table` |
| Responsive table wrapper | ➕ (gap T-1) | not found — create mobile-card/scroll wrapper |
| Filters / search controls | ◑ | per-page partials (e.g. `accounting/ledger/_filters`, `finance/statements/_filters`) — promote to shared |
| Form sections | ✅ | `jp/form-group` |
| Inputs / selects / textareas | ◑ | `jp/input` (extend for select/textarea/date/amount/file — gap F-1) |
| Date / amount / file fields | ➕ (gap F-1) | specialise from `jp/input` |
| Validation messages / help text | ✅ | `input-error`, `jp/form-group` slots |
| Buttons | ✅ | `jp/button` |
| Tabs | ◑ | `bookings/partials/detail-tabs-nav` (page-local) — promote |
| Status badges | ✅ | `dashboard/status-badge` |
| Alerts | ✅ | `jp/alert` |
| Empty states | ✅ | `dashboard/empty-state` |
| Loading states | ➕ | not found as a component — create (skeleton/spinner) |
| Modals | ✅ | `jp/modal` |
| Pagination | ◑ | Laravel default (Tailwind paginator in `tailwind.config` content) — style to tokens |
| Timelines | ✅ | `jp/booking-timeline`, `bookings/detail-timeline`, `support/ticket-timeline` |
| Booking summaries | ✅ | `bookings/detail-summary-card`, `jp/payment-summary` |
| Traveler cards | ◑ | `dashboard/customer/partials/default-traveler-card` (page-local) — promote |
| Support cards | ✅ | `support/ticket-timeline`, `customer/support-status-badge` |
| Wallet / finance summaries | ◑ | `dashboard/finance/statements/_summary-cards` (partials) — promote to component |

**Genuinely missing (create):** breadcrumbs, responsive-table wrapper (T-1),
loading/skeleton state, and specialised form fields (date/amount/file — F-1).
Everything else exists and needs **adoption + token alignment**, not creation.

---

## 4. Named gaps carried into the redesign phase

- **T-1 — Responsive table wrapper.** `jp/table` has no documented mobile-card
  fallback. The phase requires tables usable at 360 px with no horizontal page
  overflow. Create a shared wrapper (horizontal-scroll container **or**
  card-per-row at `< md`) and apply to all dashboard tables. Preserve all
  columns, actions, and authorization checks.
- **F-1 — Form field family.** Standardise `jp/input` + `jp/form-group` to cover
  select, textarea, date, amount (currency), and file, each with: visible label,
  `for`/`id` association, required indicator, consistent height/radius,
  validation + disabled + readonly states, mobile-safe width, no placeholder-as-label,
  no persistent blue/cyan focus glow, `:focus-visible` preserved.
- **Loading states.** No skeleton/spinner component; add one for async panels.
- **Breadcrumbs.** Add to the shared page header.
- **P-1 — Permission-denied / access-denied state (new in Rev 1).** No dedicated
  component found. Required by **Agent Staff** (a permission-limited user
  deep-linking to a gated Agent page) and by the auth **access-denied** /
  module-disabled surface (`PlatformModuleDisabledException`). Build one shared
  state: clear message, what's missing (permission/module), safe return action.
  Must not leak more than policy allows.
- **A-1 — Auth-entry form primitives (new in Rev 1).** Login, OTP, password reset,
  forced-password-change, and email-verification screens should reuse the same
  `jp/input` + `jp/form-group` + `jp/button` primitives (tokenised) rather than
  bespoke auth markup — **UI only; no change to auth logic, field names, CSRF, or
  validation.** Google sign-in already has `jp/google-sign-in`.

## 4a. Role coverage note (Rev 1)

- **Agent Staff** creates **no new page components** — it reuses Agent page
  components with gated actions plus the P-1 denied state (Document 01 §7).
- **Internal Staff / Admin** are table/form-dense; they consume `jp/table`
  (+ T-1 wrapper), the `jp/form-group`/`jp/input` family (+ F-1),
  `dashboard/kpi-stat`, `dashboard/status-badge`, and the finance/ledger partials
  already under `dashboard/{finance,accounting}/` — promoted to shared components
  where reused across Staff and Admin (Staff already renders
  `dashboard.admin.ledger.*` and `dashboard.admin.reports`).

---

## 5. Phase-0 disposition

Documentation only. **No component is created, edited, deleted, or renamed in
Phase 0.** Deletion of any "duplicate" requires confirming it is unused
(`grep -r "<x-jp.modal"` etc.) in the implementation phase — several duplicates
(e.g. Breeze buttons on auth screens) are legitimately used elsewhere and must
remain. The canonical-set decisions in §2 and the gaps in §4/§4a are the inputs
to **Phase 1** (shared shell + design system) and the per-role implementation
phases (P2–P10).
