# Phase 0 · Document 09 — Risk, Safety, Governance & Rollback

> **REVISION 1** — Restores the **13-phase implementation plan** (replacing the
> earlier 7-commit mapping), adds the **production HTML ValidationException
> prerequisite gate**, corrects the **database/environment** description
> (local/test SQLite vs production MySQL), and records the **Authenticated-Entry**
> dependency and expanded all-role scope.

**Phase:** JETPK-DASHBOARD-UI-FOUNDATION-ROUTE-PARITY-AND-RESPONSIVE-REDESIGN
**Stage:** Phase 0 (audit & architecture) · **Baseline:** `claude/ui-master` @ `6fbfae4`

---

## 1. Hard safety rules (CLAUDE.md — non-negotiable)

The implementation phases must **not**: deploy to production; access production
SSH/SFTP; use production credentials; perform live supplier searches; create live
bookings/PNRs; ticket, cancel, void, or refund; process real payments; send
production emails; modify production data; expose secrets; work directly on
`main`; **merge own branches**; **force push**.

Use only: local env files, local database (see §3), seeders, factories,
deterministic fixtures, mocked/testing-only supplier data, local Playwright runs.

**Scope: UI layer only, all roles.** Preserve all business logic, schema,
suppliers (Sabre, PIA NDC, AirBlue, IATI, Duffel), booking lifecycle, payments,
emails, routes (names), middleware, policies, permissions, and **authentication
business logic** (auth surfaces are UI-consolidated only). Do not reintroduce
removed Mock supplier classes.

---

## 2. Git workflow & branch model

| Item | Value |
|---|---|
| Base branch | `claude/ui-master` |
| Phase branch | `claude/dashboard-ui-foundation` |
| Baseline commit | `6fbfae4637bb00e4a35b8edf3170a150d529b0b2` |
| Update base | `git pull --ff-only` on `claude/ui-master` **before** branching |
| Commits | Layered, per phase; `claude-dashboard-summary.md` updated each phase |
| Push | phase branch only |
| Merge / force push / `main` work | **Forbidden** (stop for ChatGPT/Cursor review) |
| CSRF exception (do not touch) | `payments/abhipay/callback` |

**Branch-state note:** origin `main`, `claude/ui-master`, `integration/jetpk-ui`
all at `6fbfae4`, so a fresh ff-only pull is clean. The one failure mode: a
**drifted local `claude/ui-master`** (un-pushed commits) makes `--ff-only`
**hard-fail** — reconcile local to the baseline before branching; do not `--no-ff`
or force past it.

---

## 3. Database / environment precision (Rev 1)

> The repository may use **SQLite** for local/testing defaults; the current
> **JetPakistan production deployment uses MySQL**. The UI phase must remain
> **database-agnostic** and must not alter schema or queries.

No view, matrix disposition, component, or test in this programme may assume a
specific engine. Backend files (including query/schema) are read-only context.

---

## 4. Prerequisite gate — production HTML ValidationException (Rev 1)

A **production HTML ValidationException issue** is being repaired **separately in
Cursor**. It is a **hard prerequisite** before **any** dashboard integration:
login/validation must render correctly first. No dashboard phase commits integrate
until that fix has landed (also recorded in Document 08 §0).

---

## 5. Governance gap — `.cursor/` is gitignored

`.gitignore` line 76 excludes `.cursor/`, so mandatory-reading rules/skills are
**not** in the public repo: `.cursor/skills/ui-design-brain/SKILL.md`,
`components.md`, and `.cursor/rules/*.mdc` (`laravel-production-safety`,
`v2-ui-implementation`, `sprint-workflow`, `pre-deploy-audit-gate`,
`mobile-app-cache-bust`, `phase-completion-report`, `finance-reports-qa`,
`mobile-public-results`). Only `docs/skills/ui-design-brain-OTA-CONTEXT.md` is
tracked. **Confirm these exist in the local checkout** before implementation;
Cursor runs locally and will read them if present.

---

## 6. Implementation plan (13 phases — restored)

Phase 0 (this document set) is the audit/architecture baseline. Implementation:

| Phase | Scope | Primary Phase-0 spec |
|---|---|---|
| **1** | Shared authenticated shell + canonical design-system consolidation (incl. monolith decomposition start; auth-entry layout alignment) | Docs 02, 03, 04 |
| **2** | Customer dashboard home + booking indexes | Doc 01 §5 |
| **3** | Customer booking details, travelers, account, support | Doc 01 §5, §10 |
| **4** | Agent dashboard + booking operations | Doc 01 §6 |
| **5** | Agent finance, deposits, commissions, staff management, support | Doc 01 §6 |
| **6** | Agent Staff permission-scoped dashboard & pages (nav + permission-denied) | Doc 01 §7, Doc 05 §6 |
| **7** | Internal Staff dashboard & operational pages | Doc 01 §8 |
| **8** | Admin dashboard, bookings, customers, agents, staff | Doc 01 §9 |
| **9** | Admin finance, reports, markups, ledger | Doc 01 §9 |
| **10** | Admin roles, settings, suppliers, branding, support, controls (mask secrets) | Doc 01 §9 |
| **11** | Mobile/dual-shell parity + responsive consolidation (9 viewports) | Doc 06 |
| **12** | Accessibility, branding leakage, consistency consolidation | Docs 04 §4, 07 |
| **13** | Final complete Cursor integration package (`jetpk-complete-dashboard-ui-package.zip`) | all |

Each phase is independently reviewable, produces complete files (no ellipses), and
updates `claude-dashboard-summary.md`.

---

## 7. Risk register

| # | Risk | L | I | Mitigation |
|---|---|---|---|---|
| R1 | Redesign treated as greenfield → duplicate design system/components | High | High | Reuse `ota-dashboard-*`, `jp/*`, `ota-design-system.css`; dedupe map Doc 03 |
| R2 | Hardcoded brand colour breaks white-labeling | High | High | `var(--brand-*)` only (Doc 04 §1) |
| R3 | Responsive treated as CSS-only → mobile shell / desktop-only Staff-Admin missed | High | High | Dual-shell + desktop-only facts (Doc 06) |
| R4 | Missed `?v=` cache-bust | Med | Med | Docs 04/06/08 |
| R5 | `--ff-only` hard-fail on drifted local base | Med | Low | §2 recovery |
| R6 | Monolith (`dashboard.blade.php`) decomposition regresses Staff/Admin | Med | High | Incremental extraction + visual diff; Staff/Admin normalize not restyle |
| R7 | Bare `route()`/literal `view()` breaks tenancy | Med | High | `client_route()`/`client_view()` |
| R8 | Branding leak reintroduced while restyling | Low | High | Doc 07 gate each phase; preserve anti-leakage CSS |
| R9 | Test/behaviour weakened to pass | Low | High | Never weaken; `fail=0` gate |
| R10 | `.cursor/` rules unavailable locally | Low | Med | §5 confirm |
| **R11** | **Agent Staff duplicated instead of permission-gated** | Med | High | Shared Agent views + gated actions + P-1 denied state (Doc 01 §7, Doc 05 §6) |
| **R12** | **Staff/Admin table overflow at phone widths (no mobile tree)** | Med | Med | T-1 responsive wrapper mandatory on Staff/Admin tables (Doc 03) |
| **R13** | **Auth surface touched beyond UI (logic change)** | Low | High | UI-only; ValidationException fix is a separate Cursor task (§4) |
| **R14** | **DB-engine assumption leaks into UI/tests** | Low | Med | Database-agnostic rule (§3) |

---

## 8. Rollback

Work lives on the pushed **phase branch**, never self-merged:
- **Abort phase:** `git branch -D claude/dashboard-ui-foundation` (local) and
  `git push origin --delete claude/dashboard-ui-foundation` (if pushed). `main`
  and `claude/ui-master` remain at `6fbfae4`.
- **Revert one layer:** `git revert <sha>` (phases are separable).
- **Reset to baseline:** `git reset --hard 6fbfae4` on the phase branch.
- **No production rollback needed:** nothing deploys.

For **Phase 0** specifically there is nothing to roll back — no repository file is
modified; these documents are added (as the audit layer) only if wanted.

---

## 9. Phase-0 disposition

Documentation only. **No code, UI, route, schema, supplier, payment, email, or
auth-logic path is modified in Phase 0.** Governance, safety rules, git model,
prerequisite gate, DB precision, the 13-phase plan, risk register, and rollback
are recorded here as the control framework for Phases 1–13.
