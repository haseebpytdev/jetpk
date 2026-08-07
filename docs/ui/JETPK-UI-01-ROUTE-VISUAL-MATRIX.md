# JETPK-UI-01 â€” Route Visual Matrix

**Phase:** JETPK-UI-01 â€” Final UI Closure Audit
**Baseline SHA:** `8d62db8c2a37038e52e3130d45b9ad284510bfee`
**Branch:** `phase/jetpk-ui-01-final-ui-closure-audit`
**Evidence root:** `%TEMP%\jetpk-ui-01-evidence` (outside repository)

## Legend

| Status | Meaning |
|--------|---------|
| VISUALLY_AUDITED | Runtime screenshot or Playwright capture this audit |
| INVENTORIED_NOT_VISUALLY_AUDITED | Route inventoried; no runtime visual evidence this pass |
| FIXTURE_AUDITED_HISTORICAL | Prior JP-UI-03A matrix pass (119/119); not re-run this audit |
| BLOCKED | Production preview server failure prevented audit |
| GATE_ONLY | Auth gate observed without authenticated interior |

## Viewport matrix (mandatory)

| Viewport | Captured this audit |
|----------|----------------------|
| 1440Ã—900 | Yes (homepage, admin overview) |
| 1280Ã—800 | Yes (login, results, admin CMS, staff overview) |
| 1024Ã—768 | Yes (admin bookings) |
| 768Ã—1024 | No â€” gap JETPK-UI-017 |
| 390Ã—844 | Yes (homepage mobile, customer/agent gate) |
| 360Ã—800 | No â€” gap JETPK-UI-020 |

## Theme matrix

| Theme | Public shell | Dashboard | Portals |
|-------|-------------|-----------|---------|
| Light | VISUALLY_AUDITED (dev 3000) | VISUALLY_AUDITED | GATE_ONLY |
| Dark | Partial (Auto toggle present) | Not captured | Not captured |
| Reduced motion | Not captured | Not captured | Not captured |

---

## 1. Public homepage and CMS (11 routes)

| Route | Owner | Audience | Auth | Theme | Responsive | Data source | Fallback | Playwright | Audit status |
|-------|-------|----------|------|-------|------------|-------------|----------|------------|--------------|
| `/` | frontend | guest | no | light+dark | 1440,390 | Laravel CMS + fixtures | Blade `frontend/home` | homepage.spec.ts | VISUALLY_AUDITED |
| `/about-us` | frontend | guest | no | light+dark | â€” | CMS | Blade | public-content.spec.ts | FIXTURE_AUDITED_HISTORICAL |
| `/contact` | frontend | guest | no | light+dark | â€” | CMS | Blade | public-content.spec.ts | INVENTORIED_NOT_VISUALLY_AUDITED |
| `/faq` | frontend | guest | no | light+dark | â€” | CMS | Blade | public-content.spec.ts | FIXTURE_AUDITED_HISTORICAL |
| `/support` | frontend | guest | no | light+dark | â€” | CMS | Blade | public-content.spec.ts | FIXTURE_AUDITED_HISTORICAL |
| `/privacy` | frontend | guest | no | light+dark | â€” | CMS | Blade | public-content.spec.ts | INVENTORIED_NOT_VISUALLY_AUDITED |
| `/terms` | frontend | guest | no | light+dark | â€” | CMS | Blade | public-content.spec.ts | INVENTORIED_NOT_VISUALLY_AUDITED |
| `/sitemap` | frontend | guest | no | light | â€” | static | â€” | public-content.spec.ts | INVENTORIED_NOT_VISUALLY_AUDITED |
| `/pages/[slug]` | frontend | guest | no | light+dark | â€” | Laravel CMS | Blade | public-content.spec.ts | INVENTORIED_NOT_VISUALLY_AUDITED |
| `/legal/[slug]` | frontend | guest | no | light+dark | â€” | Laravel CMS | Blade | public-content.spec.ts | INVENTORIED_NOT_VISUALLY_AUDITED |
| `/[slug]` | frontend | guest | no | light+dark | â€” | Laravel CMS | Blade | public-content.spec.ts | INVENTORIED_NOT_VISUALLY_AUDITED |

## 2. Search and results (3 routes)

| Route | Owner | Audience | Auth | Theme | Responsive | Data source | Fallback | Playwright | Audit status |
|-------|-------|----------|------|-------|------------|-------------|----------|------------|--------------|
| `/flights/results` | frontend | guest | no | light+dark | 1280 | Laravel search API | Blade results | flight-results.spec.ts | VISUALLY_AUDITED (empty state) |
| `/flights/return-options` | frontend | guest | no | light+dark | â€” | Laravel | Blade | flight-results.spec.ts | INVENTORIED_NOT_VISUALLY_AUDITED |
| `/flights/fare-selection` | frontend | guest | no | light+dark | â€” | Laravel | â€” | â€” | INVENTORIED_NOT_VISUALLY_AUDITED |

## 3. Flight detail and fare selection

Covered above; fare-selection gap JETPK-UI-005.

## 4. Checkout and payment (18 routes)

| Route | Owner | Audience | Auth | Theme | Responsive | Data source | Fallback | Playwright | Audit status |
|-------|-------|----------|------|-------|------------|-------------|----------|------------|--------------|
| `/booking/passengers` | frontend | guest | session | light+dark | 1280 | Laravel booking | Blade | standard-booking-passengers.spec.ts | VISUALLY_AUDITED |
| `/booking/review` | frontend | guest | session | light+dark | â€” | Laravel | Blade | standard-booking-review-payment.spec.ts | INVENTORIED_NOT_VISUALLY_AUDITED |
| `/booking/payment/manual` | frontend | guest | session | light+dark | â€” | Laravel | Blade | standard-booking-review-payment.spec.ts | INVENTORIED_NOT_VISUALLY_AUDITED |
| `/booking/payment/card` | frontend | guest | session | light+dark | â€” | Laravel handoff | Blade | standard-booking-review-payment.spec.ts | INVENTORIED_NOT_VISUALLY_AUDITED |
| `/booking/confirmation` | frontend | guest | session | light+dark | â€” | Laravel | Blade | standard-booking-post-booking.spec.ts | INVENTORIED_NOT_VISUALLY_AUDITED |
| `/lookup-booking` | frontend | guest | no | light+dark | â€” | Laravel | Blade | booking-lookup-turnstile.spec.ts | INVENTORIED_NOT_VISUALLY_AUDITED |
| `/groups/*` (6 routes) | frontend | guest | varies | light+dark | â€” | Laravel | Blade | group specs | INVENTORIED_NOT_VISUALLY_AUDITED |
| Other booking routes | frontend | guest | varies | light+dark | â€” | Laravel | Blade | â€” | INVENTORIED_NOT_VISUALLY_AUDITED |

## 5. Authentication and password flows (9 routes)

| Route | Owner | Audience | Auth | Theme | Responsive | Data source | Fallback | Playwright | Audit status |
|-------|-------|----------|------|-------|------------|-------------|----------|------------|--------------|
| `/login` | frontend | guest | no | light+dark | 1280 | Laravel CSRF | Blade auth | auth.spec.ts | VISUALLY_AUDITED |
| `/login/otp` | frontend | guest | no | light+dark | â€” | Laravel OTP demo | Blade | auth.spec.ts | INVENTORIED_NOT_VISUALLY_AUDITED |
| `/register` | frontend | guest | no | light+dark | â€” | Laravel | Blade | auth.spec.ts | INVENTORIED_NOT_VISUALLY_AUDITED |
| `/forgot-password` | frontend | guest | no | light+dark | â€” | Laravel | Blade | auth.spec.ts | INVENTORIED_NOT_VISUALLY_AUDITED |
| `/reset-password/[token]` | frontend | guest | no | light+dark | â€” | Laravel | Blade | auth.spec.ts | INVENTORIED_NOT_VISUALLY_AUDITED |
| `/password/force-change` | frontend | auth | yes | light+dark | â€” | Laravel | Blade | auth.spec.ts | INVENTORIED_NOT_VISUALLY_AUDITED |
| `/verify-email` | frontend | auth | yes | light+dark | â€” | Laravel | Blade | auth.spec.ts | INVENTORIED_NOT_VISUALLY_AUDITED |
| `/agent/register` | frontend | guest | no | light+dark | â€” | Laravel | Blade | auth.spec.ts | INVENTORIED_NOT_VISUALLY_AUDITED |
| `/agent/register/submitted` | frontend | guest | no | light+dark | â€” | Laravel | Blade | auth.spec.ts | INVENTORIED_NOT_VISUALLY_AUDITED |

## 6. Customer portal (12 routes)

| Route | Owner | Audience | Auth | Theme | Responsive | Data source | Fallback | Playwright | Audit status |
|-------|-------|----------|------|-------|------------|-------------|----------|------------|--------------|
| `/customer` | frontend | Customer | redirect | â€” | â€” | â€” | â€” | customer-dashboard.spec.ts | INVENTORIED_NOT_VISUALLY_AUDITED |
| `/customer/dashboard` | frontend | Customer | yes | light+dark | 390 | Laravel JSON | Blade customer | customer-dashboard.spec.ts | GATE_ONLY |
| `/customer/bookings` | frontend | Customer | yes | light+dark | â€” | Laravel | Blade | customer-portal-routes.spec.ts | INVENTORIED_NOT_VISUALLY_AUDITED |
| `/customer/bookings/[reference]` | frontend | Customer | yes | light+dark | â€” | Laravel | Blade | â€” | INVENTORIED_NOT_VISUALLY_AUDITED |
| `/customer/profile` | frontend | Customer | yes | light+dark | â€” | Laravel | Blade | â€” | INVENTORIED_NOT_VISUALLY_AUDITED |
| `/customer/security` | frontend | Customer | yes | light+dark | â€” | Laravel | Blade | â€” | INVENTORIED_NOT_VISUALLY_AUDITED |
| `/customer/travelers` | frontend | Customer | yes | light+dark | â€” | Laravel | Blade | customer-dashboard.spec.ts | INVENTORIED_NOT_VISUALLY_AUDITED |
| `/customer/payments` | frontend | Customer | yes | light+dark | â€” | Laravel | Blade | â€” | INVENTORIED_NOT_VISUALLY_AUDITED |
| `/customer/invoices` | frontend | Customer | yes | light+dark | â€” | Laravel | Blade | â€” | INVENTORIED_NOT_VISUALLY_AUDITED |
| `/customer/invoices/[reference]` | frontend | Customer | yes | light+dark | â€” | Laravel | Blade | â€” | INVENTORIED_NOT_VISUALLY_AUDITED |
| `/customer/support` | frontend | Customer | yes | light+dark | â€” | Laravel | Blade | â€” | INVENTORIED_NOT_VISUALLY_AUDITED |
| `/customer/notifications` | frontend | Customer | yes | light+dark | â€” | placeholder | â€” | â€” | INVENTORIED_NOT_VISUALLY_AUDITED |

## 7â€“8. Agent and Agent Staff portal (28 routes, shared `/agent`)

Agent Staff uses same routes; RBAC enforced server-side. Representative routes inventoried; visual interior blocked (JETPK-UI-022). Playwright: `agent-dashboard.spec.ts`, `jp-ops-04-*`.

## 9â€“10. Admin and Platform Staff dashboard (33 templates Ã— 2 = 66 URLs)

| Route pattern | Owner | Audience | Auth | Theme | Responsive | Data source | Fallback | Playwright | Audit status |
|---------------|-------|----------|------|-------|------------|-------------|----------|------------|--------------|
| `/admin/dashboard` | dashboard | Admin | yes | light | 1440,390 | fixtures/Laravel RO | Blade admin | overview.smoke.spec.ts | VISUALLY_AUDITED |
| `/staff/dashboard` | dashboard | Platform Staff | yes | light | 1280 | fixtures/Laravel RO | Blade staff | overview.smoke.spec.ts | VISUALLY_AUDITED |
| `/admin/dashboard/bookings` | dashboard | Admin | yes | light | 1024 | fixtures | Blade | bookings.smoke.spec.ts | VISUALLY_AUDITED |
| `/admin/dashboard/cms/pages` | dashboard | Admin | yes | light | 1280 | fixtures | Blade CMS | cms-pages.smoke.spec.ts | VISUALLY_AUDITED |
| Remaining 29 modules Ã— 2 portals | dashboard | Admin/Staff | yes | light | â€” | fixtures | Blade | *.smoke.spec.ts | INVENTORIED_NOT_VISUALLY_AUDITED |

## 11. CMS / Page Builder

Deep editor audit deferred â€” JETPK-UI-008.

## 12. Support and static pages

Included in section 1 CMS routes.

## 13. Error, unavailable and empty states

| Route | Owner | Audit status |
|-------|-------|--------------|
| `/access-denied` | frontend | INVENTORIED_NOT_VISUALLY_AUDITED |
| `/flights/results` (no search) | frontend | VISUALLY_AUDITED empty state |
| Dashboard fixture banners | dashboard | VISUALLY_AUDITED (honest preview) |

---

## Route totals

| Category | Count |
|----------|------:|
| Frontend production routes | 82 |
| Public/guest (cms+checkout+auth+utility) | 39 |
| Customer | 12 |
| Agent | 28 |
| Agent Staff (shared shell) | 28 |
| Admin dashboard paths | 33 |
| Platform Staff dashboard paths | 33 |
| Total Next.js production UI routes | 148 |
| Visually audited this pass (deep) | 12 |
| Inventoried not visually audited | 136 |
| Blade fallback routes (additional) | ~124 page templates |

## Playwright coverage map

| Suite | Result this audit |
|-------|-------------------|
| frontend typecheck | PASS exit 0 |
| frontend lint | PASS exit 0 |
| frontend build | PASS exit 0 |
| dashboard typecheck | PASS exit 0 |
| dashboard lint | PASS exit 0 |
| dashboard build | PASS exit 0 |
| Laravel `--filter=JetPakistan` | 17 pass / 4 fail exit 1 |
| frontend targeted Playwright (7 specs) | 46 pass / 11 fail exit 1 |
| frontend audit:visual:jp-ui-01 | 1 fail / 92 not run exit 1 |
| dashboard test:smoke | TIMEOUT exit 1 |
