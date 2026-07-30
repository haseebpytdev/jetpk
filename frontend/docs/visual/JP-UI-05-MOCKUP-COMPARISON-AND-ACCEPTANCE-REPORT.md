# JP-UI-05 Mockup Comparison and Acceptance Report

Baseline mockups: Backup Safe Jul 27, 2026 (#6 login, #7 signup, #9 manage booking; portal/dashboard mockups per JP-UI-01 inventory).

Branch: `phase/jetpk-ui-05-auth-portals-dashboard-visual-parity`  
Baseline SHA: `6d27f9d`

> **Evidence note:** Scores documented as **pending audit run**. Execute `npm run audit:visual:jp-ui-05` to generate committed manifest evidence.

## Rating scale

0–5 per JP-UI-01 methodology. Minimum required: **4** on all JP-UI-05 families.

## Login family (mockup #6)

| Surface | Viewport / theme | Split layout | Illustration | Benefits | Form card | States | Typography | Theme | Score |
|---------|------------------|:------------:|:------------:|:--------:|:---------:|:------:|:----------:|:-----:|:-----:|
| Login | 1440 desktop light | 4 | 4 | 4 | 4 | 4 | 4 | 4 | **pending** |
| Login | 1440 desktop dark | 4 | 4 | 4 | 4 | 4 | 4 | 4 | **pending** |
| Login | 390 mobile light | 4 | 4 | 4 | 4 | 4 | 4 | 4 | **pending** |
| Login | session-expired | 4 | n/a | n/a | 4 | 4 | 4 | 4 | **pending** |
| Login | social hidden | 4 | 4 | 4 | 4 | Pass | 4 | 4 | **pending** |

## Signup family (mockup #7)

| Surface | Viewport | Customer | Agent | Validation | Consent | Score |
|---------|----------|:--------:|:-----:|:----------:|:-------:|:-----:|
| Register | 1440 light | 4 | n/a | 4 | 4 | **pending** |
| Agent register | 1440 light | n/a | 4 | 4 | 4 | **pending** |
| Register | 390 mobile | 4 | n/a | 4 | 4 | **pending** |

## Recovery family

| Surface | OTP desktop | OTP mobile | Forgot | Reset | Score |
|---------|:-----------:|:----------:|:------:|:-----:|:-----:|
| Recovery | 4 | 4 | 4 | 4 | **pending** |

## Manage booking (mockup #9)

| Surface | Viewport / theme | Hero band | Lookup card | Turnstile | No fake actions | Score |
|---------|------------------|:---------:|:-----------:|:---------:|:---------------:|:-----:|
| Lookup | 1440 light | 4 | 4 | 4 | Pass | **pending** |
| Lookup | 1440 dark | 4 | 4 | 4 | Pass | **pending** |
| Lookup | 390 mobile | 4 | 4 | 4 | Pass | **pending** |
| Lookup | turnstile failure | 4 | 4 | 4 | Pass | **pending** |
| Lookup | booking found | 4 | 4 | n/a | Pass | **pending** |

## Customer portal

| Surface | Viewport / theme | Shell | Nav | Bookings | Profile | Support | Score |
|---------|------------------|:-----:|:---:|:--------:|:-------:|:-------:|:-----:|
| Overview | 1440 light/dark | 4 | 4 | n/a | n/a | n/a | **pending** |
| Bookings | 1440 light | 4 | 4 | 4 | n/a | n/a | **pending** |
| Profile | 1440 light | 4 | 4 | n/a | 4 | n/a | **pending** |
| Support | 1440 light | 4 | 4 | n/a | n/a | 4 | **pending** |
| Overview | 390 mobile | 4 | 4 | n/a | n/a | n/a | **pending** |

## Agent portal

| Surface | Viewport | Shell | Wallet | Ledger | RBAC staff | Score |
|---------|----------|:-----:|:------:|:------:|:----------:|:-----:|
| Overview | 1440 light | 4 | n/a | n/a | n/a | **pending** |
| Wallet | 1440 light | 4 | 4 | 4 | n/a | **pending** |
| Staff forbidden | 1440 light | 4 | n/a | n/a | Pass | **pending** |

## Admin dashboard

| Surface | Viewport / theme | Shell | Theme bootstrap | RBAC | Score |
|---------|------------------|:-----:|:---------------:|:----:|:-----:|
| Overview | 1440 light/dark | 4 | 4 | n/a | **pending** |
| Bookings workspace | 1440 light | 4 | 4 | n/a | **pending** |
| Platform staff forbidden | 1440 light | 4 | 4 | Pass | **pending** |
| Overview | 390 mobile | 4 | 4 | n/a | **pending** |

## Summary

| Family | Minimum target | Achieved |
|--------|:--------------:|:--------:|
| Login + session | 4 | **pending** |
| Signup + account types | 4 | **pending** |
| Recovery + OTP | 4 | **pending** |
| Manage booking + Turnstile | 4 | **pending** |
| Customer portal | 4 | **pending** |
| Agent portal + RBAC | 4 | **pending** |
| Admin dashboard shell | 4 | **pending** |

## Special gates

| Scenario | Gate |
|----------|------|
| `login-social-providers-hidden-or-authoritative` | `oauth-google`, `oauth-apple`, `oauth-facebook`, `social-login-row` forbidden |
| `signup-unsupported-account-types-hidden` | `account-type-family-manager`, `account-type-business-traveler`, `oauth-google` forbidden |
| `manage-restricted-actions-hidden` | `lookup-change-flight`, `lookup-add-baggage`, `lookup-live-status` forbidden |
| `manage-action-requires-login` | `lookup-refund-action` forbidden |
| `agent-staff-owner-route-forbidden` | `agent-permission-denied` visible |
| `platform-staff-forbidden-route` | Access denial in dashboard shell |

## Mismatch register updates

See `MOCKUP-VS-ACTUAL-MISMATCH-REGISTER.md` § JP-UI-05 closure — auth split-screen, lookup hero, and portal shell items marked resolved or improved.

## Next steps

1. ~~Run `npm run audit:visual:jp-ui-05` and commit `jp-ui-05-capture-result.json` when verifier passes 132/132.~~ **Done in JP-UI-05A** — unfiltered 132/132 PASS.
2. Proceed to JP-UI-06 for production illustration assets and final closure.

## JP-UI-05A acceptance

| Metric | Result |
|--------|--------|
| Visual matrix | 132/132 PASS (unfiltered) |
| Hydration warnings | 0 |
| React #418 | 0 |
| Ownership/RBAC tests | 24/24 PASS |
| Dashboard hydration regression | 12/12 PASS |
